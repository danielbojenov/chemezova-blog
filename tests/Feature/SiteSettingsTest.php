<?php

use App\Enums\NavigationLinkType;
use App\Filament\Pages\SiteSettings;
use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\Images\ContentImageProcessor;
use App\Support\Images\SettingsImageProcessor;
use App\Support\Site\SiteGeneralSettings;
use App\Support\Site\SitePageSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
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
        ->and(SitePageSettings::current()->toFormData())->toBe([
            'header_links' => [],
            'featured_tag' => 'featured',
            'featured_title' => 'Featured',
            'featured_button_label' => 'Read the article',
        ]);
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

/*
|--------------------------------------------------------------------------
| Featured article
|--------------------------------------------------------------------------
*/

test('the home page tab carries the featured article controls', function () {
    $this->get(SiteSettings::getUrl())
        ->assertSuccessful()
        ->assertSee('Featured article')
        ->assertSee('Featured tag')
        ->assertSee('Button label')
        ->assertSee('Reset to defaults');
});

test('saving the form stores the featured block settings', function () {
    Tag::factory()->create(['name' => 'Editors Pick', 'slug' => 'editors-pick']);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'featured_tag' => 'editors-pick',
            'featured_title' => 'Editor’s pick',
            'featured_button_label' => 'Read it',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = SitePageSettings::current();

    expect($settings->featuredTagSlug())->toBe('editors-pick')
        ->and($settings->featuredTagTitle())->toBe('Editor’s pick')
        ->and($settings->featuredButtonLabel())->toBe('Read it');
});

test('resetting restores the tag and its title but keeps the button wording', function () {
    Tag::factory()->create(['name' => 'Editors Pick', 'slug' => 'editors-pick']);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'featured_tag' => 'editors-pick',
            'featured_title' => 'Editor’s pick',
            'featured_button_label' => 'Read it',
        ])
        ->callAction(TestAction::make('resetFeatured')->schemaComponent('featuredArticle'))
        ->assertSchemaStateSet([
            'featured_tag' => 'featured',
            'featured_title' => 'Featured',
            'featured_button_label' => 'Read it',
        ], schema: 'form');
});

test('a blank featured title falls back to the default rather than emptying the tag line', function () {
    expect(SitePageSettings::fromFormData(['featured_title' => '  ']))
        ->toBe(SitePageSettings::defaults());
});

/*
|--------------------------------------------------------------------------
| General
|--------------------------------------------------------------------------
*/

test('the general tab carries the author and reading time controls', function () {
    $this->get(SiteSettings::getUrl())
        ->assertSuccessful()
        ->assertSee('General')
        ->assertSee('Author')
        ->assertSee('Author page')
        ->assertSee('Reading time')
        ->assertSee('Characters read per minute');
});

test('general settings fall back to the defaults when nothing is stored', function () {
    $settings = SiteGeneralSettings::current();

    expect($settings->authorName())->toBe('Elena Chemezova')
        ->and($settings->authorPageId())->toBeNull()
        ->and($settings->authorAvatarPath())->toBeNull()
        ->and($settings->charactersPerMinute())->toBe(1000);
});

test('saving the form stores the general settings under their own key', function () {
    $page = Page::factory()->published()->create();

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'author_name' => 'Someone Else',
            'author_page_id' => $page->id,
            'characters_per_minute' => 1400,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = SiteGeneralSettings::current();

    expect($settings->authorName())->toBe('Someone Else')
        ->and($settings->authorPageId())->toBe($page->id)
        ->and($settings->charactersPerMinute())->toBe(1400)
        // The two groups are stored separately, so saving one must not disturb the other.
        ->and(SitePageSettings::current()->featuredTagSlug())->toBe('featured');
});

test('a reading speed stored outside the accepted range is clamped on read', function () {
    storeGeneralSettings(['reading' => ['characters_per_minute' => 0]]);
    expect(SiteGeneralSettings::current()->charactersPerMinute())->toBe(SiteGeneralSettings::MIN_CHARACTERS_PER_MINUTE);

    storeGeneralSettings(['reading' => ['characters_per_minute' => 'nonsense']]);
    expect(SiteGeneralSettings::current()->charactersPerMinute())->toBe(1000);
});

test('an unreadable reading speed is rejected by the form', function () {
    Livewire::test(SiteSettings::class)
        ->fillForm(['characters_per_minute' => 10])
        ->call('save')
        ->assertHasFormErrors(['characters_per_minute'])
        ->assertNotNotified();
});

test('an uploaded avatar is converted out of the staging directory', function () {
    Storage::fake('public');

    Storage::disk('public')->put(
        $upload = ContentImageProcessor::TMP_DIRECTORY.'/elena-tmpab12cd.jpg',
        fakeJpegImage(600, 600),
    );

    Livewire::test(SiteSettings::class)
        ->fillForm(['author_avatar' => ['upload' => $upload]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $stored = SiteGeneralSettings::current()->authorAvatarPath();

    expect($stored)->toBe(SettingsImageProcessor::AUTHOR_DIRECTORY.'/elena.webp');

    Storage::disk('public')->assertExists([
        $stored,
        SettingsImageProcessor::AUTHOR_DIRECTORY.'/elena-thumbnail.webp',
    ]);
    Storage::disk('public')->assertMissing($upload);
});

test('saving again after an upload keeps the converted avatar', function () {
    Storage::fake('public');

    Storage::disk('public')->put(
        $upload = ContentImageProcessor::TMP_DIRECTORY.'/elena-tmpab12cd.jpg',
        fakeJpegImage(600, 600),
    );

    $component = Livewire::test(SiteSettings::class)
        ->fillForm(['author_avatar' => ['upload' => $upload]])
        ->call('save')
        ->assertHasNoFormErrors();

    $converted = SiteGeneralSettings::current()->authorAvatarPath();

    $component->call('save')->assertHasNoFormErrors();

    expect(SiteGeneralSettings::current()->authorAvatarPath())->toBe($converted);
    Storage::disk('public')->assertExists($converted);
});

test('clearing the avatar removes the files it left behind', function () {
    Storage::fake('public');

    Storage::disk('public')->put(
        $upload = ContentImageProcessor::TMP_DIRECTORY.'/elena-tmpab12cd.jpg',
        fakeJpegImage(600, 600),
    );

    $component = Livewire::test(SiteSettings::class)
        ->fillForm(['author_avatar' => ['upload' => $upload]])
        ->call('save');

    $converted = SiteGeneralSettings::current()->authorAvatarPath();

    $component
        ->fillForm(['author_avatar' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteGeneralSettings::current()->authorAvatarPath())->toBeNull();
    Storage::disk('public')->assertMissing($converted);
});
