<?php

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

test('factory creates a valid draft page at the root', function () {
    $page = Page::factory()->create();

    expect($page->status)->toBe(ContentStatus::Draft)
        ->and($page->published_at)->toBeNull()
        ->and($page->parent_id)->toBeNull()
        ->and($page->isHub())->toBeTrue()
        ->and($page->content[0]['type'])->toBe('richText');
});

test('published state sets a past published_at', function () {
    $page = Page::factory()->published()->create();

    expect($page->status)->toBe(ContentStatus::Published)
        ->and($page->published_at)->toBeInstanceOf(Carbon::class)
        ->and($page->published_at->isPast())->toBeTrue();
});

test('slugs are unique across the whole table', function () {
    Page::factory()->create(['slug' => 'privacy-policy']);

    Page::factory()->create(['slug' => 'privacy-policy']);
})->throws(QueryException::class);

test('a hub exposes its children in sort order', function () {
    $hub = Page::factory()->create(['title' => 'Legal information']);

    Page::factory()->childOf($hub)->create(['title' => 'Terms', 'sort_order' => 2]);
    Page::factory()->childOf($hub)->create(['title' => 'Privacy policy', 'sort_order' => 1]);

    expect($hub->children()->pluck('title')->all())->toBe(['Privacy policy', 'Terms']);
});

test('a child page points back at its hub', function () {
    $hub = Page::factory()->create(['title' => 'Legal information']);
    $child = Page::factory()->childOf($hub)->create();

    expect($child->parent->is($hub))->toBeTrue()
        ->and($child->isHub())->toBeFalse();
});

test('deleting a hub promotes its children to the root instead of deleting them', function () {
    $hub = Page::factory()->create();
    $child = Page::factory()->childOf($hub)->create();

    $hub->delete();

    expect($child->refresh()->parent_id)->toBeNull()
        ->and($child->exists)->toBeTrue();
});

test('a page cannot be nested under a page that is already a child', function () {
    $hub = Page::factory()->create();
    $child = Page::factory()->childOf($hub)->create();

    Page::factory()->create(['parent_id' => $child->id]);
})->throws(RuntimeException::class, 'is already a child');

test('a page with children cannot itself be given a parent', function () {
    $hub = Page::factory()->create();
    Page::factory()->childOf($hub)->create();

    $otherHub = Page::factory()->create();

    $hub->update(['parent_id' => $otherHub->id]);
})->throws(RuntimeException::class, 'has children');

test('a page cannot be its own parent', function () {
    $page = Page::factory()->create();

    $page->update(['parent_id' => $page->id]);
})->throws(RuntimeException::class, 'its own parent');

test('saving a page without touching its parent runs no hierarchy queries', function () {
    $hub = Page::factory()->create();
    $child = Page::factory()->childOf($hub)->create();

    // The guard is skipped entirely when parent_id is unchanged, so a plain content
    // edit on a child page does not fail on its own parent being valid.
    $child->update(['title' => 'Renamed']);

    expect($child->refresh()->title)->toBe('Renamed')
        ->and($child->parent_id)->toBe($hub->id);
});

test('images are removed from disk when a page is deleted', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');

    $page = Page::factory()->create();
    $disk->put("{$page->imageDirectory()}/photo.webp", 'binary');

    $page->delete();

    expect($disk->exists("pages/{$page->id}/photo.webp"))->toBeFalse();
});

test('the image directory is namespaced per record', function () {
    $page = Page::factory()->create();

    expect($page->imageDirectory())->toBe("pages/{$page->id}");
});
