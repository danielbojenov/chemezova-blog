# ArticleResource

Filament v5 resource for managing blog articles. Follows the "resource directory" convention: `ArticleResource` itself is a thin coordinator that delegates form/table definition to dedicated classes, rather than defining `form()`/`table()` inline.

## File map

| Role | Path |
|---|---|
| Resource | `app/Filament/Resources/Articles/ArticleResource.php` |
| Form schema | `app/Filament/Resources/Articles/Schemas/ArticleForm.php` |
| Infolist schema (view) | `app/Filament/Resources/Articles/Schemas/ArticleInfolist.php` |
| Table schema | `app/Filament/Resources/Articles/Tables/ArticlesTable.php` |
| List page | `app/Filament/Resources/Articles/Pages/ListArticles.php` |
| Create page | `app/Filament/Resources/Articles/Pages/CreateArticle.php` |
| View page | `app/Filament/Resources/Articles/Pages/ViewArticle.php` |
| Edit page | `app/Filament/Resources/Articles/Pages/EditArticle.php` |
| Reusable content entry (view) | `app/Filament/Support/ArticleContentEntry.php` |
| Reusable TLDR entry (view) | `app/Filament/Support/ArticleTldrEntry.php` |
| Content preview Blade view | `resources/views/filament/infolists/article-content.blade.php` |
| TLDR preview Blade view | `resources/views/filament/infolists/article-tldr.blade.php` |
| Shared preview stylesheet | `resources/views/filament/infolists/article-content-styles.blade.php` |
| Model | `app/Models/Article.php` |
| Related models | `app/Models/Category.php`, `app/Models/Tag.php` |
| Status enum | `app/Enums/ArticleStatus.php` |
| Image variant enum | `app/Enums/ImageVariant.php` |
| Reusable slug field | `app/Filament/Support/SlugInput.php` |
| Reusable image block | `app/Filament/Support/ImageBlock.php` |
| Reusable FAQ block | `app/Filament/Support/FaqBlock.php` |
| Reusable product card block | `app/Filament/Support/ProductCardBlock.php` |
| Product ranking syncer | `app/Support/Products/ArticleProductSyncer.php` |
| Shared article rich editor | `app/Filament/Support/ArticleRichEditor.php` |
| Affiliate link editor plugin | `app/Filament/Support/AffiliateLinkPlugin.php`, `app/Filament/Support/AffiliateLinkAction.php` |
| Rich content display renderer | `app/Filament/Support/ArticleRichContent.php` |
| Affiliate placement syncer | `app/Support/AffiliateLinks/ArticleAffiliateLinkSyncer.php` |
| Image pipeline orchestrator | `app/Support/Images/ArticleImageProcessor.php` |
| WebP conversion | `app/Support/Images/ImageConverter.php` |
| Configurable image sizes | `app/Support/Images/ImageSizeSettings.php` |
| Ranking direction enum | `app/Enums/RankingOrder.php` |
| Card override mode enum | `app/Enums/ProductCardOverride.php` |
| Schema migrations | `database/migrations/2026_07_17_153849_create_articles_table.php`, `database/migrations/2026_07_29_133726_add_tldr_to_articles_table.php`, `database/migrations/2026_07_29_145358_add_ranking_order_to_articles_table.php`, `database/migrations/2026_07_29_145356_create_article_product_table.php` |

There are **no RelationManagers**. The `categories`/`tags` many-to-many relations are managed inline on the article form via relationship `Select` fields with `createOptionForm()`, rather than dedicated RelationManager pages.

## Navigation & pages

```php
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema { return ArticleForm::configure($schema); }
    public static function table(Table $table): Table   { return ArticlesTable::configure($table); }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'view'   => ViewArticle::route('/{record}'),
            'edit'   => EditArticle::route('/{record}/edit'),
        ];
    }
}
```

Four routes. The `view` route is a read-only, reader-facing preview of the article (see [Infolist / View schema](#infolist--view-schema-articleinfolist)) — reachable via the `ViewAction` on both the table row and the Edit page header. Navigation label/slug default from the model name; only the icon is customized.

## Form schema (`ArticleForm`)

Five `Section`s, each `->columnSpanFull()`.

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

### Section "TLDR"

```php
RichEditor::make('tldr')
    ->hiddenLabel()
    ->columnSpanFull()
    ->helperText('Optional short summary shown above the article.'),
```

An optional one-paragraph summary of the article, stored in its own nullable `tldr` column. Two deliberate choices:

- **It is a separate column, not a `content` block type.** The TLDR is structurally distinct from the body — there is exactly zero or one per article and it always renders first — so modelling it as a block (which editors could add multiple times, or reorder into the middle of the body) would be wrong. It also lets the public site query the summary without parsing the block JSON.
- **It uses a plain `RichEditor`, not `ArticleRichEditor`.** The shared factory exists to attach the affiliate link plugin; affiliate placements are a body-only feature, so the TLDR gets the stock toolbar with no "Insert affiliate link" button. Consequently `ArticleAffiliateLinkSyncer` still scans **`content` only** — a `/go/` href hand-pasted into the TLDR will *not* be attached to the article in the `affiliate_link_article` pivot and will not appear in placement tracking. (It is still rendered with `rel="sponsored nofollow"`, because the view goes through `ArticleRichContent::renderer()` like every other rich text surface.)

`RichEditor` stores raw HTML by default, so `tldr` is a plain nullable `text` column with **no cast** on the model.

### Section "Content"

```php
Select::make('ranking_order')
    ->label('Product card numbering')
    ->options(RankingOrder::class)
    ->default(RankingOrder::Descending)
    ->required(),

Builder::make('content')
    ->blocks([
        Block::make('richText')
            ->label('Rich text')
            ->icon(Heroicon::Bars3BottomLeft)
            ->schema([
                ArticleRichEditor::make('content')->hiddenLabel()->required(),
            ]),
        ImageBlock::make(),
        FaqBlock::make(),
        ProductCardBlock::make(),
    ])
    ->reorderableWithButtons()
    ->collapsible()
    ->addActionLabel('Add block'),
```

A Filament `Builder` field models the article body as an ordered array of typed blocks (`richText`, `image`, `faq`, `productCard`), persisted as JSON in the `content` column (cast to `array` on the model). Editors can reorder, collapse, and add blocks freely — this is the CMS-style content editor for the article.

All rich text HTML in articles (the `richText` block and the FAQ answers) is edited through `ArticleRichEditor::make()`, a shared factory that returns a `RichEditor` with the affiliate link plugin attached and its "Insert affiliate link" toolbar button enabled — see [AffiliateLinkResource.md](AffiliateLinkResource.md#insertion-ux-the-rich-editor-plugin) for the plugin's mechanics.

`ranking_order` sits in this section rather than with the article metadata because it numbers the product cards in the builder directly below it. See [Product card blocks](#product-card-blocks).

### Product card blocks

`ProductCardBlock::make()` embeds a catalog `Product` into the article — the mechanism behind "TOP 10" rankings, where cards are interleaved with prose. The block is fully documented in [ProductResource.md](ProductResource.md#product-cards-in-articles); what matters from the article side:

- **The block stores no rank.** A card's number is derived from its position among the article's `productCard` blocks, with `articles.ranking_order` (`RankingOrder::Ascending`/`Descending`, default descending for a countdown) setting the direction. Deleting or reordering a card therefore cannot leave a gap or a duplicate — there is no stored number to go stale.
- `ArticleProductSyncer::ranksFor()` is the single source of truth for that calculation. Both the syncer and the content Blade view call it, so the number printed on a card and the rank stored on the `article_product` pivot cannot disagree.
- Card display data (image, price, rating, brand, ingredients, form, composition) is pulled **live** from the product on render. The only per-article state stored in the block is the product reference plus two override choices — retailer links and description — both using `ProductCardOverride`. Editorial commentary goes in ordinary rich text blocks around the card rather than inside it.
- The block header reads `Product card — {name}`, so the article's structure stays readable when every block is collapsed, and the block body shows a thumbnail preview of the selected product for visual reordering. The preview's product name links to that product's edit page in a **new tab**, so correcting a catalog detail never discards the article's unsaved Builder state.

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

## Infolist / View schema (`ArticleInfolist`)

The `view` page (`ViewArticle` extends `ViewRecord`) renders a read-only, reader-facing preview of the article instead of the form. `ViewArticle` delegates to `ArticleInfolist::configure()` from its `infolist()` method — the same thin-coordinator split used for the form. It also declares `getHeaderActions(): [EditAction::make()]` explicitly.

Five `Section`s parallel the form: **Article** (title as a large bold `TextEntry`, `status` badge via the enum, `published_at` dateTime), **Taxonomy** (`categories.name`/`tags.name` badges), **TLDR** (the summary, below), **Content** (the block preview, below), and **SEO** (collapsed `meta_title`/`meta_description`). Entries use a `->placeholder('—')` for empty values.

Note that the infolist order differs from the form: **Taxonomy sits before TLDR/Content here**, so the reading preview keeps the two long-form blocks adjacent at the bottom of the page.

### Rendering the `tldr` summary

```php
// app/Filament/Support/ArticleTldrEntry.php
class ArticleTldrEntry extends Entry
{
    protected string $view = 'filament.infolists.article-tldr';
}
```

The TLDR is rendered by a custom entry rather than `TextEntry::make('tldr')->html()` for two reasons: it needs the same `.acp-richtext` preview typography as the content blocks (see [Styling](#rendering-the-content-builder-blocks) below), and the block must stay visible when the field is empty. The Blade view renders `ArticleRichContent::renderer($getState())` when the value is `filled()`, and otherwise falls back to:

```blade
<p class="acp-empty">No TLDR has been written for this article.</p>
```

A `->placeholder()` would not do — Filament placeholders replace the entry's value, but the surrounding markup and empty-state copy here are deliberate: the section always renders so an editor can see at a glance that a summary is still missing.

### Rendering the `content` Builder blocks

Filament has **no built-in Builder infolist entry**, so the JSON block array is rendered by a custom entry plus a Blade view:

```php
// app/Filament/Support/ArticleContentEntry.php
class ArticleContentEntry extends Entry
{
    protected string $view = 'filament.infolists.article-content';
}
```

The Blade view iterates `$getState()` and renders each block type as it will appear to the end user:

- `richText` and `faq` answers — rendered through `ArticleRichContent::renderer($html)`, a thin wrapper around `Filament\Forms\Components\RichEditor\RichContentRenderer` that outputs (and sanitizes) the stored RichEditor HTML and centrally injects `rel="sponsored nofollow"` on every affiliate `/go/` link (the `rel` attribute is never stored in content — see [AffiliateLinkResource.md](AffiliateLinkResource.md#central-relsponsored-nofollow-at-render-time)).
- `image` — a `<figure>` whose `src` is the **`-mobile` variant** derived from the stored Original path (`Str::replaceLast('.webp', ImageVariant::Mobile->fileSuffix().'.webp', $path)`), served via `Storage::disk('public')->url()`, plus alt text and an optional `<figcaption>`.
- `faq` — a standout heading followed by a `<details>`/`<summary>` accordion per question/answer pair.
- `productCard` — a rank badge, the product's `-mobile` image, name, brand, a facts list (rating, pack price, per-dose price, doses, form, composition, primary ingredient), ingredient pills, the description, and buy buttons pointing at `/go/{slug}`. Ranks come from `ArticleProductSyncer::ranksFor()`, the same helper the syncer uses. Products are loaded once up front with `Product::with(['brand', 'affiliateLinks'])->findMany(...)` so a ten-card ranking does not N+1, and a card whose product has been deleted is skipped entirely rather than half-rendered.

Every array access is null-guarded (`?? ''`) because `content` is user-authored JSON; an empty `content` shows a fallback message. The custom entry lives in `app/Filament/Support/` alongside the form-side reusable components (`SlugInput`, `ImageBlock`, `FaqBlock`).

**Styling.** The admin panel does **not** ship the Tailwind Typography plugin, so `prose` classes are no-ops and Preflight would otherwise leave rich-text HTML unstyled (flattened headings/lists). A self-contained `<style>` block (keyed off `.article-content-preview`, with `.dark` overrides) restores rich-text typography, adds inter-block padding + a 1px separator, and styles the FAQ accordion. This keeps the feature dependency-free — no new npm package, no Filament custom-theme build step.

That stylesheet lives in its own partial, `resources/views/filament/infolists/article-content-styles.blade.php`, which both the content and TLDR views `@include`. The partial wraps everything in a once-directive, so it emits a single copy per page no matter how many previews render — and neither view depends on the other having rendered first.

> Careful with Blade in that file: writing a directive name such as `@`once inside a CSS comment makes Blade compile it as a real directive and the view dies with a `ParseError`. Escape it or reword.

## Table (`ArticlesTable`)

Columns: `id` (sortable), `title` (searchable, sortable, `->url()` linking to the **edit** page — the row title goes to Edit, while the read-only View page is reached only via the `ViewAction`), `slug` (searchable, hidden-by-default toggle), `status` (badge, colored/labeled via the `ArticleStatus` enum), `published_at` (dateTime, sortable), `categories.name` (badge, relationship column), `tags.name` (badge, hidden-by-default toggle), `created_at`/`updated_at` (dateTime, sortable, hidden-by-default toggle).

`->defaultSort('created_at', 'desc')`.

Filters:
- `SelectFilter::make('status')->options(ArticleStatus::class)`
- `SelectFilter::make('categories')->relationship('categories', 'name')->multiple()->preload()`
- `SelectFilter::make('tags')->relationship('tags', 'name')->multiple()->preload()`

Actions: `recordActions([ViewAction::make(), EditAction::make()])`, `toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])`. The `ViewAction` opens the read-only [view page](#infolist--view-schema-articleinfolist); no other custom table actions.

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
                    ArticleRichEditor::make('answer')->required(),
                ])
                ->required()->minItems(1)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->reorderableWithButtons()
                ->addActionLabel('Add question'),
        ]);
}
```

A FAQ content block: an optional `heading` plus a `Repeater` of question/answer pairs (answers are rich text HTML via the shared `ArticleRichEditor`, so FAQ answers can also carry affiliate links), intended to render as an accordion on the frontend. `question` is `->live(onBlur: true)` so `itemLabel()` shows the question text on collapsed repeater items. The image pipeline ignores this block entirely (it only processes `type === 'image'`).

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
        app(ArticleAffiliateLinkSyncer::class)->sync($record);
        app(ArticleProductSyncer::class)->sync($record);
    }
}
```

### `EditArticle`

```php
protected bool $hasProcessedImages = false;

protected function getHeaderActions(): array
{
    return [ViewAction::make(), DeleteAction::make()];
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
        app(ArticleAffiliateLinkSyncer::class)->sync($record);
        app(ArticleProductSyncer::class)->sync($record);
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

Both hooks run the two syncers after the image processor, so they scan the final (path-rewritten) content.

`ArticleAffiliateLinkSyncer` extracts every `/go/{slug}` href from rich text blocks and FAQ answers, and syncs the `affiliate_link_article` pivot (see [AffiliateLinkResource.md](AffiliateLinkResource.md#placement-tracking)). It **also** collects the link ids carried by product cards — those are stored as ids rather than hrefs, so an href-only scan would silently miss every retailer link on every card and under-report placements. Per card it contributes the override ids when `links_mode` is `custom`, the product's own links when `inherit`, and nothing when `none`.

`ArticleProductSyncer` then syncs `article_product`, assigning each card the rank implied by its position and the article's `ranking_order`.

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
#[Fillable(['title', 'slug', 'excerpt', 'tldr', 'content', 'status', 'published_at', 'ranking_order', 'meta_title', 'meta_description'])]
class Article extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'ranking_order' => RankingOrder::class,
        ];
    }

    public function categories(): BelongsToMany     { return $this->belongsToMany(Category::class); }
    public function tags(): BelongsToMany           { return $this->belongsToMany(Tag::class); }
    public function affiliateLinks(): BelongsToMany { return $this->belongsToMany(AffiliateLink::class); }
    public function products(): BelongsToMany       { return $this->belongsToMany(Product::class)->withPivot('rank'); }
}
```

Uses PHP 8 attribute-based `#[Fillable(...)]` rather than a classic `protected $fillable` property. `content` is cast to `array` (backs the Builder JSON); `status` is cast to the `ArticleStatus` backed enum. `tldr` is deliberately **uncast** — it holds the RichEditor's raw HTML string. `categories`/`tags` are standard `belongsToMany` pivots (`article_category`, `article_tag`). `affiliateLinks` (pivot `affiliate_link_article`) and `products` (pivot `article_product`) are not edited directly — both are derived from content by their syncers on every save.

## Enums

**`ArticleStatus`** (`draft` / `published` / `scheduled`) implements `HasColor` + `HasLabel`, driving both the form's status `Select` options and the table's badge column/filter:

| Case | Label | Color |
|---|---|---|
| `Draft` | Draft | gray |
| `Published` | Published | success |
| `Scheduled` | Scheduled | warning |

**`ImageVariant`** (`original` / `desktop` / `mobile` / `thumbnail`) exposes `fileSuffix()`, used throughout the image pipeline for consistent variant filenames.

**`RankingOrder`** (`ascending` / `descending`) implements `HasLabel` and carries the ranking rule itself:

```php
public function rankFor(int $index, int $total): int
{
    return match ($this) {
        self::Ascending => $index + 1,
        self::Descending => $total - $index,
    };
}
```

Putting the calculation on the enum rather than in the syncer is what lets the Blade view compute the displayed number the same way the pivot was written.

**`ProductCardOverride`** (`inherit` / `custom` / `none`) governs how a product card field resolves against the catalog. One enum serves both `links_mode` and `description_mode`, which is why its labels read generically ("Take from the catalog", "Override for this article", "Hide on this card") and let the field label supply the context.

Because it is stored inside content JSON rather than a column it is read back as a plain string, so `ProductCardOverride::resolve()` normalises the enum, its backing value, or a missing key down to a single case. That normalisation is also wired into both selects via `->formatStateUsing()`, so a card saved before a mode field existed does not fail its `required()` rule on the next save — Filament does not back-fill `default()` for keys absent from stored block data.

## Database schema

`articles` table:

```php
$table->id();
$table->string('title');
$table->string('slug')->unique();
$table->text('excerpt')->nullable();      // fillable, not on the form
$table->text('tldr')->nullable();         // added by add_tldr_to_articles_table; RichEditor HTML, uncast
$table->json('content')->nullable();
$table->string('status')->default('draft')->index();
$table->timestamp('published_at')->nullable();
$table->string('ranking_order')->default('descending');  // added by add_ranking_order_to_articles_table
$table->string('meta_title')->nullable();
$table->text('meta_description')->nullable();
$table->timestamps();
```

`tldr` arrived later, in `2026_07_29_133726_add_tldr_to_articles_table.php` — a plain `Schema::table()` add/drop pair. The DB is PostgreSQL, so no `->after()` column positioning (that modifier is MySQL-only and is ignored here).

Plus pivot tables `article_category` and `article_tag` backing the `categories`/`tags` many-to-many relations, and `affiliate_link_article` / `article_product` backing the content-derived `affiliateLinks` and `products` relations. `article_product` carries an extra `unsignedSmallInteger('rank')->nullable()` — stored even though it is derived, so a ranking can be queried in SQL rather than by parsing content JSON.

## Notable Filament v5 patterns used here

- Resource split into dedicated `Schemas`/`Tables`/`Pages` sub-namespaces instead of one monolithic class.
- `Get`/`Set` closures for cross-field reactivity (slug generation; `published_at` visibility tied to `status`).
- `->live()` on a `Select` to drive reactive `visible()`/`required()` closures elsewhere in the form.
- Enum-driven `Select` options (`ArticleStatus::class` passed directly to `->options()`) via `HasLabel`/`HasColor`, reused identically for the table's badge column and filter.
- `Builder` field (polymorphic block repeater) for flexible CMS-style content composition, with a read-only counterpart: a custom infolist `Entry` + Blade view renders the same blocks on the view page via `RichContentRenderer` (Filament has no built-in Builder entry).
- `->suffixAction()` for inline generate-from-another-field UX.
- `->createOptionForm()` on relationship `Select`s instead of RelationManagers for lightweight inline taxonomy creation.
- Deferred two-phase image processing: uploads stage in a tmp directory; a page lifecycle hook (`afterCreate`/`afterSave`) performs the real move, variant generation, and content rewrite.
- Custom `getRedirectUrl()` override to force a full remount after server-side mutation of upload-derived state.
- `updateQuietly()` to persist processed content without re-firing model events.
- Rich editor extension via the supported plugin API (`RichContentPlugin` + `RichEditorTool` + modal `Action` + `EditorCommand`) for affiliate link insertion — no custom TipTap extensions (see [AffiliateLinkResource.md](AffiliateLinkResource.md)).
- `Block::make()->label(fn (?array $state) => ...)` for content-derived block labels — the product card resolves the selected product's name, which is why its `product_id` select is `->live(onBlur: true)`.
- `->actionSchemaModel(Model::class)` on a Builder-block select so a nested `createOptionForm()` validates against the created model's table rather than the article being edited (see [ProductResource.md](ProductResource.md#selecting-a-product-and-quick-create)).
- Deriving ordinal data from block position instead of storing it, so reordering and deletion cannot corrupt it.
