<?php

use App\Enums\ProductComposition;
use App\Enums\SupplementForm;
use App\Filament\Pages\ImageSettings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Ingredients\IngredientResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('article resource pages load', function () {
    $article = Article::factory()->create();

    $this->get(ArticleResource::getUrl('index'))->assertSuccessful();
    $this->get(ArticleResource::getUrl('create'))->assertSuccessful();
    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertSuccessful();
});

test('article edit page loads with image and faq blocks in the content', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
            ['type' => 'image', 'data' => ['image' => 'articles/1/photo.webp', 'alt' => 'A photo', 'caption' => 'Caption']],
            ['type' => 'faq', 'data' => [
                'heading' => 'Frequently asked questions',
                'items' => [
                    ['question' => 'What is this?', 'answer' => '<p>An article.</p>'],
                    ['question' => 'Why?', 'answer' => '<p>Because.</p>'],
                ],
            ]],
        ],
    ]);

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertSuccessful();
});

test('the article edit page loads with a product card block in the content', function () {
    $product = Product::factory()->create(['name' => 'DISTINCT_CARD_PRODUCT']);
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
            ['type' => 'productCard', 'data' => [
                'product_id' => $product->id,
                'links_mode' => 'inherit',
                'affiliate_link_ids' => [],
            ]],
        ],
    ]);

    // The block header is prefixed so collapsed blocks stay readable.
    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('Product card — DISTINCT_CARD_PRODUCT');
});

test('the product card block previews the full details of the selected product', function () {
    $brand = Brand::factory()->create(['name' => 'PREVIEW_BRAND']);
    $primary = Ingredient::factory()->create(['name' => 'PREVIEW_PRIMARY']);
    $product = Product::factory()->priced(24.00, 60)->create([
        'name' => 'PREVIEW_PRODUCT',
        'brand_id' => $brand->id,
        'primary_ingredient_id' => $primary->id,
        'form' => SupplementForm::Softgel,
        'composition' => ProductComposition::Multivitamin,
        'rating' => 4.7,
        'image' => 'products/1/bottle.webp',
    ]);
    $product->ingredients()->attach(Ingredient::factory()->create(['name' => 'PREVIEW_INGREDIENT']));

    $article = Article::factory()->create([
        'content' => [
            ['type' => 'productCard', 'data' => [
                'product_id' => $product->id,
                'links_mode' => 'inherit',
                'affiliate_link_ids' => [],
            ]],
        ],
    ]);

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('PREVIEW_BRAND')
        ->assertSee('PREVIEW_PRIMARY')
        ->assertSee('PREVIEW_INGREDIENT')
        ->assertSee('Softgel')
        ->assertSee('Multivitamin')
        ->assertSee('4.7 / 5')
        ->assertSee('0.40/dose')
        ->assertSee('60 doses')
        // The small -thumbnail variant, not the full-size original.
        ->assertSee('bottle-thumbnail.webp');
});

test('the product card preview links to the product edit page in a new tab', function () {
    $product = Product::factory()->create(['name' => 'LINKED_PRODUCT']);
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'productCard', 'data' => [
                'product_id' => $product->id,
                'links_mode' => 'inherit',
                'affiliate_link_ids' => [],
            ]],
        ],
    ]);

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee(ProductResource::getUrl('edit', ['record' => $product]), escape: false)
        // A new tab, so an unsaved article is never navigated away from.
        ->assertSee('target="_blank"', escape: false);
});

test('a product card block with no product selected shows the empty preview', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'productCard', 'data' => ['product_id' => null, 'links_mode' => 'inherit']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('Select a product to preview its card.');
});

test('the affiliate link tool renders in rich text and faq answer editors', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
            ['type' => 'faq', 'data' => [
                'heading' => 'FAQ',
                'items' => [
                    ['question' => 'Where to buy?', 'answer' => '<p>Here.</p>'],
                ],
            ]],
        ],
    ]);

    $response = $this->get(ArticleResource::getUrl('edit', ['record' => $article]));

    $response->assertSuccessful();

    // One toolbar button per editor: the rich text block and the FAQ answer.
    expect(substr_count($response->getContent(), 'Insert affiliate link'))->toBeGreaterThanOrEqual(2);
});

test('image settings page loads', function () {
    $this->get(ImageSettings::getUrl())->assertSuccessful();
});

test('category resource pages load', function () {
    $category = Category::factory()->create();

    $this->get(CategoryResource::getUrl('index'))->assertSuccessful();
    $this->get(CategoryResource::getUrl('create'))->assertSuccessful();
    $this->get(CategoryResource::getUrl('edit', ['record' => $category]))->assertSuccessful();
});

test('tag resource pages load', function () {
    $tag = Tag::factory()->create();

    $this->get(TagResource::getUrl('index'))->assertSuccessful();
    $this->get(TagResource::getUrl('create'))->assertSuccessful();
    $this->get(TagResource::getUrl('edit', ['record' => $tag]))->assertSuccessful();
});

test('product resource pages load', function () {
    $product = Product::factory()->withIngredients()->withAffiliateLinks()->create();

    $this->get(ProductResource::getUrl('index'))->assertSuccessful();
    $this->get(ProductResource::getUrl('create'))->assertSuccessful();
    $this->get(ProductResource::getUrl('view', ['record' => $product]))->assertSuccessful();
    $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertSuccessful();
});

test('brand resource pages load', function () {
    $brand = Brand::factory()->create();

    $this->get(BrandResource::getUrl('index'))->assertSuccessful();
    $this->get(BrandResource::getUrl('create'))->assertSuccessful();
    $this->get(BrandResource::getUrl('edit', ['record' => $brand]))->assertSuccessful();
});

test('ingredient resource pages load', function () {
    $ingredient = Ingredient::factory()->create();

    $this->get(IngredientResource::getUrl('index'))->assertSuccessful();
    $this->get(IngredientResource::getUrl('create'))->assertSuccessful();
    $this->get(IngredientResource::getUrl('edit', ['record' => $ingredient]))->assertSuccessful();
});
