<?php

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

test('factory creates a valid draft article', function () {
    $article = Article::factory()->create();

    expect($article->status)->toBe(ContentStatus::Draft)
        ->and($article->published_at)->toBeNull()
        ->and($article->content)->toBeArray()
        ->and($article->content[0]['type'])->toBe('richText')
        ->and($article->content[0]['data']['content'])->toBeString();
});

test('faq block content round-trips through the array cast', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'faq', 'data' => [
                'heading' => 'Frequently asked questions',
                'items' => [
                    ['question' => 'What is this?', 'answer' => '<p>An article.</p>'],
                    ['question' => 'Why?', 'answer' => '<p>Because.</p>'],
                ],
            ]],
        ],
    ]);

    $content = $article->refresh()->content;

    expect($content[0]['type'])->toBe('faq')
        ->and($content[0]['data']['heading'])->toBe('Frequently asked questions')
        ->and($content[0]['data']['items'])->toHaveCount(2)
        ->and($content[0]['data']['items'][0])->toBe(['question' => 'What is this?', 'answer' => '<p>An article.</p>']);
});

test('published state sets a past published_at', function () {
    $article = Article::factory()->published()->create();

    expect($article->status)->toBe(ContentStatus::Published)
        ->and($article->published_at)->toBeInstanceOf(Carbon::class)
        ->and($article->published_at->isPast())->toBeTrue();
});

test('scheduled state sets a future published_at', function () {
    $article = Article::factory()->scheduled()->create();

    expect($article->status)->toBe(ContentStatus::Scheduled)
        ->and($article->published_at->isFuture())->toBeTrue();
});

test('categories can be attached and synced', function () {
    $article = Article::factory()->create();
    $categories = Category::factory(3)->create();

    $article->categories()->attach($categories);

    expect($article->categories()->count())->toBe(3);

    $article->categories()->sync([$categories->first()->id]);

    expect($article->refresh()->categories()->count())->toBe(1)
        ->and($categories->first()->articles()->count())->toBe(1);
});

test('tags can be attached and synced', function () {
    $article = Article::factory()->create();
    $tags = Tag::factory(2)->create();

    $article->tags()->attach($tags);

    expect($article->tags()->count())->toBe(2)
        ->and($tags->first()->articles->first()->is($article))->toBeTrue();
});

test('deleting an article removes its pivot rows', function () {
    $article = Article::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $article->categories()->attach($category);
    $article->tags()->attach($tag);

    $article->delete();

    expect($category->refresh()->articles()->count())->toBe(0)
        ->and($tag->refresh()->articles()->count())->toBe(0);
});

test('slug must be unique', function () {
    Article::factory()->create(['slug' => 'vitamin-d-guide']);

    Article::factory()->create(['slug' => 'vitamin-d-guide']);
})->throws(QueryException::class);

/*
|--------------------------------------------------------------------------
| Intro
|--------------------------------------------------------------------------
*/

test('the excerpt is the intro when the author has written one', function () {
    $article = Article::factory()->create([
        'excerpt' => '  What to look for in a D3 supplement.  ',
        'tldr' => '<p>Ignored while an excerpt exists.</p>',
    ]);

    expect($article->intro())->toBe('What to look for in a D3 supplement.');
});

test('the tldr stands in for a missing excerpt, as plain text', function () {
    $article = Article::factory()->create([
        'excerpt' => null,
        'tldr' => '<p>Most adults need <strong>more</strong> than they think.</p>',
    ]);

    expect($article->intro())->toBe('Most adults need more than they think.');
});

test('a long tldr is shortened to the intro limit', function () {
    $article = Article::factory()->create([
        'excerpt' => null,
        'tldr' => '<p>'.str_repeat('word ', 200).'</p>',
    ]);

    $intro = $article->intro();

    expect(mb_strlen($intro))->toBeLessThanOrEqual(Article::INTRO_LIMIT)
        ->and($intro)->toEndWith(Article::INTRO_ELLIPSIS);
});

test('an article with neither an excerpt nor a tldr has no intro', function () {
    $article = Article::factory()->create(['excerpt' => null, 'tldr' => null]);

    expect($article->intro())->toBeNull();
});

test('a tldr holding only markup counts as no intro', function () {
    $article = Article::factory()->create(['excerpt' => null, 'tldr' => '<p></p>']);

    expect($article->intro())->toBeNull();
});
