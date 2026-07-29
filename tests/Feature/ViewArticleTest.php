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
