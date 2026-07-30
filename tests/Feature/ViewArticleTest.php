<?php

use App\Enums\ProductCardOverride;
use App\Enums\ProductComposition;
use App\Enums\RankingOrder;
use App\Enums\SupplementForm;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Support\Products\ArticleProductSyncer;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the list page links the title to the edit page', function () {
    $article = Article::factory()->create();

    $this->get(ArticleResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee(ArticleResource::getUrl('edit', ['record' => $article]));
});

test('the view page loads and shows the article title and taxonomy', function () {
    $article = Article::factory()->create(['title' => 'A Very Distinct Headline']);
    $article->categories()->attach(Category::factory()->create(['name' => 'Nutrition']));
    $article->tags()->attach(Tag::factory()->create(['name' => 'Vitamins']));

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('A Very Distinct Headline')
        ->assertSee('Nutrition')
        ->assertSee('Vitamins');
});

test('rich text content is rendered on the view page', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>UNIQUE_RICH_MARKER paragraph.</p>']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('UNIQUE_RICH_MARKER');
});

test('a heading block is rendered as an anchored h2', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'h2', 'data' => ['text' => 'Unique Section Heading']],
            ['type' => 'richText', 'data' => ['content' => '<p>Body copy.</p>']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('id="unique-section-heading"', escape: false)
        ->assertSee('Unique Section Heading');
});

test('repeated heading blocks render unique anchor ids', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'h2', 'data' => ['text' => 'Dosage']],
            ['type' => 'h2', 'data' => ['text' => 'Dosage']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('id="dosage"', escape: false)
        ->assertSee('id="dosage-2"', escape: false);
});

test('a cyrillic heading block renders a transliterated anchor id', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'h2', 'data' => ['text' => 'Как принимать']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('id="kak-prinimat"', escape: false)
        ->assertSee('Как принимать');
});

test('the tldr is rendered above the article content', function () {
    $article = Article::factory()
        ->withTldr('<p>UNIQUE_TLDR_MARKER summary.</p>')
        ->create([
            'content' => [
                ['type' => 'richText', 'data' => ['content' => '<p>UNIQUE_BODY_MARKER paragraph.</p>']],
            ],
        ]);

    $response = $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('UNIQUE_TLDR_MARKER')
        ->assertSee('UNIQUE_BODY_MARKER');

    $content = (string) $response->getContent();

    expect(strpos($content, 'UNIQUE_TLDR_MARKER'))->toBeLessThan(strpos($content, 'UNIQUE_BODY_MARKER'));
});

test('an empty tldr still renders its block with a placeholder message', function () {
    $article = Article::factory()->create();

    expect($article->tldr)->toBeNull();

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('TLDR')
        ->assertSee('No TLDR has been written for this article.');
});

test('the tldr is saved from the edit form', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['tldr' => '<p>SAVED_TLDR_MARKER</p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->tldr)->toContain('SAVED_TLDR_MARKER');
});

test('the featured image alt and caption are saved from the edit form', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'featured_image_alt' => 'SAVED_FEATURED_ALT',
            'featured_image_caption' => 'SAVED_FEATURED_CAPTION',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $article->refresh();

    expect($article->featured_image_alt)->toBe('SAVED_FEATURED_ALT')
        ->and($article->featured_image_caption)->toBe('SAVED_FEATURED_CAPTION');
});

test('an article with no featured image saves without errors', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['title' => 'Still Saveable Without A Featured Image'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->featured_image)->toBeNull();
});

test('featured image alt text is required once an image is present', function () {
    Storage::fake('public');

    $article = Article::factory()->withFeaturedImage()->create();

    // FileUpload drops hydrated paths that are missing from the disk, which would
    // leave the form with no image and so no alt requirement to assert against.
    Storage::disk('public')->put($article->featured_image, 'image');

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['featured_image_alt' => null])
        ->call('save')
        ->assertHasFormErrors(['featured_image_alt' => 'required']);
});

test('the featured image is rendered on the view page', function () {
    Storage::fake('public');

    $article = Article::factory()->create([
        'featured_image' => 'articles/1/featured/hero.webp',
        'featured_image_alt' => 'DISTINCT_FEATURED_ALT',
        'featured_image_caption' => 'DISTINCT_FEATURED_CAPTION',
    ]);

    // ImageEntry resolves no URL for a path that is not on the disk.
    Storage::disk('public')->put('articles/1/featured/hero-mobile.webp', 'image');

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('Featured image')
        ->assertSee('hero-mobile.webp')
        ->assertSee('DISTINCT_FEATURED_ALT')
        ->assertSee('DISTINCT_FEATURED_CAPTION');
});

test('the articles table shows the featured image thumbnail', function () {
    Storage::fake('public');

    $article = Article::factory()->withFeaturedImage('listed-hero')->create();

    Storage::disk('public')->put("articles/{$article->id}/featured/listed-hero-thumbnail.webp", 'image');

    $this->get(ArticleResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee("articles/{$article->id}/featured/listed-hero-thumbnail.webp");
});

test('image and faq blocks are rendered on the view page', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'image', 'data' => ['image' => 'articles/1/photo.webp', 'alt' => 'DISTINCT_ALT', 'caption' => 'DISTINCT_CAPTION']],
            ['type' => 'faq', 'data' => [
                'heading' => 'DISTINCT_FAQ_HEADING',
                'items' => [
                    ['question' => 'DISTINCT_QUESTION?', 'answer' => '<p>DISTINCT_ANSWER.</p>'],
                ],
            ]],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('photo-mobile.webp')
        ->assertSee('DISTINCT_ALT')
        ->assertSee('DISTINCT_CAPTION')
        ->assertSee('DISTINCT_FAQ_HEADING')
        ->assertSee('DISTINCT_QUESTION?')
        ->assertSee('DISTINCT_ANSWER');
});

test('affiliate links render with rel sponsored nofollow in rich text and faq answers', function () {
    $link = AffiliateLink::factory()->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$link->slug}\">Buy now</a></p>"]],
            ['type' => 'faq', 'data' => [
                'heading' => 'FAQ',
                'items' => [
                    ['question' => 'Where?', 'answer' => "<p><a href=\"/go/{$link->slug}\">Right here</a></p>"],
                ],
            ]],
        ],
    ]);

    $response = $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee("href=\"/go/{$link->slug}\"", escape: false);

    expect(substr_count((string) $response->getContent(), 'rel="sponsored nofollow"'))->toBe(2);
});

test('regular external links do not get the sponsored rel attribute', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p><a href="https://example.com/study">A study</a></p>']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('href="https://example.com/study"', escape: false)
        ->assertDontSee('rel="sponsored nofollow"', escape: false);
});

/**
 * Build a product card block for the given product.
 *
 * @param  list<int>  $linkIds
 * @return array<string, mixed>
 */
function viewProductCard(
    Product $product,
    ProductCardOverride $mode = ProductCardOverride::Inherit,
    array $linkIds = [],
    ProductCardOverride $descriptionMode = ProductCardOverride::Inherit,
    ?string $description = null,
): array {
    return [
        'type' => 'productCard',
        'data' => [
            'product_id' => $product->id,
            'links_mode' => $mode->value,
            'affiliate_link_ids' => $linkIds,
            'description_mode' => $descriptionMode->value,
            'description' => $description,
        ],
    ];
}

test('a product card renders the full catalog details of its product', function () {
    $brand = Brand::factory()->create(['name' => 'DISTINCT_BRAND']);
    $primary = Ingredient::factory()->create(['name' => 'DISTINCT_PRIMARY']);
    $product = Product::factory()->priced(24.00, 60)->create([
        'name' => 'DISTINCT_PRODUCT_NAME',
        'brand_id' => $brand->id,
        'primary_ingredient_id' => $primary->id,
        'form' => SupplementForm::Softgel,
        'composition' => ProductComposition::Multivitamin,
        'rating' => 4.7,
        'image' => 'products/1/bottle.webp',
        'description' => '<p>DISTINCT_CATALOG_DESCRIPTION.</p>',
    ]);
    $product->ingredients()->attach(Ingredient::factory()->create(['name' => 'DISTINCT_INGREDIENT']));

    $article = Article::factory()->create([
        'content' => [viewProductCard($product)],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('DISTINCT_PRODUCT_NAME')
        ->assertSee('DISTINCT_BRAND')
        ->assertSee('DISTINCT_PRIMARY')
        ->assertSee('DISTINCT_INGREDIENT')
        ->assertSee('DISTINCT_CATALOG_DESCRIPTION')
        ->assertSee('Softgel')
        ->assertSee('Multivitamin')
        ->assertSee('4.7 / 5')
        ->assertSee('24.00')
        ->assertSee('0.40')
        ->assertSee('60')
        ->assertSee('bottle-mobile.webp');
});

test('a card overriding its description shows the override instead of the catalog text', function () {
    $product = Product::factory()->create(['description' => '<p>CATALOG_DESCRIPTION.</p>']);

    $article = Article::factory()->create([
        'content' => [viewProductCard(
            $product,
            descriptionMode: ProductCardOverride::Custom,
            description: '<p>OVERRIDE_DESCRIPTION.</p>',
        )],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('OVERRIDE_DESCRIPTION')
        ->assertDontSee('CATALOG_DESCRIPTION');
});

test('a card hiding its description shows neither text', function () {
    $product = Product::factory()->create(['description' => '<p>CATALOG_DESCRIPTION.</p>']);

    $article = Article::factory()->create([
        'content' => [viewProductCard($product, descriptionMode: ProductCardOverride::None)],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertDontSee('CATALOG_DESCRIPTION');
});

/**
 * The rank badge markup, precise enough not to collide with hex colours or ids
 * elsewhere in the page.
 */
function rankBadge(int $rank): string
{
    return "acp-product-rank\">#{$rank}<";
}

test('the rank shown on a card matches the rank stored on the pivot', function () {
    [$first, $second, $third] = Product::factory()->count(3)->create();

    $article = Article::factory()->create([
        'content' => [viewProductCard($first), viewProductCard($second), viewProductCard($third)],
    ]);

    $response = $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee(rankBadge(3), escape: false)
        ->assertSee(rankBadge(2), escape: false)
        ->assertSee(rankBadge(1), escape: false);

    $content = (string) $response->getContent();

    // A descending countdown: the first card carries the highest number.
    expect(strpos($content, rankBadge(3)))->toBeLessThan(strpos($content, rankBadge(1)));

    // And the rendered numbers match what the syncer stored.
    app(ArticleProductSyncer::class)->sync($article);

    expect($article->products()->pluck('rank', 'products.id')->map(intval(...))->all())
        ->toBe([$first->id => 3, $second->id => 2, $third->id => 1]);
});

test('ascending numbering is reflected on the rendered cards', function () {
    [$first, $second] = Product::factory()->count(2)->create();

    $article = Article::factory()->create([
        'ranking_order' => RankingOrder::Ascending,
        'content' => [viewProductCard($first), viewProductCard($second)],
    ]);

    $content = (string) $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->getContent();

    expect(strpos($content, rankBadge(1)))->toBeLessThan(strpos($content, rankBadge(2)));
});

test('card buy buttons point at the go redirect with a sponsored rel', function () {
    $link = AffiliateLink::factory()->create(['retailer' => 'DISTINCT_RETAILER']);
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($link);

    $article = Article::factory()->create([
        'content' => [viewProductCard($product)],
    ]);

    $response = $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('DISTINCT_RETAILER')
        ->assertSee("href=\"/go/{$link->slug}\"", escape: false);

    expect(substr_count((string) $response->getContent(), 'rel="sponsored nofollow"'))->toBe(1);
});

test('a card overriding its links shows the override instead of the product links', function () {
    $productLink = AffiliateLink::factory()->create(['retailer' => 'PRODUCT_RETAILER']);
    $overrideLink = AffiliateLink::factory()->create(['retailer' => 'OVERRIDE_RETAILER']);
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($productLink);

    $article = Article::factory()->create([
        'content' => [viewProductCard($product, ProductCardOverride::Custom, [$overrideLink->id])],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('OVERRIDE_RETAILER')
        ->assertDontSee('PRODUCT_RETAILER');
});

test('a card hiding its links shows no buy buttons', function () {
    $link = AffiliateLink::factory()->create(['retailer' => 'HIDDEN_RETAILER']);
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($link);

    $article = Article::factory()->create([
        'content' => [viewProductCard($product, ProductCardOverride::None)],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertDontSee('HIDDEN_RETAILER')
        ->assertDontSee('/go/', escape: false);
});

test('a card referencing a deleted product does not break the page', function () {
    [$kept, $removed] = Product::factory()->count(2)->create();

    $article = Article::factory()->create([
        'content' => [viewProductCard($removed), viewProductCard($kept)],
    ]);

    $removed->delete();

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee($kept->name)
        ->assertDontSee($removed->name);
});
