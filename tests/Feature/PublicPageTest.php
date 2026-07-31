<?php

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Page;

test('a root page loads at the top level by slug', function () {
    Page::factory()->published()->create([
        'title' => 'DISTINCT_PAGE_TITLE',
        'slug' => 'about',
        'excerpt' => 'Who is behind this site.',
    ]);

    $this->get('/about')
        ->assertSuccessful()
        ->assertSee('DISTINCT_PAGE_TITLE')
        ->assertSee('Who is behind this site.');
});

test('a child page loads nested under its hub', function () {
    $hub = Page::factory()->published()->create(['slug' => 'legal']);

    Page::factory()->childOf($hub)->published()->create([
        'title' => 'DISTINCT_CHILD_TITLE',
        'slug' => 'privacy-policy',
    ]);

    $this->get('/legal/privacy-policy')
        ->assertSuccessful()
        ->assertSee('DISTINCT_CHILD_TITLE');
});

test('a child page under the wrong hub returns a 404', function () {
    $hub = Page::factory()->published()->create(['slug' => 'legal']);
    Page::factory()->childOf($hub)->published()->create(['slug' => 'privacy-policy']);

    Page::factory()->published()->create(['slug' => 'about']);

    $this->get('/about/privacy-policy')->assertNotFound();
});

test('a child page reached at the top level redirects to its canonical nested url', function () {
    $hub = Page::factory()->published()->create(['slug' => 'legal']);
    $child = Page::factory()->childOf($hub)->published()->create(['slug' => 'privacy-policy']);

    $this->get('/privacy-policy')
        ->assertRedirect('/legal/privacy-policy')
        ->assertStatus(301);

    expect($child->url())->toEndWith('/legal/privacy-policy');
});

test('a root page url has no parent segment', function () {
    $page = Page::factory()->published()->create(['slug' => 'about']);

    expect($page->url())->toEndWith('/about');
});

test('a draft page returns a 404', function () {
    Page::factory()->create(['slug' => 'unfinished', 'status' => ContentStatus::Draft]);

    $this->get('/unfinished')->assertNotFound();
});

test('a draft child page returns a 404 rather than rendering under its hub', function () {
    $hub = Page::factory()->published()->create(['slug' => 'legal']);
    Page::factory()->childOf($hub)->create(['slug' => 'terms', 'status' => ContentStatus::Draft]);

    $this->get('/legal/terms')->assertNotFound();
});

test('an unknown slug returns a 404', function () {
    $this->get('/nope')->assertNotFound();
});

test('the page routes do not swallow the routes declared before them', function () {
    // `/{page:slug}` sits at the root of the site, so this is the regression guard for
    // it shadowing anything else that lives there.
    $category = Category::factory()->create(['slug' => 'vitamins']);

    $this->get(route('categories.show', $category))->assertSuccessful();
    $this->get('/')->assertSuccessful();
});

test('the admin panel is still reachable', function () {
    // Filament's panel lives at /admin, one segment, exactly what the catch-all matches.
    $this->get('/admin/login')->assertSuccessful();
});
