<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the list page links the title to the edit page', function () {
    $article = Article::factory()->create();

    $this->get(ArticleResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee(ArticleResource::getUrl('edit', ['record' => $article]));
});

test('the view page loads and shows the article title and taxonomy', function () {
    $article = Article::factory()->create(['title' => 'A Very Distinct Headline']);
    $article->categories()->attach(Category::factory()->create(['name' => 'Nutrition']));
    $article->tags()->attach(Tag::factory()->create(['name' => 'Vitamins']));

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('A Very Distinct Headline')
        ->assertSee('Nutrition')
        ->assertSee('Vitamins');
});

test('rich text content is rendered on the view page', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>UNIQUE_RICH_MARKER paragraph.</p>']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('UNIQUE_RICH_MARKER');
});

test('image and faq blocks are rendered on the view page', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'image', 'data' => ['image' => 'articles/1/photo.webp', 'alt' => 'DISTINCT_ALT', 'caption' => 'DISTINCT_CAPTION']],
            ['type' => 'faq', 'data' => [
                'heading' => 'DISTINCT_FAQ_HEADING',
                'items' => [
                    ['question' => 'DISTINCT_QUESTION?', 'answer' => '<p>DISTINCT_ANSWER.</p>'],
                ],
            ]],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('photo-mobile.webp')
        ->assertSee('DISTINCT_ALT')
        ->assertSee('DISTINCT_CAPTION')
        ->assertSee('DISTINCT_FAQ_HEADING')
        ->assertSee('DISTINCT_QUESTION?')
        ->assertSee('DISTINCT_ANSWER');
});

test('affiliate links render with rel sponsored nofollow in rich text and faq answers', function () {
    $link = AffiliateLink::factory()->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$link->slug}\">Buy now</a></p>"]],
            ['type' => 'faq', 'data' => [
                'heading' => 'FAQ',
                'items' => [
                    ['question' => 'Where?', 'answer' => "<p><a href=\"/go/{$link->slug}\">Right here</a></p>"],
                ],
            ]],
        ],
    ]);

    $response = $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee("href=\"/go/{$link->slug}\"", escape: false);

    expect(substr_count((string) $response->getContent(), 'rel="sponsored nofollow"'))->toBe(2);
});

test('regular external links do not get the sponsored rel attribute', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p><a href="https://example.com/study">A study</a></p>']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('href="https://example.com/study"', escape: false)
        ->assertDontSee('rel="sponsored nofollow"', escape: false);
});
