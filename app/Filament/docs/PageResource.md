# PageResource

Filament v5 resource for managing standing site content — about, contact, legal — as opposed to the dated, categorised content in [ArticleResource.md](ArticleResource.md). The WordPress analogy is pages vs posts.

Follows the same "resource directory" convention as `ArticleResource`: `PageResource` is a thin coordinator that delegates form/table definition to dedicated classes.

## File map

| Role | Path |
|---|---|
| Resource | `app/Filament/Resources/Pages/PageResource.php` |
| Form schema | `app/Filament/Resources/Pages/Schemas/PageForm.php` |
| Infolist schema (view) | `app/Filament/Resources/Pages/Schemas/PageInfolist.php` |
| Table schema | `app/Filament/Resources/Pages/Tables/PagesTable.php` |
| List page | `app/Filament/Resources/Pages/Pages/ListPages.php` |
| Create page | `app/Filament/Resources/Pages/Pages/CreatePage.php` |
| View page | `app/Filament/Resources/Pages/Pages/ViewPage.php` |
| Edit page | `app/Filament/Resources/Pages/Pages/EditPage.php` |
| Children relation manager | `app/Filament/Resources/Pages/RelationManagers/ChildrenRelationManager.php` |
| Model | `app/Models/Page.php` |
| Factory | `database/factories/PageFactory.php` |
| Reserved slug list | `app/Support/Site/ReservedSlugs.php` |
| Public controller | `app/Http/Controllers/PageController.php` |
| Public template (stand-in) | `resources/views/page.blade.php` |
| Status enum (shared with articles) | `app/Enums/ContentStatus.php` |
| Schema migration | `database/migrations/2026_07_30_153021_create_pages_table.php` |
| Tests | `tests/Feature/PageTest.php`, `tests/Feature/PageResourceTest.php`, `tests/Feature/PublicPageTest.php` |

Shared with `ArticleResource` rather than duplicated: `SlugInput`, `ContentBuilder`, `HeadingBlock`, `ImageBlock`, `FeaturedImageSection`, `ContentRichEditor`, `ContentEntry`, `ContentImageProcessor`, `ImageConverter`, `ImageSizeSettings`, `TmpUploadName`, `ImageVariant`, `ContentStatus`.

## Navigation & pages

```php
class PageResource extends Resource
{
    protected static ?string $model = Page::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getRelations(): array { return [ChildrenRelationManager::class]; }

    public static function getPages(): array
    {
        return [
            'index'  => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'view'   => ViewPage::route('/{record}'),
            'edit'   => EditPage::route('/{record}/edit'),
        ];
    }
}
```

Sits at position 2 in the **Content** group, directly after Articles; Categories, Tags and AffiliateLinks were shifted down to 3/4/5 to make room. `$recordTitleAttribute` is set because the `AssociateAction` in the relation manager needs a label for its record select.

## Hierarchy: how pages group

Pages carry **no categories and no tags**. They group through a parent instead.

A hub — say "Legal information" — is an ordinary page with its own content that additionally lists the pages filed beneath it. It is not a separate model: making the hub a page means it has a slug, content and SEO fields of its own, and any page can be promoted to a hub or demoted without moving data.

```php
public function parent(): BelongsTo   // the hub this page sits under, or null
public function children(): HasMany   // ordered by sort_order, then title
public function isHub(): bool         // parent_id === null
```

### The tree is exactly two levels deep

Root pages, and children of root pages. Nothing further. This is what keeps the URL scheme and the hub template representable; deeper nesting is the classic WordPress footgun and is not needed here.

Enforced in two places:

1. **In the form** — the parent `Select` only offers root pages, and never the record being edited. On a page that already has children the field is disabled outright, with a helper text saying why.
2. **In the model** — `Page::guardHierarchyDepth()`, run from the `saving` event, so a seeder, console script or future bulk action cannot quietly build a deeper tree. It throws on three cases: a page parenting itself, a parent that is already a child, and a page with children being given a parent.

```php
protected function guardHierarchyDepth(): void
{
    if (! $this->isDirty('parent_id') || $this->parent_id === null) {
        return;
    }
    // ... three RuntimeException cases
}
```

The early return matters: the guard costs zero extra queries on any save that does not move the page, which is nearly all of them.

### Deleting a hub

`parent_id` is `nullOnDelete()`. Deleting a hub **promotes its children to the root** rather than cascading. A mis-click on "Legal information" must never take the privacy policy and the terms down with it.

## Form schema (`PageForm`)

Five `Section`s, each `->columnSpanFull()`.

### Section "Page" (2 columns)

| Field | Notes |
|---|---|
| `title` | required, max 255 |
| `slug` | `SlugInput::make('title')` plus a reserved-slug rule — see [Public routing](#public-routing) |
| `status` | `Select`, `->live()`, **Draft/Published only** |
| `published_at` | `DateTimePicker`, visible only when status is Published; empty or future is pulled to now on save |
| `excerpt` | `Textarea`, full width, max 500 — shown beside the title in the list on the parent page |

`status` deliberately does **not** offer `ContentStatus::Scheduled`. There is no editorial calendar for an About page; it is either live or it is not. The enum is shared with articles rather than duplicated into a near-identical `PageStatus`, so the two cases are listed explicitly:

```php
Select::make('status')
    ->options([
        ContentStatus::Draft->value => ContentStatus::Draft->getLabel(),
        ContentStatus::Published->value => ContentStatus::Published->getLabel(),
    ])
```

### Section "Parent page"

```php
Select::make('parent_id')
    ->relationship(
        'parent',
        'title',
        modifyQueryUsing: fn (EloquentBuilder $query, ?Page $record): EloquentBuilder => $query
            ->whereNull('parent_id')
            ->when($record, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot($record)),
    )
    ->searchable()
    ->preload()
    ->disabled(fn (?Page $record): bool => $record?->children()->exists() ?? false)
```

The `modifyQueryUsing` closure receives the record being edited, which is what makes "never offer myself" possible. `->disabled()` on a hub is a deliberate lock rather than a validation failure — Filament omits disabled fields from dehydration, so the existing `null` is preserved untouched.

Grouping works from **either end**: here, or by dragging an existing page into the hub's [children relation manager](#children-relation-manager).

### Section "Featured image"

`FeaturedImageSection::make()`, unchanged from articles. Optional on every page — an About page may want a hero, a privacy policy will not.

### Section "Content"

`ContentBuilder` with **three** block types:

| Block | Source |
|---|---|
| `h2` | `HeadingBlock::make()` |
| `richText` | inline `Block` wrapping `ContentRichEditor::withoutAffiliateLinks('content')` |
| `image` | `ImageBlock::make()` |

No FAQ block and no product card block. Both are article devices; a legal page has no use for either, and the product card additionally depends on the `article_product` pivot that pages have no side of.

The rich editor drops the affiliate link toolbar button for the same reason. Affiliate placements are tracked on `affiliate_link_article`; a link dropped into a page would redirect correctly through `/go/{slug}` but never appear in that link's usage reporting. The button is removed rather than left to mislead. If pages ever need affiliate links, the fix is a polymorphic pivot, not re-enabling the button.

### Section "SEO" (2 columns, collapsed by default)

`meta_title` and `meta_description`, identical to articles.

## Infolist / View schema (`PageInfolist`)

Configured on `ViewPage::infolist()`, matching `ViewArticle`. Four sections: Page (title, status, published_at, parent, child page badges, excerpt), Featured image, Content, SEO.

The content preview reuses `ContentEntry` and its Blade view rather than duplicating the renderer. Pages can only ever hold heading, rich text and image blocks, so the FAQ and product card branches are simply never reached. One line in that view is page-aware:

```php
$record = $getRecord();
$ranks = $record instanceof Article ? ArticleProductSyncer::ranksFor($record) : [];
```

`ArticleProductSyncer::ranksFor()` is typed to `Article`, so the guard is what lets one renderer serve both models.

### Header actions

```php
Action::make('openOnSite')
    ->url(fn (Page $record): string => $record->url())
    ->openUrlInNewTab()
    ->visible(fn (Page $record): bool => $record->isPublished()),
EditAction::make(),
```

"Open on site" is hidden on drafts, which have no public URL to open.

## Table (`PagesTable`)

Columns: id, title (links to edit), slug (hidden by default), parent badge, children count, status badge, published_at, timestamps (hidden by default).

Grouped by parent so children read underneath their hub:

```php
->groups([
    Group::make('parent.title')
        ->label('Parent page')
        ->getTitleFromRecordUsing(fn (Page $record): string => $record->parent_id === null
            ? 'Top-level pages'
            : $record->parent->title),
])
->defaultGroup('parent.title')
```

The group title is keyed off `parent_id` rather than the `parent` relation so root pages get a real heading instead of a blank one.

Filters: status, parent page (restricted to root pages), and an "Only top-level pages" toggle.

There is deliberately **no drag ordering on this table**. `sort_order` only means something within one parent, which is the relation manager's scope, not this one's.

## Children relation manager

`ChildrenRelationManager` renders on the hub's Edit and View pages.

```php
public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
{
    return $ownerRecord instanceof Page && $ownerRecord->isHub();
}
```

Hidden on pages that are themselves children, since the tree stops at two levels.

Children are **associated, not created** here. A page needs its content builder, featured image and SEO fields, none of which belong in a relation manager modal — editors write the page in the main resource, then group it. `AssociateAction` offers only pages that are ungrouped and not hubs themselves:

```php
->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
    ->whereNull('parent_id')
    ->whereKeyNot($this->getOwnerRecord())
    ->whereDoesntHave('children'))
```

`->reorderable('sort_order')` lives here and nowhere else. Row actions link out to the child's own edit page and dissociate it from the hub.

> **Testing note:** relation managers are lazy (`CanBeLazy`), so their title is **not** present in the initial HTML of an edit page. Assert on `canViewForRecord()` or drive the Livewire component directly; a plain `assertSee('Child pages')` on the edit page will fail.

## Public routing

### URL scheme

| Page | URL | Route name |
|---|---|---|
| Root page | `/about` | `pages.show` |
| Child page | `/legal/privacy-policy` | `pages.show.child` |

The rest of the public site, for context: `/articles/{slug}`, `/categories/{slug}`, `/tags/{slug}`, `/go/{slug}`. Every one of those prefixes is in `ReservedSlugs::all()`, so a page can never take the segment.

```php
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/{parent:slug}/{child:slug}', [PageController::class, 'showChild'])->name('pages.show.child');
```

Nested URLs group related pages visibly for both readers and search engines.

**These two routes must stay last in `routes/web.php`.** Laravel matches in registration order, and `/{page:slug}` would otherwise swallow any single-segment route declared below it — including `/admin`. The two-segment child route has the same hazard in reverse: it would match `/articles/best-vitamin-d` and 404 on binding, which is why [`articles.show`](ArticleResource.md#public-routing) is registered above it. `tests/Feature/PublicPageTest.php` carries a regression guard that hits the admin panel and the category page after the catch-all is registered.

The `child` parameter is named for the `children()` relation deliberately. A nested, custom-keyed implicit binding is **automatically scoped** to the relation matching the parameter name, so `/legal/privacy-policy` only resolves when that page really is filed under that hub — a wrong hub 404s during binding, with no manual check in the controller. (Naming it `{page:slug}` would make Laravel look for a `pages()` relation and fail with a `BadMethodCallException`.)

### Reserved slugs

Root pages are the only content living directly under `/`, so their slugs are the one place a collision with a real route can happen. `ReservedSlugs::all()` is applied as a `Rule::notIn()` on the slug field:

```php
SlugInput::make('title')
    ->rule(Rule::notIn(ReservedSlugs::all()))
    ->validationMessages(['not_in' => 'This slug is reserved for the site\'s own URLs. Pick another one.'])
```

It covers registered routes and framework paths (`admin`, `go`, `categories`, `livewire`, `storage`, `up`, …) plus segments the site will grow into (`articles`, `search`, `sitemap`, `feed`, `tags`). It is a hand-maintained list rather than a read of the route table precisely because the reserved-for-later entries are not registered yet — **add to it when a new frontend route lands.**

The rule applies to every page, not only root ones, so moving a child out of its hub can never turn a valid slug into a broken URL.

### Canonical URL and the redirect

Slugs are unique across the whole table, so `/privacy-policy` matches a page that is filed under `/legal`. Rather than serving the same content at two URLs, `PageController::show()` issues a **301 to the canonical nested URL**.

`Page::url()` is the single source of truth for which of the two route names a page uses, so callers never have to pick:

```php
public function url(): string
{
    if ($this->parent_id === null) {
        return route('pages.show', ['page' => $this]);
    }

    return route('pages.show.child', ['parent' => $this->parent, 'child' => $this]);
}
```

### Visibility

Drafts 404 on both routes, via `Page::isPublished()`. There is no scheduled state to account for.

### The template is a stand-in

`resources/views/page.blade.php` currently renders the title and excerpt only, matching the same deliberate-placeholder convention as `resources/views/category.blade.php`. It exists so authored pages resolve to a real URL. **Still to build:** rendering the `content` blocks, the featured image, and a hub's list of its child pages.

## Page lifecycle hooks

### `CreatePage` / `EditPage`

Both call `PageResource::fillPublishedAt()` in their `mutateFormDataBefore*` hook, which applies exactly the same rule as [the article version](ArticleResource.md#publishing-by-hand): publishing with an empty *or future* `published_at` pulls it to now, a past date is preserved so back-dating works, and Draft leaves it untouched.

Pages have no `Scheduled` status at all, so a future date here is a typo rather than an intent to schedule — correcting it is safe, and it keeps `Page::isPublished()` a plain status comparison. **There is no scheduler for pages**; the `articles:publish-scheduled` command only touches articles.

Both run the image pipeline afterwards:

```php
app(ContentImageProcessor::class)->process($record);
```

`EditPage` also mirrors `EditArticle`'s `getRedirectUrl()` override: when images were converted, it redirects to itself to force a full remount, because Livewire's in-place state refill leaves the FilePond preview blank after the stored paths are rewritten.

No affiliate link syncer and no product syncer — neither applies to pages.

## Image pipeline

Identical to articles, through the shared `ContentImageProcessor`. Pages participate by implementing `HasContentImages`:

```php
public function imageDirectory(): string
{
    return "pages/{$this->id}";
}
```

The processor was generalised from the former `ArticleImageProcessor` for this: it moves staged uploads out of `content/tmp`, generates WebP variants into the record's own directory, rewrites the stored paths with `updateQuietly()`, and prunes orphans. The featured image converts into a `featured/` subdirectory so each source's orphan cleanup stays independent. See [ArticleResource.md](ArticleResource.md#image-pipeline) for the full walkthrough.

Deleting a page deletes its whole image directory.

## Model (`Page`)

```php
#[Fillable([
    'parent_id', 'title', 'slug', 'excerpt', 'content',
    'featured_image', 'featured_image_alt', 'featured_image_caption',
    'status', 'published_at', 'sort_order', 'meta_title', 'meta_description',
])]
class Page extends Model implements HasContentImages
```

Casts: `content` → array, `status` → `ContentStatus`, `published_at` → datetime, `sort_order` → integer.

Public API beyond the relations: `isHub()`, `isPublished()`, `url()`, `imageDirectory()`.

`PageFactory` states: `draft()`, `published()`, `childOf(?Page $parent = null)` (creates a hub when passed nothing), `withFeaturedImage()`.

## Database schema

```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('excerpt')->nullable();
    $table->json('content')->nullable();
    $table->string('featured_image')->nullable();
    $table->string('featured_image_alt')->nullable();
    $table->string('featured_image_caption', 500)->nullable();
    $table->string('status')->default('draft')->index();
    $table->timestamp('published_at')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->string('meta_title')->nullable();
    $table->text('meta_description')->nullable();
    $table->timestamps();

    $table->index(['parent_id', 'sort_order']);
});
```

`slug` is unique **globally**, not per parent, so a page keeps its slug when it moves between hubs and a child can never collide with a root page.

## What pages deliberately do not have

Worth stating explicitly, since the resource is otherwise an `ArticleResource` lookalike:

| Article feature | Why pages skip it |
|---|---|
| Categories & tags | Pages group by parent instead |
| TLDR summary | An About page is not a long read |
| `Scheduled` status | No editorial calendar for standing content |
| `ranking_order` | No product cards to number |
| FAQ block | Article device |
| Product card block | Depends on the `article_product` pivot |
| Affiliate link editor button | Depends on the `affiliate_link_article` pivot |
| Affiliate/product syncers | Nothing to sync |

## Naming: the shared `Content*` family

Everything both content types use dropped its `Article` prefix when pages were added, so the name says what the component actually serves:

| Was | Now |
|---|---|
| `ArticleStatus` | `ContentStatus` |
| `ArticleImageProcessor` | `ContentImageProcessor` |
| `ArticleRichEditor` | `ContentRichEditor` |
| `ArticleContentEntry` | `ContentEntry` |
| `ArticleRichContent` | `RichContent` |
| `filament.infolists.article-content` | `filament.infolists.content` |
| `filament.infolists.article-content-styles` | `filament.infolists.content-styles` |
| `.article-content-preview` CSS class | `.content-preview` |

Still article-prefixed on purpose, because they are article-only: `ArticleTldrEntry`, `filament.infolists.article-tldr`, `ArticleHeadings`, `ArticleAffiliateLinkSyncer`, `ArticleProductSyncer`. The `acp-*` CSS classes inside the shared stylesheet kept their names — they are an internal detail of that one file pair, and churning them would be diff noise.
