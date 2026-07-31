<?php

use App\Enums\ContentStatus;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\RelationManagers\ChildrenRelationManager;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('a page can be created through the panel', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'status' => ContentStatus::Published->value,
            'excerpt' => 'How we handle your data.',
            'content' => [
                ['type' => 'h2', 'data' => ['text' => 'What we collect']],
                ['type' => 'richText', 'data' => ['content' => '<p>Not much.</p>']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', 'privacy-policy')->sole();

    expect($page->title)->toBe('Privacy Policy')
        ->and($page->status)->toBe(ContentStatus::Published)
        ->and($page->parent_id)->toBeNull()
        ->and($page->content)->toHaveCount(2)
        ->and($page->content[0]['data']['text'])->toBe('What we collect');
});

test('publishing without a date fills published_at with the current time', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'About',
            'slug' => 'about',
            'status' => ContentStatus::Published->value,
            'published_at' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::query()->where('slug', 'about')->sole()->published_at)->not->toBeNull();
});

test('a draft is left unpublished', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => ContentStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::query()->where('slug', 'contact')->sole()->published_at)->toBeNull();
});

test('a slug that collides with a site route is rejected', function () {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Categories',
            'slug' => 'categories',
            'status' => ContentStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Page::query()->count())->toBe(0);
});

test('a page can be grouped under a hub from its own form', function () {
    $hub = Page::factory()->create(['title' => 'Legal information']);
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->fillForm(['parent_id' => $hub->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->parent_id)->toBe($hub->id);
});

test('the parent select offers only root pages, and never the record itself', function () {
    $hub = Page::factory()->create(['title' => 'Legal information']);
    $child = Page::factory()->childOf($hub)->create(['title' => 'Terms']);
    $page = Page::factory()->create(['title' => 'About']);

    // A page that is already a child cannot become a hub, and a page cannot parent
    // itself, so neither is a valid option.
    Livewire::test(EditPage::class, ['record' => $page->id])
        ->fillForm(['parent_id' => $child->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);

    expect($page->refresh()->parent_id)->toBeNull()
        ->and($hub->children()->pluck('id')->all())->toBe([$child->id]);
});

test('pages are listed in the panel', function () {
    $pages = Page::factory(3)->create();

    Livewire::test(ListPages::class)
        ->assertCanSeeTableRecords($pages);
});

test('the view page links a published page to its public url, and a draft to nothing', function () {
    $published = Page::factory()->published()->create(['slug' => 'about']);
    $draft = Page::factory()->create(['slug' => 'unfinished']);

    $this->get(PageResource::getUrl('view', ['record' => $published]))
        ->assertSuccessful()
        ->assertSee($published->url(), escape: false)
        ->assertSee('Open on site');

    $this->get(PageResource::getUrl('view', ['record' => $draft]))
        ->assertSuccessful()
        ->assertDontSee('Open on site');
});

test('the children relation manager is shown on a hub and hidden on a child', function () {
    $hub = Page::factory()->create();
    $child = Page::factory()->childOf($hub)->create();

    expect(ChildrenRelationManager::canViewForRecord($hub, EditPage::class))->toBeTrue()
        ->and(ChildrenRelationManager::canViewForRecord($child, EditPage::class))->toBeFalse();
});

test('children are listed and reordered through the relation manager', function () {
    $hub = Page::factory()->create();
    $first = Page::factory()->childOf($hub)->create(['sort_order' => 1]);
    $second = Page::factory()->childOf($hub)->create(['sort_order' => 2]);

    Livewire::test(ChildrenRelationManager::class, [
        'ownerRecord' => $hub,
        'pageClass' => EditPage::class,
    ])
        ->assertCanSeeTableRecords([$first, $second])
        ->call('reorderTable', [$second->id, $first->id]);

    expect($hub->children()->pluck('id')->all())->toBe([$second->id, $first->id]);
});
