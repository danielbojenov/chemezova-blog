# Project Requirements — Vitamins, Nutrients & Supplements Blog

## 1. Overview & Goals

A content-driven blog covering vitamins, minerals, nutrients and dietary supplements. Beyond a standard blog (articles, categories, tags), the site catalogs supplements as first-class `Product` records and lets editors build rich, structured articles — including "TOP 10" comparison/ranking articles that embed product cards.

MVP framing:
- **Informational / affiliate**, not e-commerce — products link out to retailers; no cart, checkout, or orders.
- **English only** — single locale, no translation infrastructure.
- **Editorial-only ratings** — TOP 10 rankings and comparisons are authored by editors, not derived from public reviews.
- **No public accounts** — no comments, reviews, or user-submitted content in this phase.

## 2. Tech Stack

- Backend: Laravel 13, Filament 5 (admin panel), PHP 8.5
- Frontend: Blade + Alpine.js (Livewire optional — see open questions)
- Testing: Pest 4
- Tooling already in place: Laravel Boost, Pint, Larastan, Sail

## 3. Content Model

### 3.1 Article
- `title`, `slug`, `excerpt`
- `status` (draft / published / scheduled), `published_at`
- `author` (relates to `User`)
- SEO fields: meta title, meta description, OG image
- `is_top_list` flag (marks TOP10/comparison articles)
- Ordered **content blocks** (see 3.4)
- Many-to-many with `Category`
- Many-to-many with `Tag`

### 3.2 Category
- `name`, `slug`, `description`
- Broad topical grouping (e.g. Vitamins, Minerals)
- **Assumption**: flat list, no nesting. Nesting later is a safe, additive change (nullable `parent_id` migration + tree-aware admin UI; existing content and the article-category pivot are unaffected). To keep that door open, `slug` uniqueness must stay **global**, not scoped per-parent.

### 3.3 Tag
- `name`, `slug`
- Free-form, specific labels (e.g. Vitamin D, Magnesium)
- Many-to-many with `Article`

### 3.4 Product
- `name`, `slug`, `description`
- `brand` / manufacturer
- `form` (capsule, powder, liquid, gummy, etc.)
- Nutritional / dosage facts
- Images
- External retailer link(s) (affiliate) — reference the shared Affiliate Link registry (see 3.6)
- Display price (non-transactional — informational only)
- `status` (draft / published)
- Exists standalone as a browsable catalog **and** can be embedded into articles via the Product Card block

### 3.5 Article Content Blocks

Articles are built with a page-builder flow (Filament Builder field). Editors can add, reorder, and delete blocks freely, and preview the article before publishing. Block types:

| Block | Fields / behavior |
|---|---|
| Text | Rich text (WYSIWYG) |
| Image | Upload, crop, auto-convert to WebP, caption/description |
| Info Table | Structured key/value or tabular data |
| FAQ | List of question/answer pairs |
| Product Card | References an existing catalog `Product` (not free-text duplication) |
| Interactive Diagram | Placeholder requirement — shape not yet specified, see open questions |

### 3.6 Affiliate Link

A central registry of all affiliate links used across the site — both inside rich text blocks and as Product retailer links.

- `name`, `slug`, destination URL, retailer/program, `status`, notes
- **Link format**: content never embeds raw retailer URLs. Links are inserted as internal redirect URLs (`/go/{slug}`) — plain `<a>` tags, no custom editor markup. The redirect controller logs the click and 302s to the current destination.
  - Changing a destination URL in the registry propagates to all placements instantly
  - Click statistics come for free from the redirect endpoint
  - `rel="sponsored nofollow"` is applied centrally
- **Insertion UX** (in the Text block's rich editor): a custom "Insert affiliate link" toolbar button opens a modal with a searchable select over existing links, plus on-the-fly creation of a new link without leaving the editor. Implemented with Filament 5's supported rich-editor plugin APIs (`RichContentPlugin`, `RichEditorTool`, modal `Action`, `EditorCommand`) — no custom TipTap extensions required.
- **Placement tracking**: on Article save, block content is scanned for `/go/` hrefs and an `affiliate_link_article` pivot is synced. The admin resource shows, per link: which articles use it and click counts.

## 4. Admin (Filament) Requirements

- CRUD resources for Article, Category, Tag, Product, Affiliate Link
- Block-based article editor (add/reorder/delete blocks)
- Affiliate link insertion from within the rich text editor (select existing or create on the fly; see 3.6)
- Affiliate Link resource with placement ("used in articles") and click statistics
- Image upload pipeline: crop UI, automatic WebP conversion
- Article preview (view the rendered article before publish)
- Draft/publish workflow with scheduled publishing

## 5. Public Frontend Requirements

- Article listing (paginated) and detail pages
- Category and tag archive pages
- Product catalog listing and detail pages
- TOP10/comparison article rendering with embedded product cards
- Affiliate redirect endpoint (`/go/{slug}`): logs the click, 302s to the destination; returns 404/410 for missing or disabled links
- Responsive Blade + Alpine templates

## 6. SEO & Metadata

- Per-article meta title, meta description, OG image
- Clean, human-readable slugs
- XML sitemap
- Structured data (schema.org: Article, FAQ, Product) — forward-looking, not blocking MVP

## 7. Media Handling

- Image upload, cropping, and WebP conversion needed for both the Image block and Product images
- Likely requires a media library solution (e.g. Spatie Media Library) — package choice to be decided at implementation time; **adding a new dependency requires approval** per project conventions

## 8. Non-Functional Requirements

- Performance: optimized image delivery, caching for article/product pages
- Accessibility: semantic HTML, alt text on images (enforced via the Image block)
- No i18n needed now, but avoid hard-coding user-facing strings where it's cheap to avoid

## 9. Out of Scope (for now)

- Public user accounts
- Comments
- Product reviews/ratings
- Cart, checkout, orders, payments
- Multi-language content

## 10. Open Questions / Assumptions to Revisit

- Category hierarchy: flat (assumed) vs. nested?
- Interactive Diagram block: what does "interactive" mean concretely — a chart type, an embedded widget, a custom Alpine component? Needs a follow-up spec before implementation.
- Product Card block: does it always pull live data from the catalog `Product`, or snapshot values at publish time?
- Does the public frontend need Livewire, or is Blade + Alpine sufficient for the required interactivity?
- Media library package choice (e.g. Spatie Media Library vs. custom solution).
