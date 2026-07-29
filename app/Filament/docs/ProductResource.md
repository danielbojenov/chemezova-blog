# ProductResource

Filament v5 resource for the supplement catalog, implementing [REQUIREMENTS.md](../../../REQUIREMENTS.md) §3.4. Follows the same "resource directory" convention as [ArticleResource.md](ArticleResource.md): `ProductResource` is a thin coordinator delegating form/infolist/table definitions to dedicated classes.

A `Product` is consumed two ways, and the split between them drives most of the design here:

1. **The catalog** — the `ProductResource` list page, browsed and sorted by intrinsic properties (brand, price per dose, ingredient, rating, form, composition).
2. **Ranking articles** — a `productCard` block placed anywhere in the article page builder, typically a "TOP 10". A product's *position* in such a ranking is **not** a property of the product; it belongs to the article/product pair, and lives on the `article_product` pivot. See [Product cards in articles](#product-cards-in-articles).

The public visitor-facing supplements section is not built yet; everything below is admin-side.

## File map

| Role | Path |
|---|---|
| Resource | `app/Filament/Resources/Products/ProductResource.php` |
| Form schema | `app/Filament/Resources/Products/Schemas/ProductForm.php` |
| Infolist schema (view) | `app/Filament/Resources/Products/Schemas/ProductInfolist.php` |
| Table schema (the catalog) | `app/Filament/Resources/Products/Tables/ProductsTable.php` |
| Pages | `app/Filament/Resources/Products/Pages/{List,Create,View,Edit}Product.php` |
| Brand resource | `app/Filament/Resources/Brands/` |
| Ingredient resource | `app/Filament/Resources/Ingredients/` |
| Model | `app/Models/Product.php` |
| Related models | `app/Models/Brand.php`, `app/Models/Ingredient.php`, `app/Models/AffiliateLink.php` |
| Status enum | `app/Enums/ProductStatus.php` |
| Dosage form enum | `app/Enums/SupplementForm.php` |
| Composition enum | `app/Enums/ProductComposition.php` |
| Ingredient type enum | `app/Enums/IngredientType.php` |
| Card override mode enum | `app/Enums/ProductCardOverride.php` |
| Ranking direction enum | `app/Enums/RankingOrder.php` |
| Product card block | `app/Filament/Support/ProductCardBlock.php` |
| Product card editor preview | `resources/views/filament/forms/product-card-preview.blade.php` |
| Ranking syncer | `app/Support/Products/ArticleProductSyncer.php` |
| Image pipeline | `app/Support/Images/ProductImageProcessor.php` |
| Reusable slug field | `app/Filament/Support/SlugInput.php` |
| Schema migrations | `database/migrations/2026_07_29_1453{48,50,51,53,55,56,58}_*.php` |
| Tests | `tests/Feature/Product{,Resource,ImageProcessing}Test.php`, `tests/Feature/ArticleProductSyncTest.php` |

There are **no RelationManagers**. `ingredients` and `affiliateLinks` are edited inline on the product form via relationship `Select` fields, matching the taxonomy handling on `ArticleResource`.

## Taxonomy: why models for some properties and enums for others

The catalog's descriptive properties are split deliberately:

| Property | Storage | Why |
|---|---|---|
| `brand` | `Brand` model + resource | Grows to hundreds of values; editors must add them without a deploy. One brand per product, so a `BelongsTo` — a shared tag pool could not express that. |
| `ingredients`, `primary_ingredient` | `Ingredient` model + resource | Grows continually and carries its own metadata (`type`). Many-to-many. |
| `form` | `SupplementForm` enum | ~9 values, effectively closed. |
| `composition` | `ProductComposition` enum | Exactly three values. |
| `status` | `ProductStatus` enum | Two values. |

The dividing line is **whether an editor needs to add values at runtime**. Filament's `->createOptionForm()` provides "pick from the dropdown, or create a new one inline" — but only for DB-backed relationships. A PHP enum is fixed in code, so a new value means a code change and a deploy.

`Tag` and `Category` are deliberately **not** reused for any of this. They remain article-only: reusing them would need a discriminator column just to keep the brand select scoped, would lose one-brand-per-product semantics, and would mix editorial labels with structured catalog data in one pool.

Note that `IngredientType` lives on `Ingredient`, not on `Product` — "Vitamin D" is a vitamin regardless of which product contains it. A multivitamin's types are derived from its ingredients rather than stored.

## Navigation & pages

Resources are grouped in the sidebar: `Product`, `Brand` and `Ingredient` sit under **Catalog**; `Article`, `Category`, `Tag` and `AffiliateLink` under **Content**.

```php
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;
    protected static string|UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 1;
    // form() / infolist() / table() delegate; getRelations() is empty.
}
```

Note `$navigationGroup` must be typed `string|UnitEnum|null` and `$navigationIcon` `string|BackedEnum|null` — narrowing these to `?string` breaks the parent signature.

Pages: `index`, `create`, `view` at `/{record}`, `edit` at `/{record}/edit`. `Brand` and `Ingredient` are lighter — `index`/`create`/`edit` only, no view page or infolist, mirroring `TagResource`/`CategoryResource`.

## Form schema (`ProductForm`)

Sections, all `->columnSpanFull()`: **Product** (name, `SlugInput::make('name')`, status, rating), **Classification**, **Pricing**, **Image**, **Description**, **Retailer links**, **SEO** (collapsed).

`rating` is an editorial score out of 5 stepping in **tenths** (`->step(0.1)`, so 4.6 and 4.7 are both valid), backed by a `decimal(2,1)` column. The rating filter on the catalog table uses the same step.

### Pricing: the pack price is entered, the per-dose price is derived

```php
TextInput::make('price')->label('Pack price')->numeric()->minValue(0)->step(0.01),
TextInput::make('doses_per_pack')->numeric()->minValue(1),
TextInput::make('currency')->required()->default('USD')->maxLength(3),
```

There is **no `price_per_dose` field**. It is a PostgreSQL *stored generated column* (see [Database schema](#database-schema)), so editors enter only figures they can read off the label and the derived value can never drift out of sync.

It is a generated column rather than an Eloquent accessor specifically because the catalog must **sort and filter** by it, and an accessor cannot appear in `ORDER BY` or a `WHERE` clause.

### Classification: inline creation

`brand_id` and `primary_ingredient_id` use `->relationship(...)->searchable()->preload()->createOptionForm([...])`, so an editor can add a brand or ingredient without leaving the product form — the same pattern as the taxonomy selects on `ArticleForm`. The ingredient creation form is shared between the `primary_ingredient_id` and `ingredients` selects via a private `ingredientOptionForm()` helper.

### Retailer links: the two-link cap

```php
Select::make('affiliateLinks')
    ->relationship('affiliateLinks', 'name',
        fn (Builder $query): Builder => $query->where('status', AffiliateLinkStatus::Active))
    ->multiple()->searchable()->preload()
    ->maxItems(Product::MAX_AFFILIATE_LINKS)
```

A product carries **at most two** retailer links, enforced by `->maxItems()` and expressed as `Product::MAX_AFFILIATE_LINKS` so the block-side override uses the same number. Only `Active` registry links are offered.

These are shared `AffiliateLink` records, so product links get the same `/go/{slug}` redirect, click logging and central `rel="sponsored nofollow"` as links written into article prose — see [AffiliateLinkResource.md](AffiliateLinkResource.md).

## Infolist / View schema (`ProductInfolist`)

`ViewProduct extends ViewRecord` renders a read-only preview. Sections mirror the form (Product, Image, Classification, Pricing, Description, Retailer links, **Featured in**, SEO), with `->badge()` for enum values and the `'—'` placeholder convention.

**Featured in** shows `articles.title` as badges — the reverse of the ranking pivot, answering "which articles use this product?" without a RelationManager. Its placeholder is deliberately a sentence (`This product is not used in any article yet.`) rather than a dash.

## Table (`ProductsTable`) — the catalog

This table *is* the catalog, so filtering and sorting are the point rather than an afterthought.

Columns: `image` (`ImageColumn`), `name` (searchable, sortable, `->url()` to the edit page), `slug` (hidden), `brand.name`, `primaryIngredient.name`, `form`/`composition` badges, `price` and `price_per_dose` (both `->money()` using the record's own `currency`, both sortable), `doses_per_pack` (hidden), `rating`, `status`, timestamps (hidden). `->defaultSort('created_at', 'desc')`.

Filters:
- `SelectFilter` on `status`, `form`, `composition` (enum-backed)
- `SelectFilter` relationship filters on `brand`, `primaryIngredient`, `ingredients` — all `->multiple()->preload()`
- A range `Filter` on `price_per_dose` (from/to) and a minimum `Filter` on `rating`, both built from `TextInput` schemas with a `->query()` closure

Sorting by `price_per_dose` works because the column is materialised in Postgres.

## Product cards in articles

`ProductCardBlock::make()` is added to the `Builder::make('content')->blocks([...])` list in `ArticleForm`, so a card can be dropped between prose and image blocks — essential for TOP-10 writeups where each entry needs its own commentary.

### Ranks are derived from position, never entered

The block stores **no rank number**. Rank comes from the card's ordinal position among the article's `productCard` blocks, with the direction set per article by `articles.ranking_order` (a `RankingOrder` enum, defaulting to `Descending` for a countdown). Given `n` cards and a zero-based index `i`:

```php
// app/Enums/RankingOrder.php
public function rankFor(int $index, int $total): int
{
    return match ($this) {
        self::Ascending => $index + 1,
        self::Descending => $total - $index,
    };
}
```

This is why deleting a card can never leave a gap or a duplicate — there is no stored number to go stale. The control lives in the **Content** section of `ArticleForm`, next to the builder it numbers.

`ArticleProductSyncer::ranksFor()` is the single source of truth: the syncer *and* the Blade view both call it, so the number printed on a card and the number stored on the pivot cannot drift apart. Cards pointing at a deleted product are dropped without consuming a rank, keeping the numbering contiguous.

### Selecting a product, and quick-create

```php
Select::make('product_id')
    ->options(...)->getSearchResultsUsing(...)->getOptionLabelUsing(...)
    ->actionSchemaModel(Product::class)
    ->createOptionForm([...])
    ->createOptionUsing(fn (array $data): int => Product::create([
        ...$data, 'status' => ProductStatus::Draft,
    ])->getKey()),
```

Two things to keep in mind here:

- The select **must not** use `->relationship()`. Builder block state is a plain JSON array with no Eloquent relation behind it, so options are supplied manually.
- `->actionSchemaModel(Product::class)` is required. Without it, `SlugInput`'s `->unique(ignoreRecord: true)` rule validates against the *containing* form's model — the article being edited — instead of the `products` table. `AffiliateLinkAction` solves the same problem the same way.

The quick-create form is deliberately minimal (name, slug, brand, primary ingredient) and saves the product as a **draft**, so an author never leaves the writing flow to register a product; the record is finished later in the catalog. A file upload nested inside a modal inside a Builder block is the fragile part of the image pipeline, so it is not offered here.

### Recognising a card in the editor

Two affordances keep a long ranking article navigable:

**The block header is prefixed** — `Product card — Solgar Vitamin D3` rather than a bare product name, so a collapsed article still reads as a structure. The prefix is `ProductCardBlock::LABEL_PREFIX`, and a card with no product selected (or one whose product was deleted) falls back to the prefix alone.

**The block body opens with a thumbnail preview** of the selected product — image, name, brand, rating, pack price, per-dose price, doses per pack, form, composition, primary ingredient and ingredient pills, plus a warning when the product is still a draft. This is what makes reordering possible by sight rather than by reading headers, and it saves opening the catalog in another tab to check what a card actually contains.

The product name in the preview links to `ProductResource::getUrl('edit', ...)` with `target="_blank"`, so an author can correct a price or upload a bottle shot mid-article. **The new tab is the point** — the article being edited holds unsaved Builder state, and navigating away in the same tab would lose it. The arrow glyph carries a visually-hidden label, since an icon alone is not an accessible link name.

```php
View::make('filament.forms.product-card-preview')
    ->viewData(fn (Get $get): array => [
        'product' => Product::with('brand')->find($get('product_id')),
    ]),
```

`Filament\Schemas\Components\View` renders a Blade view inside a schema, and `viewData()` accepts a closure that gets the usual utility injection — so `Get $get` reads sibling block state. This is the non-deprecated route: `Forms\Components\Placeholder` still exists but is now a thin `TextEntry` subclass marked `@deprecated`.

The preview uses the **`-thumbnail`** image variant (the article view uses `-mobile`), and the `product_id` select is plain `->live()` rather than `->live(onBlur: true)` so both the preview and the header update the moment a product is picked.

The card stores **no editorial commentary**. A verdict or "why we picked it" paragraph goes in an ordinary rich text block next to the card. The per-article description override is for restating what the product *is*, not for arguing why it was picked.

### Per-card overrides

Two fields on the card can diverge from the catalog, and both use the same three-way `ProductCardOverride` enum (`inherit` / `custom` / `none`):

| Field | `inherit` | `custom` | `none` |
|---|---|---|---|
| `links_mode` | the product's own retailer links | up to two registry links picked per article | no buy buttons |
| `description_mode` | the product's own description | a rich text override written per article | no description |

The enum's labels are deliberately generic ("Take from the catalog", "Override for this article", "Hide on this card") because the field's own label supplies the context — that is what lets one enum serve both.

Everything else on the card — image, brand, primary ingredient, ingredients, form, composition, rating, prices — is pulled **live** from the catalog and cannot be overridden, so a correction updates every article at once.

> **Legacy block data.** Filament does *not* back-fill a field's `default()` for a key missing from already-saved Builder block data. A `required()` select added later would therefore make every previously-saved card fail validation on its next save. Both mode selects guard against this with `->formatStateUsing(fn ($state) => ProductCardOverride::resolve($state)->value)`, reusing the same normalisation the syncer and the view use. This is covered by a regression test.

Because the mode is stored inside content JSON rather than a column, it is read back as a plain string; `ProductCardOverride::resolve()` normalises the enum, its backing value, or a missing key (cards saved before the field existed) down to a single case.

### Placement tracking

`ArticleAffiliateLinkSyncer` finds links by regexing `/go/` hrefs out of rich text. Product card links are stored as **ids, not hrefs**, so that scan alone would silently miss every retailer link on every product card, under-reporting placements in the AffiliateLink resource.

It therefore also collects, per card: the override ids when `links_mode` is `custom`, the product's own links when `inherit`, and nothing when `none`. Ids are filtered against the registry before syncing.

A card's description **is** scanned for `/go/` hrefs, but only when it overrides the catalog. An inherited description belongs to the product, so any links in it are the product's own rather than a placement made by this article — scanning it would attribute the same link to every article that features the product.

Both syncers run from the same page hooks, after the image processor:

```php
// CreateArticle::afterCreate() / EditArticle::afterSave()
app(ArticleImageProcessor::class)->process($record);
app(ArticleAffiliateLinkSyncer::class)->sync($record);
app(ArticleProductSyncer::class)->sync($record);
```

### Rendering on the article view page

A `@case('productCard')` arm in `resources/views/filament/infolists/article-content.blade.php` renders the card: rank badge, the image's `-mobile` variant, name, brand, a facts list (rating, pack price, per-dose price, doses per pack, form, composition, primary ingredient), the ingredient pills, the description, and buy buttons. Products are loaded once up front (`Product::with(['brand', 'affiliateLinks', 'primaryIngredient', 'ingredients'])->findMany(...)`) rather than per card, so a ten-card ranking does not N+1.

Buy buttons always point at `$link->redirectPath()` (`/go/{slug}`) so the click is logged and `rel="sponsored nofollow"` applies. A card whose product has been deleted is **skipped entirely** rather than half-rendered, matching the null-guarding style used throughout that view. Card styles (`.acp-product-*`) live in the shared `article-content-styles.blade.php` partial.

> Same Blade trap as the rest of that stylesheet: writing a directive name such as `@`once inside a CSS comment makes Blade compile it as a real directive and the view dies with a `ParseError`.

## Image pipeline

`ProductImageProcessor` is a sibling of `ArticleImageProcessor`, sharing its conventions — `products/tmp` for uploads, a `-tmpXXXXXX` filename marker stripped after conversion, `products/{id}` as the destination, and the same `ImageConverter` + `ImageSizeSettings` (the `images.sizes` setting) for WebP variant generation.

It is **not** a subclass. A product has a single `image` column rather than a walk over content blocks, so the two implementations differ enough that sharing a base class would cost more than the ~40 lines of overlap (unique base name, orphan cleanup) saves.

Wiring matches `EditArticle`, including the `getRedirectUrl()` override that remounts the edit page after conversion so FilePond sees the rewritten path instead of a blank upload field. `Product::booted()` deletes `products/{id}` when a product is deleted.

## Model (`Product`)

Conventions match the rest of `app/Models`: the `#[Fillable([...])]` attribute (not a `$fillable` property), a `casts()` method (not a property), and typed relations with `@return BelongsToMany<Model, $this>` phpdoc for larastan level 5.

```php
brand(): BelongsTo
primaryIngredient(): BelongsTo(Ingredient::class)
ingredients(): BelongsToMany
affiliateLinks(): BelongsToMany          // the product's own retailer links, max 2
articles(): BelongsToMany                // ->withPivot('rank')
```

`price_per_dose` is cast (`decimal:4`) but **not fillable** — the database writes it. `const int MAX_AFFILIATE_LINKS = 2` is the single definition of the retailer-link cap.

## Enums

| Enum | Cases | Contracts |
|---|---|---|
| `ProductStatus` | `Draft`, `Published` | `HasLabel`, `HasColor` (gray / success) |
| `SupplementForm` | `Capsule`, `Tablet`, `Softgel`, `Gummy`, `Powder`, `Liquid`, `Drops`, `Spray`, `Chewable` | `HasLabel` |
| `ProductComposition` | `Single` → "Mono-supplement", `Complex` → "2–3 components", `Multivitamin` | `HasLabel`, `HasColor` |
| `IngredientType` | `Vitamin`, `Mineral`, `AminoAcid`, `FattyAcid`, `Probiotic`, `Botanical`, `Enzyme`, `Other` | `HasLabel`, `HasColor` |
| `ProductCardOverride` | `Inherit`, `Custom`, `None` | `HasLabel` (+ `resolve()`) |
| `RankingOrder` | `Ascending`, `Descending` | `HasLabel` (+ `rankFor()`) |

Implementing `HasLabel`/`HasColor` is what lets each enum be passed directly to `->options(Enum::class)`, `->badge()` and `SelectFilter`.

## Database schema

```php
// products
$table->id();
$table->string('name');
$table->string('slug')->unique();
$table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('primary_ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
$table->text('description')->nullable();
$table->string('image')->nullable();
$table->string('form')->nullable()->index();
$table->string('composition')->nullable()->index();
$table->decimal('price', 10, 2)->nullable();
$table->unsignedInteger('doses_per_pack')->nullable();
$table->string('currency', 3)->default('USD');
$table->decimal('price_per_dose', 10, 4)
    ->nullable()
    ->storedAs('price / NULLIF(doses_per_pack, 0)');
$table->decimal('rating', 2, 1)->nullable();
$table->string('status')->default(ProductStatus::Draft->value)->index();
$table->string('meta_title')->nullable();
$table->text('meta_description')->nullable();
$table->timestamps();
```

`NULLIF(doses_per_pack, 0)` is what keeps a zero or missing dose count from raising a division error — the expression yields `null` instead.

Pivots follow the existing convention (alphabetical singular join name, composite primary key, cascade deletes, no `id`, no timestamps):

```php
// ingredient_product      ingredient_id + product_id
// affiliate_link_product  affiliate_link_id + product_id
// article_product         article_id + product_id + unsignedSmallInteger('rank')->nullable()
```

`article_product.rank` is stored even though it is derived, so a ranking can be queried in SQL rather than by parsing content JSON.

`articles.ranking_order` was added by `2026_07_29_145358_add_ranking_order_to_articles_table.php`, defaulting to `descending`.

> The database is **PostgreSQL**, so `->after()` is unavailable on `Schema::table()` — column position is not controllable and does not matter.

## Testing notes

| File | Covers |
|---|---|
| `tests/Feature/ProductTest.php` | Enum casts, slug uniqueness, relations, brand `nullOnDelete`, image-directory cleanup, and the derived `price_per_dose` — including the zero/null dose guard, that it updates when the price changes, and that it is sortable |
| `tests/Feature/ProductResourceTest.php` | Create/edit through the panel, duplicate slug rejection, the two-link cap (three rejected, two accepted), list-page linking, sorting by per-dose price, filtering by brand, and view-page contents |
| `tests/Feature/ProductImageProcessingTest.php` | Tmp upload → WebP variants, idempotency, orphan pruning on replacement, unique base names on collision, and that an unreadable upload is left in place |
| `tests/Feature/ArticleProductSyncTest.php` | The ranking rules — default countdown, ascending, **removing the middle card renumbers with no gap**, reordering, deleted products skipped, duplicates ranked once — plus all three `links_mode` behaviours, the override-only description scan, a card saved before `description_mode` existed still saving, and both panel page hooks |
| `tests/Feature/ViewArticleTest.php` | Card rendering: the full catalog detail set pulled live, rank badges in the right order and matching the pivot, `/go/` buy buttons with `rel`, link and description overrides plus suppression of each, and a deleted product degrading gracefully |
| `tests/Feature/AdminPanelSmokeTest.php` | All Product/Brand/Ingredient pages load; the article editor renders a `productCard` block with its prefixed header, its full-detail thumbnail preview, the new-tab link to the product's edit page, and the empty-preview fallback |

Testing conventions follow the rest of the suite: `Livewire::test(PageClass::class, ['record' => $model->id])` — the `livewire()` helper is not installed in this project — with `beforeEach(fn () => $this->actingAs(User::factory()->create()))`, and plain HTTP `get()` for page-load assertions.

Two assertion traps worth knowing, both hit while writing these tests:

- `expect($array)->toBe($array)` compares **key order**, so pivot-derived maps are `ksort()`ed by a helper before assertion.
- A bare `#2` matches hex colours and ids elsewhere in the page. Rank assertions use the badge markup (`acp-product-rank">#2<`) instead.

## Notable Filament v5 patterns used here

- `->maxItems()` on a relationship `Select` to cap a many-to-many at the form layer.
- `->actionSchemaModel(Model::class)` to retarget validation rules inside a `createOptionForm()` on a non-relationship select.
- `Block::make()->label(fn (?array $state) => ...)` for content-derived block labels, with the source field `->live()`.
- `Schemas\Components\View::make(...)->viewData(fn (Get $get) => ...)` to embed a live Blade preview inside a Builder block — the supported replacement for the now-deprecated `Placeholder`.
- `->money(fn (Model $record): string => $record->currency)` for per-record currency formatting.
- `Filter::make()->schema([...])->query(...)` for range filters that have no dedicated component.
- `->storedAs()` for a generated column that stays sortable and filterable, where an accessor could not.
