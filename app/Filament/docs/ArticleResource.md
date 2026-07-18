# ArticleResource

Filament v5 resource for managing blog articles. Follows the "resource directory" convention: `ArticleResource` itself is a thin coordinator that delegates form/table definition to dedicated classes, rather than defining `form()`/`table()` inline.

## File map

| Role | Path |
|---|---|
| Resource | `app/Filament/Resources/Articles/ArticleResource.php` |
| Form schema | `app/Filament/Resources/Articles/Schemas/ArticleForm.php` |
| Table schema | `app/Filament/Resources/Articles/Tables/ArticlesTable.php` |
| List page | `app/Filament/Resources/Articles/Pages/ListArticles.php` |
| Create page | `app/Filament/Resources/Articles/Pages/CreateArticle.php` |
| Edit page | `app/Filament/Resources/Articles/Pages/EditArticle.php` |
| Model | `app/Models/Article.php` |
| Related models | `app/Models/Category.php`, `app/Models/Tag.php` |
| Status enum | `app/Enums/ArticleStatus.php` |
| Image variant enum | `app/Enums/ImageVariant.php` |
| Reusable slug field | `app/Filament/Support/SlugInput.php` |
| Reusable image block | `app/Filament/Support/ImageBlock.php` |
| Reusable FAQ block | `app/Filament/Support/FaqBlock.php` |
| Image pipeline orchestrator | `app/Support/Images/ArticleImageProcessor.php` |
| WebP conversion | `app/Support/Images/ImageConverter.php` |
| Configurable image sizes | `app/Support/Images/ImageSizeSettings.php` |
| Schema migration | `database/migrations/2026_07_17_153849_create_articles_table.php` |

There are **no RelationManagers**. The `categories`/`tags` many-to-many relations are managed inline on the article form via relationship `Select` fields with `createOptionForm()`, rather than dedicated RelationManager pages.

## Navigation & pages

```php
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema { return ArticleForm::configure($schema); }
    public static function table(Table $table): Table   { return ArticlesTable::configure($table); }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit'   => EditArticle::route('/{record}/edit'),
        ];
    }
}
```

Three standard routes only — no dedicated "view" page. Navigation label/slug default from the model name; only the icon is customized.

## Form schema (`ArticleForm`)

Four `Section`s, each `->columnSpanFull()`.

### Section "Article" (2 columns)

```php
TextInput::make('title')->required()->maxLength(255),
SlugInput::make('title'),
Select::make('status')
    ->options(ArticleStatus::class)
    ->default(ArticleStatus::Draft)
    ->required()
    ->live(),
DateTimePicker::make('published_at')
    ->visible(fn (Get $get): bool => in_array(self::status($get), [ArticleStatus::Published, ArticleStatus::Scheduled], true))
    ->required(fn (Get $get): bool => self::status($get) === ArticleStatus::Scheduled)
    ->helperText('Leave empty when publishing to use the current time.'),
```

`status` is `->live()` so the `published_at` field's `visible()`/`required()` closures re-evaluate on every change. Both closures go through a private helper:

```php
private static function status(Get $get): ?ArticleStatus
{
    $state = $get('status');

    return $state instanceof ArticleStatus ? $state : ArticleStatus::tryFrom((string) $state);
}
```

This exists because Filament form state for a `status` field can hold either the backing string (`'draft'`) or an `ArticleStatus` enum instance depending on where the value came from (initial load vs. live update), so every comparison normalizes first. The same normalization is duplicated in `ArticleResource::fillPublishedAt()` (see below) since it operates on raw submitted `$data` rather than a `Get` closure.

`published_at` is visible for Published/Scheduled, and only *required* for Scheduled — a Published article can be saved with the field empty because the save-time hook defaults it to `now()`.

### Section "Content"

```php
Builder::make('content')
    ->blocks([
        Block::make('richText')
            ->label('Rich text')
            ->icon(Heroicon::Bars3BottomLeft)
            ->schema([
                RichEditor::make('content')->hiddenLabel()->required(),
            ]),
        ImageBlock::make(),
        FaqBlock::make(),
    ])
    ->reorderableWithButtons()
    ->collapsible()
    ->addActionLabel('Add block'),
```

A Filament `Builder` field models the article body as an ordered array of typed blocks (`richText`, `image`, `faq`), persisted as JSON in the `content` column (cast to `array` on the model). Editors can reorder, collapse, and add blocks freely — this is the CMS-style content editor for the article.

### Section "Taxonomy" (2 columns)

```php
Select::make('categories')
    ->relationship('categories', 'name')
    ->multiple()->searchable()->preload()
    ->createOptionForm([
        TextInput::make('name')->required()->maxLength(255),
        SlugInput::make('name'),
    ]),
Select::make('tags')
    ->relationship('tags', 'name')
    ->multiple()->searchable()->preload()
    ->createOptionForm([
        TextInput::make('name')->required()->maxLength(255),
        SlugInput::make('name'),
    ]),
```

Both taxonomies use the relationship-select-with-inline-create pattern instead of RelationManagers, letting editors create a new category or tag on the fly without leaving the article form.

### Section "SEO" (2 columns, collapsed by default)

```php
TextInput::make('meta_title')->maxLength(255),
Textarea::make('meta_description')->rows(3),
```

Note: `excerpt` is a fillable, DB-backed column (see [Model](#model-article)) but currently has **no form field** — it's not editable through this resource yet.

## Table (`ArticlesTable`)

Columns: `id` (sortable), `title` (searchable, sortable), `slug` (searchable, hidden-by-default toggle), `status` (badge, colored/labeled via the `ArticleStatus` enum), `published_at` (dateTime, sortable), `categories.name` (badge, relationship column), `tags.name` (badge, hidden-by-default toggle), `created_at`/`updated_at` (dateTime, sortable, hidden-by-default toggle).

`->defaultSort('created_at', 'desc')`.

Filters:
- `SelectFilter::make('status')->options(ArticleStatus::class)`
- `SelectFilter::make('categories')->relationship('categories', 'name')->multiple()->preload()`
- `SelectFilter::make('tags')->relationship('tags', 'name')->multiple()->preload()`

Actions: `recordActions([EditAction::make()])`, `toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])`. No view action, no custom table actions.

## Reusable custom components

### `SlugInput::make(string $sourceField)`

```php
public static function make(string $sourceField): TextInput
{
    return TextInput::make('slug')
        ->required()->maxLength(255)->alphaDash()
        ->unique(ignoreRecord: true)
        ->suffixAction(
            Action::make('generateSlug')
                ->label('Generate slug')
                ->icon(Heroicon::ArrowPath)
                ->action(function (Get $get, Set $set) use ($sourceField): void {
                    $set('slug', Str::slug((string) $get($sourceField)));
                }),
        );
}
```

A project-wide slug-field factory. Attaches a suffix `Action` button that reads another field's live value via `Get` and writes the slugified result via `Set` — the canonical Filament v5 cross-field reactivity pattern. Used for `Article.title → slug`, and reused verbatim inside the `categories`/`tags` `createOptionForm()`s (`name → slug`).

### `ImageBlock::make()`

```php
public static function make(): Block
{
    return Block::make('image')
        ->label('Image')->icon(Heroicon::Photo)
        ->schema([
            FileUpload::make('image')
                ->hiddenLabel()->image()->imageEditor()->required()
                ->disk(ArticleImageProcessor::DISK)               // 'public'
                ->directory(ArticleImageProcessor::TMP_DIRECTORY) // 'articles/tmp'
                ->visibility('public')
                ->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file): string => self::storageFileName($file),
                ),
            TextInput::make('alt')->label('Alt text')->required()->maxLength(255),
            TextInput::make('caption')->label('Caption')->maxLength(500),
        ]);
}

private static function storageFileName(TemporaryUploadedFile $file): string
{
    $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

    return ($baseName !== '' ? $baseName : 'image')
        .'-tmp'.Str::lower(Str::random(6))
        .'.'.$file->extension();
}
```

All uploads initially land in `articles/tmp/` rather than the final `articles/{id}/` directory — a new article doesn't have an ID yet at form-fill time, and existing articles shouldn't get half-processed images before the record actually saves. The stored filename is the slugified original name plus a random `-tmp{6 chars}` suffix, with the extension always derived server-side from `$file->extension()` (never trusted from the client).

### `FaqBlock::make()`

```php
public static function make(): Block
{
    return Block::make('faq')
        ->label('FAQ')->icon(Heroicon::QuestionMarkCircle)
        ->schema([
            TextInput::make('heading')->maxLength(255),
            Repeater::make('items')
                ->hiddenLabel()
                ->schema([
                    TextInput::make('question')->required()->maxLength(500)->live(onBlur: true),
                    RichEditor::make('answer')->required(),
                ])
                ->required()->minItems(1)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->reorderableWithButtons()
                ->addActionLabel('Add question'),
        ]);
}
```

A FAQ content block: an optional `heading` plus a `Repeater` of question/answer pairs (answers are rich text HTML), intended to render as an accordion on the frontend. `question` is `->live(onBlur: true)` so `itemLabel()` shows the question text on collapsed repeater items. The image pipeline ignores this block entirely (it only processes `type === 'image'`).

Stored block shape:

```php
[
    'type' => 'faq',
    'data' => [
        'heading' => ?string,
        'items' => [
            ['question' => string, 'answer' => string /* HTML */],
            // ...
        ],
    ],
]
```

## Business logic on the Resource

```php
public static function fillPublishedAt(array $data): array
{
    $status = $data['status'] ?? null;
    $status = $status instanceof ArticleStatus ? $status : ArticleStatus::tryFrom((string) $status);

    if ($status === ArticleStatus::Published && empty($data['published_at'])) {
        $data['published_at'] = now();
    }

    return $data;
}
```

A static helper on `ArticleResource` itself (not the model, not a trait), called from both `CreateArticle` and `EditArticle` page hooks. It normalizes `status` (which may arrive as an enum instance or a raw string, same ambiguity as the form's `Get` closures) and defaults `published_at` to `now()` when publishing without an explicit date.

## Page lifecycle hooks

### `CreateArticle`

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    return ArticleResource::fillPublishedAt($data);
}

protected function afterCreate(): void
{
    $record = $this->getRecord();

    if ($record instanceof Article) {
        app(ArticleImageProcessor::class)->process($record);
    }
}
```

### `EditArticle`

```php
protected bool $hasProcessedImages = false;

protected function getHeaderActions(): array
{
    return [DeleteAction::make()];
}

protected function mutateFormDataBeforeSave(array $data): array
{
    return ArticleResource::fillPublishedAt($data);
}

protected function afterSave(): void
{
    $record = $this->getRecord();

    if ($record instanceof Article) {
        $this->hasProcessedImages = app(ArticleImageProcessor::class)->process($record);
    }
}

protected function getRedirectUrl(): ?string
{
    if ($this->hasProcessedImages) {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    return parent::getRedirectUrl();
}
```

`getRedirectUrl()` is overridden to force a full page remount after image processing. `ArticleImageProcessor` rewrites `content`'s image paths from `articles/tmp/...` to `articles/{id}/...` *after* the form has already submitted; Livewire's normal in-place state refill doesn't pick up that rewritten path for the FilePond preview, so the edit page redirects to itself to force a clean reload with the new path.

## Image pipeline

The article image workflow is a two-phase, deferred process: the `FileUpload` component only ever stages files; the actual move, resize, and cleanup happens afterward in `afterCreate`/`afterSave`.

1. **Upload** — `ImageBlock`'s `FileUpload` stores the raw file at `articles/tmp/{slug}-tmp{random6}.{ext}` on the `public` disk.

2. **`ArticleImageProcessor::process(Article $article)`** — called from both `afterCreate` and `afterSave`:
   - Iterates every block in `$article->content`.
   - For each `image` block whose path starts with `articles/tmp/`, derives a unique base filename (strips the `-tmp{6}` suffix via `TMP_SUFFIX_PATTERN`, de-duplicated against existing files in `articles/{id}/` via `uniqueBaseName()`).
   - Calls `ImageConverter::convert()` to generate WebP variants into `articles/{article->id}/`.
   - Deletes the original tmp upload and rewrites `content[$index]['data']['image']` to the new **Original** variant path.
   - On conversion failure, logs a warning and leaves the tmp upload untouched (non-fatal, block skipped).
   - If any block changed, persists via `$article->updateQuietly(['content' => $content])` — quiet to avoid re-triggering model events (and re-entrant processing).
   - Always finishes with `cleanupOrphans($article)`, which deletes any file in `articles/{id}/` whose base name is no longer referenced by any image block — handles images removed or replaced in the Builder.

3. **`ImageConverter::convert()`** — intervention/image v4 usage:

   ```php
   public function convert(Filesystem $disk, string $sourcePath, string $directory, string $baseName, ImageSizeSettings $sizes): array
   {
       $image = $this->manager->decodeBinary($disk->get($sourcePath));
       $landscape = $image->width() >= $image->height();

       foreach (ImageVariant::cases() as $variant) {
           $copy = clone $image;
           $copy->scaleDown(width: $sizes->maxWidth($variant, $landscape));

           $path = "{$directory}/{$baseName}{$variant->fileSuffix()}.webp";
           $disk->put($path, (string) $copy->encode(new WebpEncoder(quality: self::WEBP_QUALITY)));

           $paths[$variant->value] = $path;
       }

       return $paths;
   }
   ```

   Orientation is decided once from the source image (landscape if `width >= height`), then each `ImageVariant` case (`Original`, `Desktop`, `Mobile`, `Thumbnail`) is scaled to its own max width for that orientation via `scaleDown()` (never upscales) and encoded to `.webp` at quality 82. Output filenames use each variant's `fileSuffix()` (`''`, `-desktop`, `-mobile`, `-thumbnail`).

4. **`ImageSizeSettings`** — per-variant/per-orientation max widths, configurable at runtime via a `Setting` model record under key `images.sizes`, merged over hardcoded defaults with `array_replace_recursive`:

   | Variant | Landscape | Portrait |
   |---|---|---|
   | Original | 2560 | 1440 |
   | Desktop | 1920 | 1080 |
   | Mobile | 768 | 480 |
   | Thumbnail | 320 | 240 |

5. **Cleanup on delete** — `Article::booted()` registers a `deleted` listener that purges the entire directory:

   ```php
   static::deleted(function (Article $article): void {
       Storage::disk('public')->deleteDirectory("articles/{$article->id}");
   });
   ```

## Model (`Article`)

```php
#[Fillable(['title', 'slug', 'excerpt', 'content', 'status', 'published_at', 'meta_title', 'meta_description'])]
class Article extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class); }
    public function tags(): BelongsToMany       { return $this->belongsToMany(Tag::class); }
}
```

Uses PHP 8 attribute-based `#[Fillable(...)]` rather than a classic `protected $fillable` property. `content` is cast to `array` (backs the Builder JSON); `status` is cast to the `ArticleStatus` backed enum. `categories`/`tags` are standard `belongsToMany` pivots (`article_category`, `article_tag`).

## Enums

**`ArticleStatus`** (`draft` / `published` / `scheduled`) implements `HasColor` + `HasLabel`, driving both the form's status `Select` options and the table's badge column/filter:

| Case | Label | Color |
|---|---|---|
| `Draft` | Draft | gray |
| `Published` | Published | success |
| `Scheduled` | Scheduled | warning |

**`ImageVariant`** (`original` / `desktop` / `mobile` / `thumbnail`) exposes `fileSuffix()`, used throughout the image pipeline for consistent variant filenames.

## Database schema

`articles` table:

```php
$table->id();
$table->string('title');
$table->string('slug')->unique();
$table->text('excerpt')->nullable();      // fillable, not on the form
$table->json('content')->nullable();
$table->string('status')->default('draft')->index();
$table->timestamp('published_at')->nullable();
$table->string('meta_title')->nullable();
$table->text('meta_description')->nullable();
$table->timestamps();
```

Plus pivot tables `article_category` and `article_tag` backing the `categories`/`tags` many-to-many relations.

## Notable Filament v5 patterns used here

- Resource split into dedicated `Schemas`/`Tables`/`Pages` sub-namespaces instead of one monolithic class.
- `Get`/`Set` closures for cross-field reactivity (slug generation; `published_at` visibility tied to `status`).
- `->live()` on a `Select` to drive reactive `visible()`/`required()` closures elsewhere in the form.
- Enum-driven `Select` options (`ArticleStatus::class` passed directly to `->options()`) via `HasLabel`/`HasColor`, reused identically for the table's badge column and filter.
- `Builder` field (polymorphic block repeater) for flexible CMS-style content composition.
- `->suffixAction()` for inline generate-from-another-field UX.
- `->createOptionForm()` on relationship `Select`s instead of RelationManagers for lightweight inline taxonomy creation.
- Deferred two-phase image processing: uploads stage in a tmp directory; a page lifecycle hook (`afterCreate`/`afterSave`) performs the real move, variant generation, and content rewrite.
- Custom `getRedirectUrl()` override to force a full remount after server-side mutation of upload-derived state.
- `updateQuietly()` to persist processed content without re-firing model events.
