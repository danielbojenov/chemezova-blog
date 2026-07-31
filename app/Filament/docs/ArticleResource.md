# ArticleResource

Filament v5 resource for managing blog articles. Follows the "resource directory" convention: `ArticleResource` itself is a thin coordinator that delegates form/table definition to dedicated classes, rather than defining `form()`/`table()` inline.

Many of the pieces documented here — the slug input, content builder, heading/image blocks, featured image section, rich editor and image pipeline — are shared with [PageResource.md](PageResource.md), which covers standing site content (about, contact, legal). Changes to those components affect both.

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
| Reusable content entry (view) | `app/Filament/Support/ContentEntry.php` |
| Reusable TLDR entry (view) | `app/Filament/Support/ArticleTldrEntry.php` |
| Content preview Blade view | `resources/views/filament/infolists/content.blade.php` |
| TLDR preview Blade view | `resources/views/filament/infolists/article-tldr.blade.php` |
| Shared preview stylesheet | `resources/views/filament/infolists/content-styles.blade.php` |
| Model | `app/Models/Article.php` |
| Related models | `app/Models/Category.php`, `app/Models/Tag.php` |
| Status enum | `app/Enums/ContentStatus.php` |
| Image variant enum | `app/Enums/ImageVariant.php` |
| Reusable slug field | `app/Filament/Support/SlugInput.php` |
| Reusable heading block | `app/Filament/Support/HeadingBlock.php` |
| Heading/anchor extraction | `app/Support/Articles/ArticleHeadings.php` |
| Reusable image block | `app/Filament/Support/ImageBlock.php` |
| Featured image section | `app/Filament/Support/FeaturedImageSection.php` |
| Shared tmp upload naming | `app/Support/Images/TmpUploadName.php` |
| Reusable FAQ block | `app/Filament/Support/FaqBlock.php` |
| Reusable product card block | `app/Filament/Support/ProductCardBlock.php` |
| Product ranking syncer | `app/Support/Products/ArticleProductSyncer.php` |
| Shared article rich editor | `app/Filament/Support/ContentRichEditor.php` |
| Affiliate link editor plugin | `app/Filament/Support/AffiliateLinkPlugin.php`, `app/Filament/Support/AffiliateLinkAction.php` |
| Rich content display renderer | `app/Filament/Support/RichContent.php` |
| Affiliate placement syncer | `app/Support/AffiliateLinks/ArticleAffiliateLinkSyncer.php` |
| Image pipeline orchestrator | `app/Support/Images/ContentImageProcessor.php` |
| WebP conversion | `app/Support/Images/ImageConverter.php` |
| Configurable image sizes | `app/Support/Images/ImageSizeSettings.php` |
| Ranking direction enum | `app/Enums/RankingOrder.php` |
| Card override mode enum | `app/Enums/ProductCardOverride.php` |
| Public controller | `app/Http/Controllers/ArticleController.php` |
| Public template (stand-in) | `resources/views/article.blade.php` |
| Scheduled publish command | `app/Console/Commands/PublishScheduledArticles.php` |
| Schedule registration | `routes/console.php` |
| Schema migrations | `database/migrations/2026_07_17_153849_create_articles_table.php`, `database/migrations/2026_07_29_133726_add_tldr_to_articles_table.php`, `database/migrations/2026_07_29_145358_add_ranking_order_to_articles_table.php`, `database/migrations/2026_07_29_145356_create_article_product_table.php`, `database/migrations/2026_07_30_143606_add_featured_image_to_articles_table.php` |

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

Six `Section`s, each `->columnSpanFull()`.

### Section "Article" (2 columns)

```php
TextInput::make('title')->required()->maxLength(255),
SlugInput::make('title'),
Select::make('status')
    ->options(ContentStatus::class)
    ->default(ContentStatus::Draft)
    ->required()
    ->live(),
DateTimePicker::make('published_at')
    ->visible(fn (Get $get): bool => in_array(self::status($get), [ContentStatus::Published, ContentStatus::Scheduled], true))
    ->required(fn (Get $get): bool => self::status($get) === ContentStatus::Scheduled)
    ->helperText('Leave empty when publishing to use the current time.'),
```

`status` is `->live()` so the `published_at` field's `visible()`/`required()` closures re-evaluate on every change. Both closures go through a private helper:

```php
private static function status(Get $get): ?ContentStatus
{
    $state = $get('status');

    return $state instanceof ContentStatus ? $state : ContentStatus::tryFrom((string) $state);
}
```

This exists because Filament form state for a `status` field can hold either the backing string (`'draft'`) or a `ContentStatus` enum instance depending on where the value came from (initial load vs. live update), so every comparison normalizes first. The same normalization is duplicated in `ArticleResource::fillPublishedAt()` (see below) since it operates on raw submitted `$data` rather than a `Get` closure.

`published_at` is visible for Published/Scheduled, and only *required* for Scheduled — a Published article can be saved with the field empty because the save-time hook defaults it to `now()`.

### Section "Featured image"

Contributed whole by [`FeaturedImageSection::make()`](#featuredimagesectionmake) — the article's single lead image, plus its alt text and caption.

```php
FeaturedImageSection::make(),
```

Three deliberate choices:

- **It is a section, not a `content` block type.** Same argument as the TLDR below: there is exactly zero or one featured image per article and it never belongs in the middle of the body, so a block — which editors could add twice or reorder into the body — would be the wrong shape. Storing it in a column also lets the public site select the hero/card image in SQL instead of parsing the block JSON, which is what a list of article cards actually needs.
- **The image is optional, the alt text is conditionally required.** Nothing forces an article to have a featured image, so existing articles and drafts save unchanged. But an image with no alt text is an accessibility hole, so `featured_image_alt` is `->required(fn (Get $get): bool => filled($get('featured_image')))` — the same `Get`-closure pattern `published_at` uses against `status`. Note the interaction with `BaseFileUpload::hydrateFiles()`, which drops hydrated paths whose file is missing from the disk: if the stored file disappears, the form loads with an empty upload and the alt requirement relaxes accordingly.
- **Its files live in their own subdirectory.** Everything else about the upload matches the content `ImageBlock`, but conversion targets `articles/{id}/featured/` — see [Image pipeline](#image-pipeline) for why that separation is load-bearing rather than cosmetic.

### Section "Intro"

```php
Textarea::make('excerpt')
    ->rows(2)
    ->maxLength(Article::INTRO_LIMIT)
    ->columnSpanFull()
    ->helperText('One or two sentences, shown wherever the article is listed rather than read. Left empty, a shortened TLDR stands in.'),
RichEditor::make('tldr')
    ->label('TLDR')
    ->columnSpanFull()
    ->disableToolbarButtons(['h2', 'h3'])
    ->helperText('Optional short summary shown above the article.'),
```

The two ways an article introduces itself, grouped because an author writing one is deciding about the other. They are **not** interchangeable, which is why both exist:

- **`excerpt` is for listings** — the hero block, article cards, a category index. One or two sentences, capped at `Article::INTRO_LIMIT` (255) so a layout can size for it.
- **`tldr` is for readers who have already arrived** — it renders above the article body and is free to run several sentences with emphasis and links.

`Article::intro()` is the single reader of both. It returns the excerpt verbatim when there is one; failing that it reduces the TLDR to plain text (via `App\Support\Articles\PlainText`) and shortens it to `INTRO_LIMIT` — the ellipsis counting against the limit, so a fallback lead-in is never longer than an excerpt the field would have accepted. With neither written it returns `null`, and the surface omits the lead-in rather than showing a fragment.

The 255 cap lives on the form field, not the column: `excerpt` stays a nullable `text`, so tightening or relaxing the editorial limit needs no migration.

An optional one-paragraph summary of the article, stored in its own nullable `tldr` column. Two deliberate choices:

- **It is a separate column, not a `content` block type.** The TLDR is structurally distinct from the body — there is exactly zero or one per article and it always renders first — so modelling it as a block (which editors could add multiple times, or reorder into the middle of the body) would be wrong. It also lets the public site query the summary without parsing the block JSON.
- **It uses a plain `RichEditor`, not `ContentRichEditor`.** The shared factory exists to attach the affiliate link plugin; affiliate placements are a body-only feature, so the TLDR gets the stock toolbar with no "Insert affiliate link" button. Consequently `ArticleAffiliateLinkSyncer` still scans **`content` only** — a `/go/` href hand-pasted into the TLDR will *not* be attached to the article in the `affiliate_link_article` pivot and will not appear in placement tracking. (It is still rendered with `rel="sponsored nofollow"`, because the view goes through `RichContent::renderer()` like every other rich text surface.)

- **Its toolbar has no heading buttons.** A one-paragraph summary has nothing to subdivide, so the stock toolbar is kept and `disableToolbarButtons(['h2', 'h3'])` simply subtracts — see [Heading policy](#heading-policy).

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
        HeadingBlock::make(),
        Block::make('richText')
            ->label('Rich text')
            ->icon(Heroicon::Bars3BottomLeft)
            ->schema([
                ContentRichEditor::make('content')->hiddenLabel()->required(),
            ]),
        ImageBlock::make(),
        FaqBlock::make(),
        ProductCardBlock::make(),
    ])
    ->reorderableWithButtons()
    ->collapsible()
    ->addActionLabel('Add block'),
```

A Filament `Builder` field models the article body as an ordered array of typed blocks (`h2`, `richText`, `image`, `faq`, `productCard`), persisted as JSON in the `content` column (cast to `array` on the model). Editors can reorder, collapse, and add blocks freely — this is the CMS-style content editor for the article.

All rich text HTML in articles (the `richText` block, the FAQ answers, and the product card description override) is edited through `ContentRichEditor`, a shared factory that returns a `RichEditor` with the affiliate link plugin attached and its "Insert affiliate link" toolbar button enabled — see [AffiliateLinkResource.md](AffiliateLinkResource.md#insertion-ux-the-rich-editor-plugin) for the plugin's mechanics.

### Heading blocks

`HeadingBlock::make()` is a one-field block (`text`, required, max 255) that holds a single section heading. A heading is a block rather than H2 markup typed inside a rich text block for two reasons:

- **The outline is visible while editing.** The block's `label()` closure returns `H2 — {text}` (the `HeadingBlock::LABEL_PREFIX` convention shared with `ProductCardBlock`, falling back to a bare `H2` when the field is empty), so a collapsed builder reads as the article's table of contents instead of a stack of identical "Rich text" rows, and a heading is recognisable as one rather than as body copy. The `text` input is `live(onBlur: true)` so that label tracks edits without a round trip per keystroke.
- **The outline is machine-readable.** `ArticleHeadings::extract()` reads the sections straight off the stored JSON — no HTML parsing — which is what a frontend table of contents will consume. See [Rendering](#rendering-the-content-builder-blocks).

#### Heading policy

Because H2 is a block, no editor in the app offers an H2 button; each surface gets only the headings that make sense inside it:

| Surface | Heading buttons |
| --- | --- |
| `richText` block — `ContentRichEditor::make()` | `h3`, `h4` (sub-headings within a section) |
| FAQ answer, product card description — `ContentRichEditor::withoutHeadings()` | none (prose nested in a block that already has a heading) |
| TLDR — plain `RichEditor` | none |

`ContentRichEditor` sets the whole toolbar with `toolbarButtons()` rather than `enableToolbarButtons()`: the latter only *appends* to Filament's defaults (see [AffiliateLinkResource.md](AffiliateLinkResource.md#insertion-ux-the-rich-editor-plugin)), so it can neither drop `h2` nor place `h4` next to `h3`. Both variants share one private base method, differing only in the heading group.

Articles written before this split keep whatever `<h2>` is already inside their stored rich text HTML — it still renders; there is no migration. Only *new* H2s must be blocks.

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

## Infolist / View schema (`ArticleInfolist`)

The `view` page (`ViewArticle` extends `ViewRecord`) renders a read-only, reader-facing preview of the article instead of the form. `ViewArticle` delegates to `ArticleInfolist::configure()` from its `infolist()` method — the same thin-coordinator split used for the form. Its header actions are an **Open on site** link (below) and `EditAction::make()`.

Six `Section`s parallel the form: **Article** (title as a large bold `TextEntry`, `status` badge via the enum, `published_at` dateTime), **Featured image** (below), **Taxonomy** (`categories.name`/`tags.name` badges), **Intro** (the `excerpt` and the TLDR, below), **Content** (the block preview, below), and **SEO** (collapsed `meta_title`/`meta_description`). Entries use a `->placeholder('—')` for empty values.

Note that the infolist order differs from the form: **Taxonomy sits before Intro/Content here**, so the reading preview keeps the two long-form blocks adjacent at the bottom of the page.

### Rendering the featured image

```php
ImageEntry::make('featured_image')
    ->hiddenLabel()
    ->disk('public')
    ->getStateUsing(fn (Article $record): ?string => filled($record->featured_image)
        ? ImageVariant::Mobile->pathFor($record->featured_image)
        : null)
    ->placeholder('—'),
TextEntry::make('featured_image_alt')->label('Alt text')->placeholder('—'),
TextEntry::make('featured_image_caption')->label('Caption')->placeholder('—'),
```

A stock `ImageEntry` — unlike the Builder, a single image column needs no custom entry. Two things to know:

- **It previews the `-mobile` variant, not the stored Original.** Same reasoning as the content block preview: the Original can be 2560px wide.
- **Computed state uses `->getStateUsing()`, not `->state()`.** On a schema component `state()` *writes* into the Livewire state path; the read-side "compute this entry's value" hook is `getStateUsing()`. (Table columns are the opposite — `Column::state()` there is an alias of `getStateUsing()`, which is why `ArticlesTable` below uses it.)

`ImageEntry` (and `ImageColumn`) resolve **no URL at all for a path that is missing from the disk** — they call `$storage->exists()` first and fall back to the placeholder. The custom content-block Blade view does not do this check, which is why a content image renders its `<img>` from a bare path but a featured image does not. Tests asserting on rendered image URLs must therefore put a real file on the faked disk.

### Rendering the `tldr` summary

```php
// app/Filament/Support/ArticleTldrEntry.php
class ArticleTldrEntry extends Entry
{
    protected string $view = 'filament.infolists.article-tldr';
}
```

The TLDR is rendered by a custom entry rather than `TextEntry::make('tldr')->html()` for two reasons: it needs the same `.acp-richtext` preview typography as the content blocks (see [Styling](#rendering-the-content-builder-blocks) below), and the block must stay visible when the field is empty. The Blade view renders `RichContent::renderer($getState())` when the value is `filled()`, and otherwise falls back to:

```blade
<p class="acp-empty">No TLDR has been written for this article.</p>
```

A `->placeholder()` would not do — Filament placeholders replace the entry's value, but the surrounding markup and empty-state copy here are deliberate: the section always renders so an editor can see at a glance that a summary is still missing.

### Rendering the `content` Builder blocks

Filament has **no built-in Builder infolist entry**, so the JSON block array is rendered by a custom entry plus a Blade view:

```php
// app/Filament/Support/ContentEntry.php
class ContentEntry extends Entry
{
    protected string $view = 'filament.infolists.content';
}
```

The Blade view iterates `$getState()` and renders each block type as it will appear to the end user:

- `h2` — an `<h2>` carrying an anchor id, so section links work. Ids come from `App\Support\Articles\ArticleHeadings::extract()`, which walks the content array, slugs each heading's text (`Str::slug()`, falling back to `section` when a heading is all punctuation or emoji and slugs to nothing), and suffixes repeats `-2`, `-3`, … so ids stay unique. It returns the headings keyed by block position, which is both what the view looks up while iterating and what a frontend table of contents wants via `array_values()` — one helper means the TOC's links and the rendered anchors cannot drift apart. Because the heading introduces the block below it, the CSS drops the usual inter-block separator and top padding immediately after `.acp-heading`.
- `richText` and `faq` answers — rendered through `RichContent::renderer($html)`, a thin wrapper around `Filament\Forms\Components\RichEditor\RichContentRenderer` that outputs (and sanitizes) the stored RichEditor HTML and centrally injects `rel="sponsored nofollow"` on every affiliate `/go/` link (the `rel` attribute is never stored in content — see [AffiliateLinkResource.md](AffiliateLinkResource.md#central-relsponsored-nofollow-at-render-time)).
- `image` — a `<figure>` whose `src` is the **`-mobile` variant** derived from the stored Original path (`ImageVariant::Mobile->pathFor($path)`), served via `Storage::disk('public')->url()`, plus alt text and an optional `<figcaption>`.
- `faq` — a standout heading followed by a `<details>`/`<summary>` accordion per question/answer pair.
- `productCard` — a rank badge, the product's `-mobile` image, name, brand, a facts list (rating, pack price, per-dose price, doses, form, composition, primary ingredient), ingredient pills, the description, and buy buttons pointing at `/go/{slug}`. Ranks come from `ArticleProductSyncer::ranksFor()`, the same helper the syncer uses. Products are loaded once up front with `Product::with(['brand', 'affiliateLinks'])->findMany(...)` so a ten-card ranking does not N+1, and a card whose product has been deleted is skipped entirely rather than half-rendered.

Every array access is null-guarded (`?? ''`) because `content` is user-authored JSON; an empty `content` shows a fallback message. The custom entry lives in `app/Filament/Support/` alongside the form-side reusable components (`SlugInput`, `ImageBlock`, `FaqBlock`).

**Styling.** The admin panel does **not** ship the Tailwind Typography plugin, so `prose` classes are no-ops and Preflight would otherwise leave rich-text HTML unstyled (flattened headings/lists). A self-contained `<style>` block (keyed off `.content-preview`, with `.dark` overrides) restores rich-text typography, adds inter-block padding + a 1px separator, and styles the FAQ accordion. This keeps the feature dependency-free — no new npm package, no Filament custom-theme build step.

That stylesheet lives in its own partial, `resources/views/filament/infolists/content-styles.blade.php`, which both the content and TLDR views `@include`. The partial wraps everything in a once-directive, so it emits a single copy per page no matter how many previews render — and neither view depends on the other having rendered first.

> Careful with Blade in that file: writing a directive name such as `@`once inside a CSS comment makes Blade compile it as a real directive and the view dies with a `ParseError`. Escape it or reword.

## Table (`ArticlesTable`)

Columns: `featured_image` (leading `ImageColumn`, label-less, below), `id` (sortable), `title` (searchable, sortable, `->url()` linking to the **edit** page — the row title goes to Edit, while the read-only View page is reached only via the `ViewAction`), `slug` (searchable, hidden-by-default toggle), `status` (badge, colored/labeled via the `ContentStatus` enum), `published_at` (dateTime, sortable), `categories.name` (badge, relationship column), `tags.name` (badge, hidden-by-default toggle), `created_at`/`updated_at` (dateTime, sortable, hidden-by-default toggle).

```php
ImageColumn::make('featured_image')
    ->label('')
    ->disk('public')
    ->state(fn (Article $record): ?string => filled($record->featured_image)
        ? ImageVariant::Thumbnail->pathFor($record->featured_image)
        : null),
```

Leads the row, label-less, mirroring `ProductsTable`'s image column. It renders the **`-thumbnail`** variant so a 50-row page does not pull 50 full-size Originals — the reason `ImageVariant::pathFor()` exists as a shared helper rather than an inline expression.

`->defaultSort('created_at', 'desc')`.

Filters:
- `SelectFilter::make('status')->options(ContentStatus::class)`
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
                ->disk(ContentImageProcessor::DISK)               // 'public'
                ->directory(ContentImageProcessor::TMP_DIRECTORY) // 'content/tmp'
                ->visibility('public')
                ->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file): string => self::storageFileName($file),
                ),
            TextInput::make('alt')->label('Alt text')->required()->maxLength(255),
            TextInput::make('caption')->label('Caption')->maxLength(500),
        ]);
}
```

All uploads initially land in `content/tmp/` rather than the final `articles/{id}/` directory — a new article doesn't have an ID yet at form-fill time, and existing articles shouldn't get half-processed images before the record actually saves.

### `FeaturedImageSection::make()`

```php
public static function make(): Section
{
    return Section::make('Featured image')
        ->columnSpanFull()
        ->schema([
            FileUpload::make('featured_image')
                ->hiddenLabel()->image()->imageEditor()
                ->disk(ContentImageProcessor::DISK)               // 'public'
                ->directory(ContentImageProcessor::TMP_DIRECTORY) // 'content/tmp'
                ->visibility('public')
                ->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file): string => TmpUploadName::for($file),
                ),
            TextInput::make('featured_image_alt')
                ->label('Alt text')
                ->required(fn (Get $get): bool => filled($get('featured_image')))
                ->maxLength(255),
            TextInput::make('featured_image_caption')
                ->label('Caption')
                ->maxLength(500),
        ]);
}
```

The article's lead image. Every `FileUpload` option is identical to `ImageBlock`'s except `->required()`, which is dropped — a featured image is optional. It returns a whole `Section` rather than a field or a `Block`, which is why `ArticleForm` composes it as a bare `FeaturedImageSection::make(),` alongside its inline sections.

Note it **shares `content/tmp/` with the content blocks** rather than staging in a directory of its own. The processor distinguishes the two by which attribute it read the path from, never by the tmp path, so a second staging directory would buy nothing. The directories only diverge at conversion time, where it matters.

### `TmpUploadName::for(TemporaryUploadedFile $file)`

```php
public static function for(TemporaryUploadedFile $file): string
{
    $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

    return ($baseName !== '' ? $baseName : 'image')
        .'-tmp'.Str::lower(Str::random(6))
        .'.'.$file->extension();
}
```

The stored name for any staged upload: the slugified original name plus a random `-tmp{6 chars}` marker, with the extension always derived server-side from `$file->extension()` (never trusted from the client). The marker is what `ContentImageProcessor::TMP_SUFFIX_PATTERN` matches to strip, so both article uploads that need to survive that round trip — the content blocks and the featured image — must produce it the same way. Shared by `ImageBlock` and `FeaturedImageSection`.

`ProductForm` still carries its own private copy of this logic (see [ProductResource.md](ProductResource.md)); folding it into this helper is a pending cleanup, not a deliberate split.

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
                    ContentRichEditor::withoutHeadings('answer')->required(),
                ])
                ->required()->minItems(1)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->reorderableWithButtons()
                ->addActionLabel('Add question'),
        ]);
}
```

A FAQ content block: an optional `heading` plus a `Repeater` of question/answer pairs (answers are rich text HTML via the shared `ContentRichEditor`, so FAQ answers can also carry affiliate links), intended to render as an accordion on the frontend. `question` is `->live(onBlur: true)` so `itemLabel()` shows the question text on collapsed repeater items. The image pipeline ignores this block entirely (it only processes `type === 'image'`).

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

## Public routing

### URL scheme

```php
Route::get('/articles/{article:slug}', ArticleController::class)->name('articles.show');
```

Articles live under an `/articles/` prefix rather than at the root, because the root namespace is occupied by [standing pages](PageResource.md#public-routing). `articles` is in `ReservedSlugs::all()`, so no page can ever take that segment.

**Registration order matters.** This route must stay above the page catch-alls in `routes/web.php` — `/{parent:slug}/{child:slug}` would otherwise match `/articles/best-vitamin-d` and 404 on binding rather than falling through. `tests/Feature/PublicArticleTest.php` guards this.

`Article::url()` is the canonical way to build the URL; `ArticleResource`'s **Open on site** header action and any future template links go through it rather than calling `route()` directly.

### Visibility: status is the only switch

```php
public function isPublished(): bool
{
    return $this->status === ContentStatus::Published;
}
```

Drafts and scheduled articles 404. The date deliberately plays **no part** in this check, and two guarantees are what make that safe:

1. **A scheduled article becomes Published when its date arrives** — see [Scheduled publishing](#scheduled-publishing) below.
2. **A Published article can never hold a future date** — `ArticleResource::fillPublishedAt()` pulls an empty *or future* date back to now on save.

The payoff is that every place that asks "is this live?" gives the same answer with the same query. `CategoryController` and `TagController` filter on `where('status', Published)` with no date clause, and they now agree with the article page. Before this rule they did not: an article saved as Published with a future date appeared in listings but 404'd on its own URL.

The same method drives the **Open on site** action's `->visible()`, so the button never offers a URL that would 404.

### Scheduled publishing

`Scheduled` is a real state, not a label — something has to move an article out of it:

```php
// routes/console.php
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping();
```

`App\Console\Commands\PublishScheduledArticles` flips `Scheduled → Published` for every article whose `published_at` has passed.

> **Operational requirement:** a scheduled article only goes live when this command runs, so **the Laravel scheduler must be running in any environment that serves real traffic** — `php artisan schedule:work` locally, a cron entry or the platform's scheduler in production. If it stops, scheduled articles silently never publish.

Two deliberate choices inside the command:

- **`published_at` is not rewritten.** It records when the article was *meant* to go live and is what the public site orders by; stamping it with the minute the scheduler happened to fire would corrupt that.
- **Articles are saved one at a time**, not mass-updated, so model events fire and anything that later observes a publish (cache busting, a sitemap ping) sees it.

Articles with no `published_at`, or with a future one, are left alone.

### Timezones

`config('app.timezone')` is **UTC** and stays that way — timestamps are stored as UTC instants. The panel converts for display only, via `FilamentTimezone::set(AdminPanelProvider::TIMEZONE)` in `AdminPanelProvider::boot()`. That one setting covers `DateTimePicker`, table `dateTime()` columns and infolist entries; all three fall back to `FilamentTimezone::get()`.

This is not cosmetic. Without it the picker shows a bare clock that both displays *and interprets* input as UTC, so an editor on UTC+08 scheduling something for "a minute from now" files it eight hours away and it silently never publishes. The `published_at` helper text names the zone for the same reason. `tests/Feature/PanelTimezoneTest.php` pins the local → UTC round trip.

When changing the constant, remember that existing rows are not rewritten — they are already correct UTC instants, and will simply display in the new zone.

### Publishing by hand

`fillPublishedAt()` runs from both `CreateArticle` and `EditArticle`:

| You save | `published_at` becomes |
|---|---|
| Published, date empty | now |
| Published, date in the future | now |
| Published, date in the past | left exactly as entered (back-dating works) |
| Draft or Scheduled | untouched |

The future-date case is what makes moving Scheduled → Published safe: **you never have to clear the date by hand.** Publishing early just means "publish now".

### The template is a stand-in

`resources/views/article.blade.php` currently renders the title and excerpt only, matching the same deliberate-placeholder convention as `resources/views/category.blade.php` and `resources/views/page.blade.php`. **Still to build:** the TLDR, the content blocks, the featured image, and the product cards.

Note that the admin-side content preview (`ContentEntry` and `resources/views/filament/infolists/content.blade.php`) already renders every block type — it is a panel component, not a frontend one, but it is the reference for what the public template needs to handle.

## Business logic on the Resource

```php
public static function fillPublishedAt(array $data): array
{
    $status = $data['status'] ?? null;
    $status = $status instanceof ContentStatus ? $status : ContentStatus::tryFrom((string) $status);

    if ($status !== ContentStatus::Published) {
        return $data;
    }

    $publishedAt = $data['published_at'] ?? null;

    if (empty($publishedAt) || Carbon::parse($publishedAt)->isFuture()) {
        $data['published_at'] = now();
    }

    return $data;
}
```

A static helper on `ArticleResource` itself (not the model, not a trait), called from both `CreateArticle` and `EditArticle` page hooks. It normalizes `status` (which may arrive as an enum instance or a raw string, same ambiguity as the form's `Get` closures), then pulls an empty *or future* `published_at` to `now()`. See [Publishing by hand](#publishing-by-hand) for the full table and why the future case matters. `PageResource::fillPublishedAt()` applies the identical rule.

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
        app(ContentImageProcessor::class)->process($record);
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
        $this->hasProcessedImages = app(ContentImageProcessor::class)->process($record);
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

`getRedirectUrl()` is overridden to force a full page remount after image processing. `ContentImageProcessor` rewrites the stored image paths from `content/tmp/...` to `articles/{id}/...` *after* the form has already submitted; Livewire's normal in-place state refill doesn't pick up that rewritten path for the FilePond preview, so the edit page redirects to itself to force a clean reload with the new path.

Neither page needed a change when the featured image was added: `process()` already returns a single "did anything get rewritten" boolean, so a featured-image conversion triggers the same remount as a content block conversion.

## Image pipeline

The article image workflow is a two-phase, deferred process: the `FileUpload` component only ever stages files; the actual move, resize, and cleanup happens afterward in `afterCreate`/`afterSave`.

An article has **two independent sources of images** — the `image` blocks inside its content Builder, and the single featured image column. They stage in the same tmp directory and are converted by the same processor, but land in different directories.

1. **Upload** — `ImageBlock`'s and `FeaturedImageSection`'s `FileUpload`s both store the raw file at `content/tmp/{slug}-tmp{random6}.{ext}` on the `public` disk, named by `TmpUploadName::for()`.

2. **`ContentImageProcessor::process($record)`** — called from both `afterCreate` and `afterSave`. It runs two conversion passes and returns whether *either* changed anything:
   - `convertContentBlocks()` iterates every block in `$article->content` and converts each `image` block still pointing at `content/tmp/`, into **`articles/{id}/`**.
   - `convertFeaturedImage()` does the same for `$article->featured_image`, into **`articles/{id}/featured/`**.
   - Both funnel into one private `convert()`: derive a unique base filename (strip the `-tmp{6}` suffix via `TMP_SUFFIX_PATTERN`, de-duplicate against the target directory via `uniqueBaseName()`), call `ImageConverter::convert()`, delete the tmp upload, and return the new **Original** variant path. On failure it logs a warning, returns `null`, and leaves the tmp upload untouched — non-fatal, that one image is skipped.
   - Whatever changed is written in a **single** `$article->updateQuietly([...])` carrying `content` and/or `featured_image` — quiet to avoid re-triggering model events (and with them, re-entrant processing). `updateQuietly()` also refreshes the in-memory attributes, which is what lets the cleanup below read the rewritten paths.
   - Always finishes with `cleanupOrphans($article)`.

   **`cleanupOrphans()` makes two passes**, one per directory, each deleting every file whose base name is no longer referenced — handling images removed or replaced in the Builder, and superseded featured images.

   This is exactly why the featured image gets its own subdirectory. Each pass enforces a blunt rule ("delete everything here that nothing references"), which is only safe because the passes cannot see each other's files: `Storage::files()` is **not recursive**, so the content pass never lists anything inside `featured/`, and the featured pass lists only that subdirectory. Flat storage would have made every featured image an orphan by the content pass's reckoning, and vice versa. It also removes any chance of a `uniqueBaseName()` collision between a featured image and a content image uploaded under the same filename — both keep the clean name in their own folder, with no `-1` suffix.

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

   `deleteDirectory()` *is* recursive, so this needed no change when `featured/` was added — the featured variants go with the rest.

## Model (`Article`)

```php
#[Fillable([
    'title', 'slug', 'excerpt', 'tldr', 'content',
    'featured_image', 'featured_image_alt', 'featured_image_caption',
    'status', 'published_at', 'ranking_order', 'meta_title', 'meta_description',
])]
class Article extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => ContentStatus::class,
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

Uses PHP 8 attribute-based `#[Fillable(...)]` rather than a classic `protected $fillable` property. `content` is cast to `array` (backs the Builder JSON); `status` is cast to the `ContentStatus` backed enum. `tldr` is deliberately **uncast** — it holds the RichEditor's raw HTML string. The three `featured_image*` columns are plain uncast strings: a stored path plus its alt text and caption. `categories`/`tags` are standard `belongsToMany` pivots (`article_category`, `article_tag`). `affiliateLinks` (pivot `affiliate_link_article`) and `products` (pivot `article_product`) are not edited directly — both are derived from content by their syncers on every save.

## Enums

**`ContentStatus`** (`draft` / `published` / `scheduled`) implements `HasColor` + `HasLabel`, driving both the form's status `Select` options and the table's badge column/filter. Shared with pages, which offer Draft and Published only — see [PageResource.md](PageResource.md). `Scheduled` is article-only and is resolved by [the scheduler](#scheduled-publishing) rather than being a permanent state:

| Case | Label | Color |
|---|---|---|
| `Draft` | Draft | gray |
| `Published` | Published | success |
| `Scheduled` | Scheduled | warning |

**`ImageVariant`** (`original` / `desktop` / `mobile` / `thumbnail`) exposes two methods:

- `fileSuffix()` (`''`, `-desktop`, `-mobile`, `-thumbnail`) — used throughout the image pipeline for consistent variant filenames.
- `pathFor(string $path)` — rewrites a stored path to point at this variant:

  ```php
  public function pathFor(string $path): string
  {
      return str_ends_with($path, '.webp')
          ? Str::replaceLast('.webp', $this->fileSuffix().'.webp', $path)
          : $path;
  }
  ```

  It **expects the Original path as stored on the record**, with no variant suffix — handing it an already-suffixed path would produce `-mobile-thumbnail`. Anything that is not a converted `.webp` (an upload still sitting in a tmp directory) is returned untouched. Every read-side surface that wants a smaller image goes through it: the articles table thumbnail, the featured image entry, the content block preview, and the product card preview.

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
$table->text('excerpt')->nullable();      // Intro section; capped at Article::INTRO_LIMIT on the form
$table->text('tldr')->nullable();         // added by add_tldr_to_articles_table; RichEditor HTML, uncast
$table->json('content')->nullable();
$table->string('featured_image')->nullable();             // added by add_featured_image_to_articles_table
$table->string('featured_image_alt')->nullable();
$table->string('featured_image_caption', 500)->nullable();
$table->string('status')->default('draft')->index();
$table->timestamp('published_at')->nullable();
$table->string('ranking_order')->default('descending');  // added by add_ranking_order_to_articles_table
$table->string('meta_title')->nullable();
$table->text('meta_description')->nullable();
$table->timestamps();
```

`tldr` arrived later, in `2026_07_29_133726_add_tldr_to_articles_table.php`, and the three `featured_image*` columns later still, in `2026_07_30_143606_add_featured_image_to_articles_table.php` — both plain `Schema::table()` add/drop pairs. The DB is PostgreSQL, so no `->after()` column positioning (that modifier is MySQL-only and is ignored here).

All three featured columns are nullable: the image is optional, so nothing forces existing articles or drafts to acquire one. `featured_image_caption` is capped at 500 to match the content image block's caption; the other two take the default 255. The featured path is a **column rather than a content block** so a list of article cards can select the hero image in SQL — the same argument that made `tldr` a column.

Plus pivot tables `article_category` and `article_tag` backing the `categories`/`tags` many-to-many relations, and `affiliate_link_article` / `article_product` backing the content-derived `affiliateLinks` and `products` relations. `article_product` carries an extra `unsignedSmallInteger('rank')->nullable()` — stored even though it is derived, so a ranking can be queried in SQL rather than by parsing content JSON.

## Notable Filament v5 patterns used here

- Resource split into dedicated `Schemas`/`Tables`/`Pages` sub-namespaces instead of one monolithic class.
- `Get`/`Set` closures for cross-field reactivity (slug generation; `published_at` visibility tied to `status`).
- `->live()` on a `Select` to drive reactive `visible()`/`required()` closures elsewhere in the form.
- Enum-driven `Select` options (`ContentStatus::class` passed directly to `->options()`) via `HasLabel`/`HasColor`, reused identically for the table's badge column and filter.
- `Builder` field (polymorphic block repeater) for flexible CMS-style content composition, with a read-only counterpart: a custom infolist `Entry` + Blade view renders the same blocks on the view page via `RichContentRenderer` (Filament has no built-in Builder entry).
- `->suffixAction()` for inline generate-from-another-field UX.
- `->createOptionForm()` on relationship `Select`s instead of RelationManagers for lightweight inline taxonomy creation.
- Deferred two-phase image processing: uploads stage in a tmp directory; a page lifecycle hook (`afterCreate`/`afterSave`) performs the real move, variant generation, and content rewrite.
- A dedicated single-image column (the featured image) living alongside a `Builder` that also carries images, sharing one processor and one tmp directory but converting into a separate subdirectory so each source's orphan cleanup stays independent.
- A `Section`-returning static factory (`FeaturedImageSection`) composed directly into a schema's `components()`, extending the block/field factory convention up a level to whole layout components.
- Custom `getRedirectUrl()` override to force a full remount after server-side mutation of upload-derived state.
- `updateQuietly()` to persist processed content without re-firing model events.
- Rich editor extension via the supported plugin API (`RichContentPlugin` + `RichEditorTool` + modal `Action` + `EditorCommand`) for affiliate link insertion — no custom TipTap extensions (see [AffiliateLinkResource.md](AffiliateLinkResource.md)).
- `Block::make()->label(fn (?array $state) => ...)` for content-derived block labels — the product card resolves the selected product's name, which is why its `product_id` select is `->live(onBlur: true)`.
- `->actionSchemaModel(Model::class)` on a Builder-block select so a nested `createOptionForm()` validates against the created model's table rather than the article being edited (see [ProductResource.md](ProductResource.md#selecting-a-product-and-quick-create)).
- Deriving ordinal data from block position instead of storing it, so reordering and deletion cannot corrupt it.
