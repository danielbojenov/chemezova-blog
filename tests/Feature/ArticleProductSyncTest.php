<?php

use App\Enums\AffiliateLinkStatus;
use App\Enums\ProductCardOverride;
use App\Enums\ProductStatus;
use App\Enums\RankingOrder;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use App\Support\AffiliateLinks\ArticleAffiliateLinkSyncer;
use App\Support\Products\ArticleProductSyncer;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Build a product card block for the given product.
 *
 * @param  list<int>  $linkIds
 * @return array<string, mixed>
 */
function productCard(Product $product, ProductCardOverride $mode = ProductCardOverride::Inherit, array $linkIds = []): array
{
    return [
        'type' => 'productCard',
        'data' => [
            'product_id' => $product->id,
            'links_mode' => $mode->value,
            'affiliate_link_ids' => $linkIds,
            'description_mode' => ProductCardOverride::Inherit->value,
            'description' => null,
        ],
    ];
}

/**
 * The rank stored for each product, keyed by product id and sorted by that key so
 * assertions do not depend on pivot row order.
 *
 * @return array<int, int>
 */
function ranksOf(Article $article): array
{
    $ranks = $article->products()
        ->pluck('rank', 'products.id')
        ->map(fn (mixed $rank): int => (int) $rank)
        ->all();

    ksort($ranks);

    return $ranks;
}

test('ranks count down from the number of cards by default', function () {
    [$first, $second, $third] = Product::factory()->count(3)->create();

    $article = Article::factory()->create([
        'content' => [
            productCard($first),
            ['type' => 'richText', 'data' => ['content' => '<p>Some prose between cards.</p>']],
            productCard($second),
            productCard($third),
        ],
    ]);

    app(ArticleProductSyncer::class)->sync($article);

    expect(ranksOf($article))->toBe([
        $first->id => 3,
        $second->id => 2,
        $third->id => 1,
    ]);
});

test('ascending order numbers the cards from one', function () {
    [$first, $second, $third] = Product::factory()->count(3)->create();

    $article = Article::factory()->create([
        'ranking_order' => RankingOrder::Ascending,
        'content' => [productCard($first), productCard($second), productCard($third)],
    ]);

    app(ArticleProductSyncer::class)->sync($article);

    expect(ranksOf($article))->toBe([
        $first->id => 1,
        $second->id => 2,
        $third->id => 3,
    ]);
});

test('removing the middle card renumbers the rest with no gap', function () {
    [$first, $second, $third] = Product::factory()->count(3)->create();

    $article = Article::factory()->create([
        'content' => [productCard($first), productCard($second), productCard($third)],
    ]);
    app(ArticleProductSyncer::class)->sync($article);

    $article->update(['content' => [productCard($first), productCard($third)]]);
    app(ArticleProductSyncer::class)->sync($article);

    expect(ranksOf($article))->toBe([
        $first->id => 2,
        $third->id => 1,
    ])
        ->and($article->products()->count())->toBe(2);
});

test('reordering the cards reassigns the ranks', function () {
    [$first, $second] = Product::factory()->count(2)->create();

    $article = Article::factory()->create([
        'content' => [productCard($first), productCard($second)],
    ]);
    app(ArticleProductSyncer::class)->sync($article);

    $article->update(['content' => [productCard($second), productCard($first)]]);
    app(ArticleProductSyncer::class)->sync($article);

    // The second card now leads the countdown, so it takes the top rank.
    expect(ranksOf($article))->toBe([
        $first->id => 1,
        $second->id => 2,
    ]);
});

test('a card pointing at a deleted product is skipped without consuming a rank', function () {
    [$first, $second, $third] = Product::factory()->count(3)->create();

    $article = Article::factory()->create([
        'content' => [productCard($first), productCard($second), productCard($third)],
    ]);

    $second->delete();

    app(ArticleProductSyncer::class)->sync($article);

    expect(ranksOf($article))->toBe([
        $first->id => 2,
        $third->id => 1,
    ]);
});

test('the same product listed twice is ranked once', function () {
    $product = Product::factory()->create();

    $article = Article::factory()->create([
        'content' => [productCard($product), productCard($product)],
    ]);

    app(ArticleProductSyncer::class)->sync($article);

    expect(ranksOf($article))->toBe([$product->id => 1]);
});

test('an article without product cards has no ranked products', function () {
    $article = Article::factory()->create();

    app(ArticleProductSyncer::class)->sync($article);

    expect($article->products()->count())->toBe(0);
});

test('a card inheriting links contributes the product links to the affiliate pivot', function () {
    $link = AffiliateLink::factory()->create(['status' => AffiliateLinkStatus::Active]);
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($link);

    $article = Article::factory()->create([
        'content' => [productCard($product, ProductCardOverride::Inherit)],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->all())->toBe([$link->id]);
});

test('a card overriding links contributes the override rather than the product links', function () {
    [$productLink, $overrideLink] = AffiliateLink::factory()->count(2)->create();
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($productLink);

    $article = Article::factory()->create([
        'content' => [productCard($product, ProductCardOverride::Custom, [$overrideLink->id])],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->all())->toBe([$overrideLink->id]);
});

test('a card hiding links contributes none', function () {
    $link = AffiliateLink::factory()->create();
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($link);

    $article = Article::factory()->create([
        'content' => [productCard($product, ProductCardOverride::None)],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->count())->toBe(0);
});

test('a go link written into an overriding description is tracked', function () {
    $link = AffiliateLink::factory()->create();
    $product = Product::factory()->create();

    $article = Article::factory()->create([
        'content' => [[
            'type' => 'productCard',
            'data' => [
                'product_id' => $product->id,
                'links_mode' => ProductCardOverride::None->value,
                'description_mode' => ProductCardOverride::Custom->value,
                'description' => "<p>Read the <a href=\"/go/{$link->slug}\">listing</a>.</p>",
            ],
        ]],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->all())->toBe([$link->id]);
});

test('a go link in an inherited catalog description is not an article placement', function () {
    $link = AffiliateLink::factory()->create();
    $product = Product::factory()->create([
        'description' => "<p>See the <a href=\"/go/{$link->slug}\">listing</a>.</p>",
    ]);

    $article = Article::factory()->create([
        'content' => [productCard($product, ProductCardOverride::None)],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->count())->toBe(0);
});

test('rich text and product card links are both tracked on the same article', function () {
    $prose = AffiliateLink::factory()->create();
    $cardLink = AffiliateLink::factory()->create();
    $product = Product::factory()->create();
    $product->affiliateLinks()->attach($cardLink);

    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$prose->slug}\">Buy</a></p>"]],
            productCard($product),
        ],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->sort()->values()->all())
        ->toBe(collect([$prose->id, $cardLink->id])->sort()->values()->all());
});

test('creating an article with product cards through the panel syncs the ranking', function () {
    [$first, $second] = Product::factory()->count(2)->create();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Top 2 Vitamin D Supplements',
            'slug' => 'top-2-vitamin-d-supplements',
            'ranking_order' => RankingOrder::Descending,
            'content' => [productCard($first), productCard($second)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'top-2-vitamin-d-supplements')->sole();

    expect(ranksOf($article))->toBe([
        $first->id => 2,
        $second->id => 1,
    ]);
});

test('saving an article through the panel updates the ranking', function () {
    [$first, $second] = Product::factory()->count(2)->create();
    $article = Article::factory()->create([
        'content' => [productCard($first), productCard($second)],
    ]);
    app(ArticleProductSyncer::class)->sync($article);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'ranking_order' => RankingOrder::Ascending,
            'content' => [productCard($first), productCard($second)],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ranksOf($article))->toBe([
        $first->id => 1,
        $second->id => 2,
    ]);
});

test('a card saved before the description override existed can still be saved', function () {
    $product = Product::factory()->create();
    $article = Article::factory()->create([
        'content' => [[
            'type' => 'productCard',
            // No description_mode key at all, as stored by the earlier block.
            'data' => ['product_id' => $product->id, 'links_mode' => 'inherit'],
        ]],
    ]);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ranksOf($article))->toBe([$product->id => 1]);
});

test('a product quick-created from the article editor is saved as a draft', function () {
    $product = Product::create([
        'name' => 'Quick Created Product',
        'slug' => 'quick-created-product',
        'status' => ProductStatus::Draft,
    ]);

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->price_per_dose)->toBeNull();
});
