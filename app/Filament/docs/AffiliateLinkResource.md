# AffiliateLinkResource

Filament v5 resource for the central affiliate link registry (REQUIREMENTS.md §3.6). Content never embeds raw retailer URLs: editors place internal redirect URLs (`/go/{slug}`) as plain `<a>` tags, a public controller logs each click and 302s to the current destination, and `rel="sponsored nofollow"` is applied centrally at render time. The registry is shared — article rich text uses it today, and the upcoming `ProductResource` will reference the same records for retailer links.

Follows the same "resource directory" convention as `ArticleResource`: the resource class is a thin coordinator delegating to dedicated `Schemas`/`Tables`/`Pages` classes.

## File map

| Role | Path |
|---|---|
| Resource | `app/Filament/Resources/AffiliateLinks/AffiliateLinkResource.php` |
| Form schema | `app/Filament/Resources/AffiliateLinks/Schemas/AffiliateLinkForm.php` |
| Infolist schema (view) | `app/Filament/Resources/AffiliateLinks/Schemas/AffiliateLinkInfolist.php` |
| Table schema | `app/Filament/Resources/AffiliateLinks/Tables/AffiliateLinksTable.php` |
| Pages | `app/Filament/Resources/AffiliateLinks/Pages/{ListAffiliateLinks,CreateAffiliateLink,ViewAffiliateLink,EditAffiliateLink}.php` |
| Model | `app/Models/AffiliateLink.php` |
| Click model | `app/Models/AffiliateLinkClick.php` |
| Status enum | `app/Enums/AffiliateLinkStatus.php` |
| Redirect controller | `app/Http/Controllers/AffiliateLinkRedirectController.php` (route `GET /go/{slug}`, named `affiliate-links.redirect`) |
| Rich editor plugin | `app/Filament/Support/AffiliateLinkPlugin.php` |
| Editor modal action | `app/Filament/Support/AffiliateLinkAction.php` |
| Shared article editor factory | `app/Filament/Support/ArticleRichEditor.php` |
| Display renderer (rel injection) | `app/Filament/Support/ArticleRichContent.php` |
| Placement syncer | `app/Support/AffiliateLinks/ArticleAffiliateLinkSyncer.php` |
| Migrations | `database/migrations/2026_07_18_054114_create_affiliate_links_table.php`, `..._054115_create_affiliate_link_clicks_table.php`, `..._054117_create_affiliate_link_article_table.php` |
| Factory | `database/factories/AffiliateLinkFactory.php` |

As with the other resources, there are **no RelationManagers** — placements and click statistics are shown read-only on the View page infolist.

## The `/go/{slug}` redirect endpoint

```php
// app/Http/Controllers/AffiliateLinkRedirectController.php
$link = AffiliateLink::query()->where('slug', $slug)->first();

abort_if($link === null, 404);
abort_if($link->status === AffiliateLinkStatus::Disabled, 410);

$link->clicks()->create([
    'referer' => Str::limit((string) $request->headers->get('referer'), 2048, '') ?: null,
]);

return redirect()->away($link->url, 302);
```

Semantics:

- **302** (not 301) so destination changes in the registry propagate instantly — nothing is cacheable as permanent.
- **404** for a slug that isn't in the registry; **410 Gone** for a link that exists but is Disabled. This is why the controller does a manual lookup instead of route-model binding: binding would 404 before the disabled case could be distinguished.
- **Click logging** is one row per click in `affiliate_link_clicks` (`affiliate_link_id`, nullable `referer`, `created_at` only — the model sets `const null UPDATED_AT`). Per-click rows enable time-based statistics later, not just a lifetime total. The insert is inline (one indexed insert per redirect); a queued/batched insert is the escape hatch if traffic ever makes it noticeable.

The route is public (no auth) and registered in `routes/web.php`.

## Insertion UX (the rich editor plugin)

Every article rich text surface (the `richText` block, FAQ answers, and the product card description override) is built via `ArticleRichEditor`, whose two variants share one private base:

```php
// app/Filament/Support/ArticleRichEditor.php
return RichEditor::make($name)
    ->plugins([AffiliateLinkPlugin::make()])
    ->toolbarButtons(array_values(array_filter([
        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
        $headingButtons, // ['h3', 'h4'] for body copy, [] for FAQ answers and product descriptions
        ['alignStart', 'alignCenter', 'alignEnd'],
        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['table', 'attachFiles'],
        ['undo', 'redo'],
        ['affiliateLink'],
    ])));
```

The toolbar is declared in full rather than with `enableToolbarButtons(['affiliateLink'])`, because that method only **appends** to Filament's defaults: it cannot remove the default `h2` button (H2 is a [dedicated builder block](ArticleResource.md#heading-blocks)) and it would strand `h4` in the trailing group instead of beside `h3`. The affiliate button is still its own trailing group, as before.

`AffiliateLinkPlugin` implements `Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin`. It ships **no TipTap extensions** (both extension methods return `[]`) — an affiliate link is just the built-in Link mark with an internal href, so only the two UI hooks are used:

```php
public function getEditorTools(): array
{
    return [
        RichEditorTool::make('affiliateLink')
            ->label('Insert affiliate link')
            ->icon(Heroicon::Banknotes)
            ->action(arguments: '{ href: $getEditor().getAttributes(\'link\')?.href }')
            ->activeJsExpression('$getEditor()?.getAttributes(\'link\')?.href?.startsWith(\'/go/\')'),
    ];
}

public function getEditorActions(): array
{
    return [AffiliateLinkAction::make()];
}
```

- `->action()` with no name mounts the same-named modal `Action`, always passing the current `editorSelection` plus the JS `arguments` — here the href of the link mark under the caret, so re-opening the modal on an existing affiliate link pre-selects it.
- `->activeJsExpression()` highlights the toolbar button only when the caret sits inside a `/go/` link, not on regular links.

`AffiliateLinkAction::make()` returns the modal `Action` (mirroring the structure of Filament's built-in `LinkAction`):

- One searchable `Select` over **Active** links only (disabled links can't be newly inserted), searching name/retailer/slug, options capped at 50.
- `->fillForm()` resolves the incoming `href` argument back to a link id (`str($href)->after('/go/')` → slug lookup) for pre-selection.
- **On-the-fly creation** via `->createOptionForm([...])` (name, `SlugInput`, url, retailer, notes) + `->createOptionUsing()` which creates the link as Active. Two subtleties:
  - a non-relationship `Select` **requires** `createOptionUsing()` — there is no relationship to create through;
  - `->actionSchemaModel(AffiliateLink::class)` is set so `SlugInput`'s `unique(ignoreRecord: true)` rule resolves against `affiliate_links` instead of the Article record that owns the form.
- The action closure inserts the link through editor commands:

```php
$component->runCommands(
    [
        ...($isCollapsedSelection ? [EditorCommand::make('extendMarkRange', arguments: ['link'])] : []),
        EditorCommand::make('setLink', arguments: [['href' => $link->redirectPath()]]),
    ],
    editorSelection: $arguments['editorSelection'],
);
```

`extendMarkRange('link')` runs first when the selection is collapsed (caret only) so editing an existing link re-wraps the whole link, exactly like the built-in link tool. Only the plain href is written into content — no `rel`, no `target` (see below). **Removing** an affiliate link is intentionally not part of this modal: the built-in link tool's empty-URL path (`unsetLink`) already covers it.

## Central `rel="sponsored nofollow"` at render time

`rel` is never stored in content. `ArticleRichContent::renderer($html)` wraps `RichContentRenderer` with a `->processNodesUsing()` node processor that stamps `rel="sponsored nofollow"` on every link mark whose href starts with `/go/`:

```php
->processNodesUsing(function (object &$node): void {
    // Text nodes carry the link marks but are skipped by the node
    // walker, so mutate them through their visited parent.
    foreach ($node->content ?? [] as $child) {
        foreach ($child->marks ?? [] as $mark) {
            if (($mark->type ?? null) !== 'link') {
                continue;
            }

            if (str_starts_with((string) ($mark->attrs->href ?? ''), '/go/')) {
                $mark->attrs ??= new stdClass;
                $mark->attrs->rel = 'sponsored nofollow';
            }
        }
    }
})
```

Gotcha worth knowing: tiptap-php's `descendants()` walker **skips text nodes**, and text nodes are what carry link marks — so the processor iterates the *children* of each visited container node. Regular external links are untouched. The Article view page (`resources/views/filament/infolists/article-content.blade.php`) renders all rich text through this helper; the future public frontend should reuse it so the policy stays in one place.

## Placement tracking

`ArticleAffiliateLinkSyncer::sync(Article $article)` runs from `CreateArticle::afterCreate()` and `EditArticle::afterSave()` (after the image processor, so it reads final content):

- Collects every rich text HTML string from the content blocks — `richText` → `data.content`, `faq` → each `data.items[].answer`, and `productCard` → `data.description` **only when the card overrides the catalog description**.
- Extracts slugs with `~href="(?:https?://[^"/]+)?/go/([^"/?#]+)~` (matches both relative `/go/slug` as inserted by the editor and absolute forms).
- **Additionally collects link ids from product cards**, which reference links by id rather than by href — see below.
- Resolves everything to ids and `sync()`s the `affiliate_link_article` pivot. Unknown slugs and ids missing from the registry are silently ignored; links removed from content are detached.

Lightweight regex over the stored HTML (rather than a TipTap document walk) matches the precedent set by `ArticleImageProcessor`'s array scanning, and is safe here because content is trusted admin-authored HTML serialized by TipTap with double-quoted attributes.

**Product cards are the exception to the href scan.** A `productCard` block stores affiliate link *ids*, not hrefs, so a regex over rich text alone would miss every retailer link rendered on a card — placements would be under-reported and the "used in articles" panel would be wrong. The syncer therefore also reads each card's `links_mode` ([`ProductCardOverride`](ProductResource.md#per-card-overrides)) and contributes the card's override ids when it is `custom`, the product's own `affiliateLinks` when `inherit`, and nothing when `none`.

A card's description is scanned for hrefs, but **only when it overrides the catalog**. An inherited description belongs to the product, so links inside it are the product's own — scanning it would attribute the same link to every article featuring that product.

Products also carry links directly, via the `affiliate_link_product` pivot and the `AffiliateLink::products()` relation. Those are the defaults a card inherits; see [ProductResource.md](ProductResource.md#retailer-links-the-two-link-cap).

**Slug-edit caveat:** renaming a slug does not rewrite hrefs already stored in article content — those `/go/old-slug` links will 404 until the article is re-saved with updated links. The pivot itself is id-keyed, so placement tracking survives a rename. The form's slug helper text warns about this; blocking slug edits once placements exist is a possible follow-up.

## Form schema (`AffiliateLinkForm`)

```php
TextInput::make('name')->required()->maxLength(255),
SlugInput::make('name')
    ->helperText('Changing the slug breaks existing /go/ links in published content.'),
TextInput::make('url')
    ->label('Destination URL')
    ->url()->required()->maxLength(2048)->columnSpanFull(),
TextInput::make('retailer')->maxLength(255),
Select::make('status')
    ->options(AffiliateLinkStatus::class)
    ->default(AffiliateLinkStatus::Active)
    ->required(),
Textarea::make('notes')->rows(3)->columnSpanFull(),
```

Reuses the project-wide `SlugInput` factory (generate-from-name suffix action, `unique(ignoreRecord: true)`). Simple flat schema like `CategoryForm` — no Sections needed at this field count.

## Table (`AffiliateLinksTable`)

Columns: `name` (searchable, sortable), `slug` displayed as **Redirect URL** (`/go/{slug}`, searchable, `->copyable()` — copying yields the absolute URL via `url($record->redirectPath())` so it can be pasted anywhere), `retailer` (searchable, toggleable), `status` (badge via the enum), `clicks_count` / `articles_count` (`->counts()` aggregate columns, sortable), `url` / `created_at` / `updated_at` (hidden-by-default toggles).

Filter: `SelectFilter::make('status')->options(AffiliateLinkStatus::class)`.

Actions: `ViewAction` + `EditAction` per row, `DeleteBulkAction` in a `BulkActionGroup`.

## Infolist / View schema (`AffiliateLinkInfolist`)

Three sections:

- **Affiliate link** — name (large, bold), redirect URL (copyable, same absolute-URL copy behavior as the table), status badge, destination URL (`->url(..., shouldOpenInNewTab: true)` so editors can verify the target), retailer, notes.
- **Statistics** — total clicks and last-clicked timestamp, both computed via `->state()` closures against the `clicks()` relation (`placeholder('Never')` when unclicked).
- **Used in articles** — `TextEntry::make('articles.title')->listWithLineBreaks()->bulleted()`, with a placeholder when the link has no placements. This is the read-only replacement for a RelationManager, consistent with the rest of the panel.

## Model (`AffiliateLink`)

```php
#[Fillable(['name', 'slug', 'url', 'retailer', 'status', 'notes'])]
class AffiliateLink extends Model
{
    protected function casts(): array
    {
        return ['status' => AffiliateLinkStatus::class];
    }

    public function articles(): BelongsToMany { return $this->belongsToMany(Article::class); }
    public function clicks(): HasMany         { return $this->hasMany(AffiliateLinkClick::class); }

    public function redirectPath(): string
    {
        return "/go/{$this->slug}";
    }
}
```

`redirectPath()` is the single source of truth for the internal URL format — used by the editor action, the table/infolist copy buttons, and tests. `AffiliateLinkClick` is append-only (`const null UPDATED_AT = null`, fillable `referer` only). The `ProductResource` will later reference this same model for retailer links (its own pivot/FK — nothing here needs to change).

## Enum

**`AffiliateLinkStatus`** (`active` / `disabled`) implements `HasColor` + `HasLabel`, driving the form select, table badge, and filter:

| Case | Label | Color | Redirect behavior |
|---|---|---|---|
| `Active` | Active | success | 302 to destination |
| `Disabled` | Disabled | danger | 410 Gone |

Disabling is the kill switch for a dead retailer offer: existing placements stay in content but stop redirecting (and stop logging clicks), and the link disappears from the editor's insert modal.

## Database schema

```php
// affiliate_links
$table->id();
$table->string('name');
$table->string('slug')->unique();
$table->string('url', 2048);
$table->string('retailer')->nullable();
$table->string('status')->default('active')->index();
$table->text('notes')->nullable();
$table->timestamps();

// affiliate_link_clicks
$table->id();
$table->foreignId('affiliate_link_id')->constrained()->cascadeOnDelete();
$table->string('referer', 2048)->nullable();
$table->timestamp('created_at')->nullable()->index();

// affiliate_link_article (pivot, mirrors article_category: composite PK, no timestamps)
$table->foreignId('affiliate_link_id')->constrained()->cascadeOnDelete();
$table->foreignId('article_id')->constrained()->cascadeOnDelete();
$table->primary(['affiliate_link_id', 'article_id']);
```

## Testing notes

- `tests/Feature/AffiliateLinkRedirectTest.php` — redirect semantics: 302 + click row, referer capture, 404 unknown / 410 disabled (no click row either way), one row per click.
- `tests/Feature/AffiliateLinkSyncTest.php` — syncer unit-style tests (rich text, FAQ answers, absolute URLs, unknown slugs, detach) plus Livewire-driven `CreateArticle`/`EditArticle` tests proving the pivot syncs through the real save hooks.
- `tests/Feature/AffiliateLinkResourceTest.php` — page smoke, create/edit through the panel, duplicate-slug validation, counts and placements display.
- `tests/Feature/ViewArticleTest.php` — `/go/` links in rich text **and** FAQ answers render with `rel="sponsored nofollow"`; regular external links don't.
- `tests/Feature/AdminPanelSmokeTest.php` — the "Insert affiliate link" toolbar button renders in both the rich text block editor and the FAQ answer editor (asserted via the tool's aria-label in the page HTML).
- The modal action itself has no Livewire mount test: the RichEditor lives inside dynamic Builder/Repeater items with UUID schema-component keys, which makes mounting brittle and low-value — its logic (slug→href, placement sync, rel rendering) is covered by the tests above.
