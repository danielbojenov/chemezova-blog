<?php

use App\Enums\ImageVariant;
use App\Filament\Pages\ImageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Images\ImageSizeSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('setting values round trip through the model', function () {
    Setting::setValue('some.key', ['a' => 1]);

    expect(Setting::getValue('some.key'))->toBe(['a' => 1])
        ->and(Setting::getValue('missing.key', 'fallback'))->toBe('fallback');

    Setting::setValue('some.key', ['a' => 2]);

    expect(Setting::getValue('some.key'))->toBe(['a' => 2])
        ->and(Setting::query()->where('key', 'some.key')->count())->toBe(1);
});

test('image size settings fall back to defaults when nothing is stored', function () {
    $sizes = ImageSizeSettings::current();

    expect($sizes->maxWidth(ImageVariant::Original, landscape: true))->toBe(2560)
        ->and($sizes->maxWidth(ImageVariant::Original, landscape: false))->toBe(1440)
        ->and($sizes->maxWidth(ImageVariant::Thumbnail, landscape: true))->toBe(320);
});

test('stored image sizes override the defaults', function () {
    Setting::setValue(ImageSizeSettings::SETTINGS_KEY, [
        ImageVariant::Desktop->value => ['landscape' => 1000, 'portrait' => 700],
    ]);

    $sizes = ImageSizeSettings::current();

    expect($sizes->maxWidth(ImageVariant::Desktop, landscape: true))->toBe(1000)
        ->and($sizes->maxWidth(ImageVariant::Desktop, landscape: false))->toBe(700)
        ->and($sizes->maxWidth(ImageVariant::Original, landscape: true))->toBe(2560);
});

test('image settings page loads', function () {
    $this->get(ImageSettings::getUrl())->assertSuccessful();
});

test('saving the form persists the sizes', function () {
    Livewire::test(ImageSettings::class)
        ->fillForm([
            'desktop_landscape' => 1600,
            'desktop_portrait' => 900,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $sizes = ImageSizeSettings::current();

    expect($sizes->maxWidth(ImageVariant::Desktop, landscape: true))->toBe(1600)
        ->and($sizes->maxWidth(ImageVariant::Desktop, landscape: false))->toBe(900)
        ->and($sizes->maxWidth(ImageVariant::Mobile, landscape: true))->toBe(768);
});

test('non numeric widths are rejected', function () {
    Livewire::test(ImageSettings::class)
        ->fillForm([
            'desktop_landscape' => 'not-a-number',
        ])
        ->call('save')
        ->assertHasFormErrors(['desktop_landscape' => 'numeric']);

    expect(Setting::getValue(ImageSizeSettings::SETTINGS_KEY))->toBeNull();
});

test('widths below the minimum are rejected', function () {
    Livewire::test(ImageSettings::class)
        ->fillForm([
            'thumbnail_portrait' => 5,
        ])
        ->call('save')
        ->assertHasFormErrors(['thumbnail_portrait']);
});
