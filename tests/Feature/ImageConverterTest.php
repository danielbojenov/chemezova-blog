<?php

use App\Enums\ImageVariant;
use App\Support\Images\ImageConverter;
use App\Support\Images\ImageSizeSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->disk = Storage::disk('public');
    $this->converter = app(ImageConverter::class);
});

test('converts an image into all webp variants', function () {
    $this->disk->put('articles/tmp/photo.jpg', fakeJpegImage(1200, 800));

    $paths = $this->converter->convert($this->disk, 'articles/tmp/photo.jpg', 'articles/1', 'photo', ImageSizeSettings::current());

    expect($paths)->toBe([
        'original' => 'articles/1/photo.webp',
        'desktop' => 'articles/1/photo-desktop.webp',
        'mobile' => 'articles/1/photo-mobile.webp',
        'thumbnail' => 'articles/1/photo-thumbnail.webp',
    ]);

    foreach ($paths as $path) {
        expect($this->disk->exists($path))->toBeTrue();

        $info = getimagesizefromstring($this->disk->get($path));

        expect($info['mime'])->toBe('image/webp');
    }
});

test('landscape images are capped at the landscape max width', function () {
    $this->disk->put('articles/tmp/photo.jpg', fakeJpegImage(1200, 800));

    $sizes = new ImageSizeSettings([
        ImageVariant::Original->value => ['landscape' => 1000, 'portrait' => 500],
        ImageVariant::Desktop->value => ['landscape' => 600, 'portrait' => 300],
        ImageVariant::Mobile->value => ['landscape' => 400, 'portrait' => 200],
        ImageVariant::Thumbnail->value => ['landscape' => 100, 'portrait' => 50],
    ]);

    $this->converter->convert($this->disk, 'articles/tmp/photo.jpg', 'articles/1', 'photo', $sizes);

    [$originalWidth] = getimagesizefromstring($this->disk->get('articles/1/photo.webp'));
    [$desktopWidth, $desktopHeight] = getimagesizefromstring($this->disk->get('articles/1/photo-desktop.webp'));
    [$mobileWidth] = getimagesizefromstring($this->disk->get('articles/1/photo-mobile.webp'));
    [$thumbnailWidth] = getimagesizefromstring($this->disk->get('articles/1/photo-thumbnail.webp'));

    expect($originalWidth)->toBe(1000)
        ->and($desktopWidth)->toBe(600)
        ->and($desktopHeight)->toBe(400)
        ->and($mobileWidth)->toBe(400)
        ->and($thumbnailWidth)->toBe(100);
});

test('portrait images are capped at the portrait max width', function () {
    $this->disk->put('articles/tmp/tall.jpg', fakeJpegImage(800, 1200));

    $sizes = new ImageSizeSettings([
        ImageVariant::Original->value => ['landscape' => 1000, 'portrait' => 500],
        ImageVariant::Desktop->value => ['landscape' => 600, 'portrait' => 300],
        ImageVariant::Mobile->value => ['landscape' => 400, 'portrait' => 200],
        ImageVariant::Thumbnail->value => ['landscape' => 100, 'portrait' => 50],
    ]);

    $this->converter->convert($this->disk, 'articles/tmp/tall.jpg', 'articles/1', 'tall', $sizes);

    [$originalWidth] = getimagesizefromstring($this->disk->get('articles/1/tall.webp'));
    [$desktopWidth, $desktopHeight] = getimagesizefromstring($this->disk->get('articles/1/tall-desktop.webp'));

    expect($originalWidth)->toBe(500)
        ->and($desktopWidth)->toBe(300)
        ->and($desktopHeight)->toBe(450);
});

test('small images are never upscaled', function () {
    $this->disk->put('articles/tmp/small.jpg', fakeJpegImage(100, 80));

    $this->converter->convert($this->disk, 'articles/tmp/small.jpg', 'articles/1', 'small', ImageSizeSettings::current());

    foreach (ImageVariant::cases() as $variant) {
        [$width, $height] = getimagesizefromstring($this->disk->get("articles/1/small{$variant->fileSuffix()}.webp"));

        expect($width)->toBe(100)->and($height)->toBe(80);
    }
});
