<?php

use App\Enums\NavigationLinkType;
use App\Filament\Pages\SiteSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Site\SitePageSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('site settings page loads with the navigation builder', function () {
    $this->get(SiteSettings::getUrl())
        ->assertSuccessful()
        ->assertSee('Navigation')
        ->assertSee('Home page')
        ->assertSee('Header links')
        ->assertSee('Add link');
});

test('site page settings fall back to the defaults when nothing is stored', function () {
    expect(SitePageSettings::current()->headerLinks())->toBe([])
        ->and(SitePageSettings::current()->toFormData())->toBe(['header_links' => []]);
});

test('saving an empty form persists an empty link list', function () {
    Livewire::test(SiteSettings::class)
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::getValue(SitePageSettings::SETTINGS_KEY))->toBe(SitePageSettings::defaults());
});

test('stored links override the defaults', function () {
    Setting::setValue(SitePageSettings::SETTINGS_KEY, [
        'navigation' => ['header' => [['type' => 'category', 'category_id' => 7, 'label' => 'Vitamins']]],
    ]);

    expect(SitePageSettings::current()->headerLinks())
        ->toBe([['type' => 'category', 'category_id' => 7, 'label' => 'Vitamins']]);
});

test('saving the form stores the links as an ordered list', function () {
    $vitamins = Category::factory()->create(['name' => 'Vitamins']);
    $minerals = Category::factory()->create(['name' => 'Minerals']);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'header_links' => [
                ['type' => NavigationLinkType::Category->value, 'category_id' => $vitamins->id, 'label' => null],
                ['type' => NavigationLinkType::Category->value, 'category_id' => $minerals->id, 'label' => 'All minerals'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(SitePageSettings::current()->headerLinks())->toBe([
        ['type' => 'category', 'category_id' => $vitamins->id, 'label' => null],
        ['type' => 'category', 'category_id' => $minerals->id, 'label' => 'All minerals'],
    ]);
});

test('a blank label override is stored as null', function () {
    $category = Category::factory()->create();

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'header_links' => [
                ['type' => NavigationLinkType::Category->value, 'category_id' => $category->id, 'label' => '   '],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SitePageSettings::current()->headerLinks())->toBe([
        ['type' => 'category', 'category_id' => $category->id, 'label' => null],
    ]);
});

test('a link without a category is rejected', function () {
    Livewire::test(SiteSettings::class)
        ->fillForm([
            'header_links' => [
                ['type' => NavigationLinkType::Category->value, 'category_id' => null, 'label' => 'Vitamins'],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors()
        ->assertNotNotified();

    expect(Setting::getValue(SitePageSettings::SETTINGS_KEY))->toBeNull();
});
