<?php

use App\Models\Product;
use App\Support\Images\ProductImageProcessor;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->disk = Storage::disk('public');
    $this->processor = app(ProductImageProcessor::class);
});

test('a tmp upload is converted into webp variants and the stored path is rewritten', function () {
    $this->disk->put('products/tmp/bottle-tmpabc123.jpg', fakeJpegImage(1200, 800));

    $product = Product::factory()->create(['image' => 'products/tmp/bottle-tmpabc123.jpg']);

    $converted = $this->processor->process($product);

    $product->refresh();

    expect($converted)->toBeTrue()
        ->and($product->image)->toBe("products/{$product->id}/bottle.webp")
        ->and($this->disk->exists("products/{$product->id}/bottle.webp"))->toBeTrue()
        ->and($this->disk->exists("products/{$product->id}/bottle-desktop.webp"))->toBeTrue()
        ->and($this->disk->exists("products/{$product->id}/bottle-mobile.webp"))->toBeTrue()
        ->and($this->disk->exists("products/{$product->id}/bottle-thumbnail.webp"))->toBeTrue()
        ->and($this->disk->exists('products/tmp/bottle-tmpabc123.jpg'))->toBeFalse();
});

test('an already converted image is left alone', function () {
    $product = Product::factory()->create();
    $path = "products/{$product->id}/bottle.webp";
    $this->disk->put($path, 'binary');
    $product->updateQuietly(['image' => $path]);

    $converted = $this->processor->process($product);

    expect($converted)->toBeFalse()
        ->and($product->refresh()->image)->toBe($path)
        ->and($this->disk->exists($path))->toBeTrue();
});

test('a product without an image is untouched', function () {
    $product = Product::factory()->create(['image' => null]);

    expect($this->processor->process($product))->toBeFalse()
        ->and($product->refresh()->image)->toBeNull();
});

test('replacing an image prunes the previous variants', function () {
    $product = Product::factory()->create();

    foreach (['', '-desktop', '-mobile', '-thumbnail'] as $suffix) {
        $this->disk->put("products/{$product->id}/old{$suffix}.webp", 'binary');
    }
    $product->updateQuietly(['image' => "products/{$product->id}/old.webp"]);

    $this->disk->put('products/tmp/new-tmpxyz789.jpg', fakeJpegImage(600, 400));
    $product->updateQuietly(['image' => 'products/tmp/new-tmpxyz789.jpg']);

    $this->processor->process($product);

    expect($this->disk->exists("products/{$product->id}/new.webp"))->toBeTrue()
        ->and($this->disk->exists("products/{$product->id}/old.webp"))->toBeFalse()
        ->and($this->disk->exists("products/{$product->id}/old-mobile.webp"))->toBeFalse();
});

test('a name colliding with an existing image set is given a unique base name', function () {
    $product = Product::factory()->create();
    $this->disk->put("products/{$product->id}/bottle.webp", 'binary');
    $this->disk->put('products/tmp/bottle-tmpabc123.jpg', fakeJpegImage(400, 400));

    $product->updateQuietly(['image' => 'products/tmp/bottle-tmpabc123.jpg']);

    $this->processor->process($product);

    expect($product->refresh()->image)->toBe("products/{$product->id}/bottle-1.webp");
});

test('an unreadable upload is left in place rather than losing the record', function () {
    $this->disk->put('products/tmp/broken-tmpabc123.jpg', 'not really an image');

    $product = Product::factory()->create(['image' => 'products/tmp/broken-tmpabc123.jpg']);

    $converted = $this->processor->process($product);

    expect($converted)->toBeFalse()
        ->and($product->refresh()->image)->toBe('products/tmp/broken-tmpabc123.jpg')
        ->and($this->disk->exists('products/tmp/broken-tmpabc123.jpg'))->toBeTrue();
});
