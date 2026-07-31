<?php

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Page;
use App\Support\Site\ReservedSlugs;

test('a published article loads by slug', function () {
    Article::factory()->published()->create([
        'title' => 'DISTINCT_ARTICLE_TITLE',
        'slug' => 'best-vitamin-d',
        'excerpt' => 'What to look for in a D3 supplement.',
    ]);

    $this->get('/articles/best-vitamin-d')
        ->assertSuccessful()
        ->assertSee('DISTINCT_ARTICLE_TITLE')
        ->assertSee('What to look for in a D3 supplement.');
});

test('the article url helper points at the canonical route', function () {
    $article = Article::factory()->published()->create(['slug' => 'best-vitamin-d']);

    expect($article->url())->toEndWith('/articles/best-vitamin-d')
        ->and($article->url())->toBe(route('articles.show', $article));
});

test('a draft article returns a 404', function () {
    Article::factory()->draft()->create(['slug' => 'unfinished']);

    $this->get('/articles/unfinished')->assertNotFound();
});

test('a scheduled article returns a 404 until its date passes', function () {
    Article::factory()->scheduled()->create(['slug' => 'coming-soon']);

    $this->get('/articles/coming-soon')->assertNotFound();
});

test('status alone decides visibility, so a stray future date does not hide an article', function () {
    // The panel cannot produce this state — fillPublishedAt pulls a future date back to
    // now — but if one is written directly, Published still means live. Keeping the date
    // out of the check is what makes this page and the category/tag listings agree.
    Article::factory()->create([
        'slug' => 'odd-date',
        'status' => ContentStatus::Published,
        'published_at' => now()->addWeek(),
    ]);

    $this->get('/articles/odd-date')->assertSuccessful();
});

test('an unknown article slug returns a 404', function () {
    $this->get('/articles/nope')->assertNotFound();
});

test('the articles prefix is not reachable as a page slug', function () {
    // `articles` is in ReservedSlugs, so this route can never be shadowed by a page.
    expect(ReservedSlugs::all())->toContain('articles');

    $hub = Page::factory()->published()->create(['slug' => 'legal']);
    Page::factory()->childOf($hub)->published()->create(['slug' => 'privacy-policy']);

    Article::factory()->published()->create(['slug' => 'best-vitamin-d']);

    // The article route is registered before the page catch-alls, so a two-segment
    // article URL is never resolved as a hub/child pair.
    $this->get('/articles/best-vitamin-d')->assertSuccessful();
    $this->get('/legal/privacy-policy')->assertSuccessful();
});
