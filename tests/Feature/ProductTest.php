<?php

use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

test('a product can be created with its enum attributes', function () {
    $product = Product::factory()->create([
        'name' => 'Vitamin D3 5000 IU',
        'slug' => 'vitamin-d3-5000-iu',
        'form' => SupplementForm::Softgel,
        'composition' => ProductComposition::Single,
        'status' => ProductStatus::Published,
    ]);

    expect($product->form)->toBe(SupplementForm::Softgel)
        ->and($product->composition)->toBe(ProductComposition::Single)
        ->and($product->status)->toBe(ProductStatus::Published);
});

test('the slug must be unique', function () {
    Product::factory()->create(['slug' => 'duplicate']);
    Product::factory()->create(['slug' => 'duplicate']);
})->throws(QueryException::class);

test('the price per dose is derived by the database', function () {
    $product = Product::factory()->priced(29.99, 60)->create();

    expect((float) $product->fresh()->price_per_dose)->toBe(0.4998);
});

test('the price per dose is null when the dose count is missing or zero', function () {
    $zero = Product::factory()->priced(29.99, 0)->create();
    $missing = Product::factory()->create(['price' => 29.99, 'doses_per_pack' => null]);

    expect($zero->fresh()->price_per_dose)->toBeNull()
        ->and($missing->fresh()->price_per_dose)->toBeNull();
});

test('the price per dose updates when the pack price changes', function () {
    $product = Product::factory()->priced(30.00, 60)->create();

    $product->update(['price' => 60.00]);

    expect((float) $product->fresh()->price_per_dose)->toBe(1.0);
});

test('the catalog can be sorted by the derived price per dose', function () {
    $expensive = Product::factory()->priced(60.00, 30)->create();
    $cheap = Product::factory()->priced(10.00, 100)->create();

    expect(Product::orderBy('price_per_dose')->pluck('id')->all())
        ->toBe([$cheap->id, $expensive->id]);
});

test('a product belongs to a brand and a primary ingredient', function () {
    $brand = Brand::factory()->create(['name' => 'Solgar']);
    $ingredient = Ingredient::factory()->create(['name' => 'Vitamin D']);

    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'primary_ingredient_id' => $ingredient->id,
    ]);

    expect($product->brand->name)->toBe('Solgar')
        ->and($product->primaryIngredient->name)->toBe('Vitamin D')
        ->and($brand->products()->count())->toBe(1);
});

test('deleting a brand leaves its products without one', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $brand->delete();

    expect($product->fresh()->brand_id)->toBeNull();
});

test('a product has many ingredients', function () {
    $product = Product::factory()->create();
    $ingredients = Ingredient::factory()->count(3)->create();

    $product->ingredients()->attach($ingredients);

    expect($product->ingredients()->count())->toBe(3)
        ->and($ingredients->first()->products()->count())->toBe(1);
});

test('a product carries its own retailer links', function () {
    $product = Product::factory()->create();
    $links = AffiliateLink::factory()->count(2)->create();

    $product->affiliateLinks()->attach($links);

    expect($product->affiliateLinks()->count())->toBe(2)
        ->and($links->first()->products()->pluck('products.id')->all())->toBe([$product->id]);
});

test('a product records the rank it holds in each article', function () {
    $product = Product::factory()->create();
    [$first, $second] = Article::factory()->count(2)->create();

    $first->products()->attach($product, ['rank' => 1]);
    $second->products()->attach($product, ['rank' => 7]);

    $ranks = $product->articles()->pluck('rank', 'articles.id');

    expect((int) $ranks[$first->id])->toBe(1)
        ->and((int) $ranks[$second->id])->toBe(7);
});

test('deleting a product removes its image directory', function () {
    Storage::fake('public');

    $product = Product::factory()->create();
    Storage::disk('public')->put("products/{$product->id}/photo.webp", 'binary');

    $product->delete();

    Storage::disk('public')->assertMissing("products/{$product->id}/photo.webp");
});
