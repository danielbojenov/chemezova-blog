<?php

use App\Models\Article;
use App\Models\Page;
use App\Models\Tag;
use App\Support\Site\FeaturedArticle;
use Illuminate\Support\Facades\Storage;

/**
 * The tag the block looks for out of the box.
 */
function featuredTag(): Tag
{
    return Tag::factory()->create(['name' => 'Featured', 'slug' => 'featured']);
}

test('the block renders the tagged article', function () {
    $article = articleTaggedWith(featuredTag());

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee($article->title)
        ->assertSee($article->excerpt)
        ->assertSee($article->url(), escape: false)
        ->assertSee('Read the article', escape: false);
});

test('the tag line pairs the configured title with the article\'s first other tag', function () {
    // Created in reverse, so passing would need the ordering rather than the insert order.
    $later = Tag::factory()->create(['name' => 'DISTINCT_TAG_B']);
    $first = Tag::factory()->create(['name' => 'DISTINCT_TAG_A']);

    articleTaggedWith($later, featuredTag(), $first);

    $this->get(route('home'))
        ->assertSee('Featured · DISTINCT_TAG_A', escape: false)
        ->assertDontSee('DISTINCT_TAG_B');
});

test('an article carrying only the featured tag shows the title alone', function () {
    articleTaggedWith(featuredTag());

    $this->get(route('home'))
        ->assertSee('Featured', escape: false)
        ->assertDontSee('Featured ·', escape: false);
});

test('the block is left out entirely when no tag has the configured slug', function () {
    articleTaggedWith(Tag::factory()->create(['name' => 'Magnesium', 'slug' => 'magnesium']));

    expect(FeaturedArticle::current())->toBeNull();

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('Read the article', escape: false);
});

test('the block is left out when the tag carries nothing published', function () {
    $tag = featuredTag();

    Article::factory()->draft()->create()->tags()->attach($tag);
    Article::factory()->scheduled()->create()->tags()->attach($tag);

    expect(FeaturedArticle::current())->toBeNull();

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('Read the article', escape: false);
});

test('a draft article is never picked over the published one', function () {
    $tag = featuredTag();

    Article::factory()->draft()->create(['title' => 'DISTINCT_DRAFT_TITLE'])->tags()->attach($tag);
    $published = articleTaggedWith($tag);

    $this->get(route('home'))
        ->assertSee($published->title)
        ->assertDontSee('DISTINCT_DRAFT_TITLE');
});

test('the editor can point the block at a different tag under a different title', function () {
    $article = articleTaggedWith(Tag::factory()->create(['name' => 'Editors Pick', 'slug' => 'editors-pick']));

    storeFeaturedSettings([
        'tag' => 'editors-pick',
        'title' => 'Editor’s pick',
        'button_label' => 'DISTINCT_BUTTON_LABEL',
    ]);

    $this->get(route('home'))
        ->assertSee('Editor’s pick', escape: false)
        ->assertSee('DISTINCT_BUTTON_LABEL')
        ->assertSee($article->title);
});

test('the featured image is rendered at both variants, cropped rather than stretched', function () {
    $tag = featuredTag();

    $article = Article::factory()->published()->withFeaturedImage()->create();
    $article->tags()->attach($tag);

    $response = $this->get(route('home'))->assertSuccessful();

    $directory = "articles/{$article->id}/featured";

    $response
        ->assertSee(Storage::disk('public')->url("{$directory}/featured-photo-desktop.webp"), escape: false)
        ->assertSee(Storage::disk('public')->url("{$directory}/featured-photo-mobile.webp"), escape: false)
        ->assertSee($article->featured_image_alt, escape: false)
        ->assertSee('object-cover', escape: false);
});

test('the block falls back to the tldr when the article has no excerpt', function () {
    $tag = featuredTag();

    $article = Article::factory()->published()->create([
        'excerpt' => null,
        'tldr' => '<p>DISTINCT_TLDR_SENTENCE about dosage.</p>',
    ]);
    $article->tags()->attach($tag);

    $this->get(route('home'))
        ->assertSee('DISTINCT_TLDR_SENTENCE about dosage.')
        // The markup is reduced away rather than escaped into the page.
        ->assertDontSee('&lt;p&gt;', escape: false);
});

test('the block shows no intro text when the article has neither', function () {
    $tag = featuredTag();

    $article = Article::factory()->published()->create(['excerpt' => null, 'tldr' => null]);
    $article->tags()->attach($tag);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee($article->title);

    expect(FeaturedArticle::current()?->intro)->toBeNull();
});

test('an article with no image falls back to the placeholder block', function () {
    articleTaggedWith(featuredTag());

    $this->get(route('home'))
        ->assertSee('ph-cool', escape: false)
        ->assertDontSee('object-cover', escape: false);
});

test('the byline links the author name to the configured page', function () {
    $page = Page::factory()->published()->create(['title' => 'About Elena']);

    articleTaggedWith(featuredTag());
    storeGeneralSettings(['author' => ['name' => 'DISTINCT_AUTHOR_NAME', 'page_id' => $page->id]]);

    $this->get(route('home'))
        ->assertSee('DISTINCT_AUTHOR_NAME')
        ->assertSee($page->url(), escape: false);
});

test('an unpublished author page leaves the name unlinked', function () {
    $page = Page::factory()->draft()->create();

    articleTaggedWith(featuredTag());
    storeGeneralSettings(['author' => ['name' => 'DISTINCT_AUTHOR_NAME', 'page_id' => $page->id]]);

    $this->get(route('home'))
        ->assertSee('DISTINCT_AUTHOR_NAME')
        ->assertDontSee($page->url(), escape: false);

    expect(FeaturedArticle::current()?->authorUrl)->toBeNull();
});

test('the reading estimate follows the configured reading speed', function () {
    $tag = featuredTag();

    $article = Article::factory()->published()->create([
        'content' => [['type' => 'richText', 'data' => ['content' => '<p>'.str_repeat('a', 4000).'</p>']]],
    ]);
    $article->tags()->attach($tag);

    // 4 and 8 minutes, rather than any of the times the placeholder article list still
    // prints, so the assertions can only be satisfied by the featured block.
    storeGeneralSettings(['reading' => ['characters_per_minute' => 1000]]);
    $this->get(route('home'))->assertSee('4 min', escape: false);

    storeGeneralSettings(['reading' => ['characters_per_minute' => 500]]);
    $this->get(route('home'))->assertSee('8 min', escape: false);
});

test('the article shown rotates between the articles carrying the tag', function () {
    $tag = featuredTag();

    $titles = collect(['DISTINCT_ROTATION_ONE', 'DISTINCT_ROTATION_TWO'])
        ->each(fn (string $title) => Article::factory()->published()->create(['title' => $title])->tags()->attach($tag));

    /*
     * inRandomOrder() gives no guarantee within one call, so the assertion is that both
     * articles are reachable rather than that any single reload differs. Twenty draws
     * make a false failure a one-in-half-a-million event.
     */
    $seen = collect(range(1, 20))
        ->map(fn (): ?string => FeaturedArticle::current()?->article->title)
        ->unique();

    expect($seen->sort()->values()->all())->toBe($titles->sort()->values()->all());
});
