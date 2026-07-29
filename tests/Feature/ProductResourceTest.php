<?php

use App\Enums\AffiliateLinkStatus;
use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\AffiliateLink;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('a product can be created through the panel', function () {
    $brand = Brand::factory()->create();
    $ingredient = Ingredient::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'NOW Vitamin D3 5000 IU',
            'slug' => 'now-vitamin-d3-5000',
            'brand_id' => $brand->id,
            'primary_ingredient_id' => $ingredient->id,
            'form' => SupplementForm::Softgel,
            'composition' => ProductComposition::Single,
            'price' => 12.50,
            'doses_per_pack' => 120,
            'currency' => 'USD',
            'rating' => 4.5,
            'status' => ProductStatus::Published,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', 'now-vitamin-d3-5000')->sole();

    expect($product->name)->toBe('NOW Vitamin D3 5000 IU')
        ->and($product->form)->toBe(SupplementForm::Softgel)
        ->and($product->status)->toBe(ProductStatus::Published)
        ->and($product->brand_id)->toBe($brand->id)
        ->and((float) $product->price_per_dose)->toBeGreaterThan(0.0);
});

test('a duplicate slug is rejected', function () {
    Product::factory()->create(['slug' => 'taken-slug']);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Another product',
            'slug' => 'taken-slug',
            'currency' => 'USD',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

test('a product can be edited through the panel', function () {
    $product = Product::factory()->create(['name' => 'Old name']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['name' => 'New name', 'rating' => 3.5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->refresh()->name)->toBe('New name')
        ->and((float) $product->rating)->toBe(3.5);
});

test('a rating accepts a tenth of a point', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['rating' => 4.7])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $product->refresh()->rating)->toBe(4.7);
});

test('at most two retailer links can be attached', function () {
    $links = AffiliateLink::factory()->count(3)->create(['status' => AffiliateLinkStatus::Active]);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Three link product',
            'slug' => 'three-link-product',
            'currency' => 'USD',
            'affiliateLinks' => $links->pluck('id')->all(),
        ])
        ->call('create')
        ->assertHasFormErrors(['affiliateLinks']);

    expect(Product::query()->where('slug', 'three-link-product')->exists())->toBeFalse();
});

test('two retailer links are accepted', function () {
    $links = AffiliateLink::factory()->count(2)->create(['status' => AffiliateLinkStatus::Active]);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Two link product',
            'slug' => 'two-link-product',
            'currency' => 'USD',
            'affiliateLinks' => $links->pluck('id')->all(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::query()->where('slug', 'two-link-product')->sole()->affiliateLinks()->count())->toBe(2);
});

test('the list page shows products and links the name to the edit page', function () {
    $product = Product::factory()->create();

    $response = $this->get(ProductResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee($product->name);
    $response->assertSee(ProductResource::getUrl('edit', ['record' => $product]), escape: false);
});

test('the catalog can be sorted by price per dose from the list page', function () {
    $expensive = Product::factory()->priced(60.00, 30)->create();
    $cheap = Product::factory()->priced(10.00, 100)->create();

    Livewire::test(ListProducts::class)
        ->sortTable('price_per_dose')
        ->assertCanSeeTableRecords([$cheap, $expensive], inOrder: true);
});

test('the catalog can be filtered by brand', function () {
    $brand = Brand::factory()->create();
    $matching = Product::factory()->create(['brand_id' => $brand->id]);
    $other = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->filterTable('brand', [$brand->id])
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the view page shows the product details and its retailer links', function () {
    $brand = Brand::factory()->create(['name' => 'Solgar']);
    $link = AffiliateLink::factory()->create(['name' => 'iHerb listing']);
    $product = Product::factory()->priced(24.00, 60)->create([
        'name' => 'Solgar Vitamin D3',
        'brand_id' => $brand->id,
    ]);
    $product->affiliateLinks()->attach($link);

    $response = $this->get(ProductResource::getUrl('view', ['record' => $product]));

    $response->assertSuccessful();
    $response->assertSee('Solgar Vitamin D3');
    $response->assertSee('Solgar');
    $response->assertSee('iHerb listing');
});
