<?php

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Tag;
use App\Support\Site\ReservedSlugs;

test('a tag page loads by slug', function () {
    Tag::factory()->create([
        'name' => 'DISTINCT_TAG_NAME',
        'slug' => 'vitamin-d',
    ]);

    $this->get('/tags/vitamin-d')
        ->assertSuccessful()
        ->assertSee('DISTINCT_TAG_NAME');
});

test('an unknown tag slug returns a 404', function () {
    $this->get('/tags/nope')->assertNotFound();
});

test('the tag page lists only published articles', function () {
    $tag = Tag::factory()->create();

    $published = Article::factory()->published()->create(['title' => 'A published guide']);
    $draft = Article::factory()->draft()->create(['title' => 'An unfinished draft']);
    $scheduled = Article::factory()->scheduled()->create(['title' => 'A future piece']);

    $tag->articles()->attach([$published->id, $draft->id, $scheduled->id]);

    $this->get(route('tags.show', $tag))
        ->assertSee('A published guide')
        ->assertDontSee('An unfinished draft')
        ->assertDontSee('A future piece');
});

test('a tag with no articles renders an empty state', function () {
    $tag = Tag::factory()->create();

    $this->get(route('tags.show', $tag))
        ->assertSuccessful()
        ->assertSee('No articles with this tag yet.');
});

test('the tag route is not shadowed by a page', function () {
    // `tags` is reserved, so no page can take the segment.
    expect(ReservedSlugs::all())->toContain('tags');

    $tag = Tag::factory()->create(['slug' => 'vitamin-d']);
    Article::factory()->published()->create(['title' => 'DISTINCT_TAGGED'])->tags()->attach($tag);

    $this->get('/tags/vitamin-d')
        ->assertSuccessful()
        ->assertSee('DISTINCT_TAGGED');
});

test('a published article with a future date still counts as live', function () {
    // Status is the switch, and fillPublishedAt guarantees a live article never carries
    // a future date — so the listing filters on status alone, matching the article page.
    $tag = Tag::factory()->create();

    $article = Article::factory()->create([
        'title' => 'DISTINCT_ODD_DATE',
        'slug' => 'odd-date',
        'status' => ContentStatus::Published,
        'published_at' => now()->addWeek(),
    ]);

    $tag->articles()->attach($article);

    $this->get(route('tags.show', $tag))->assertSee('DISTINCT_ODD_DATE');
    $this->get('/articles/odd-date')->assertSuccessful();
});
