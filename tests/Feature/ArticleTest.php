<?php

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

test('factory creates a valid draft article', function () {
    $article = Article::factory()->create();

    expect($article->status)->toBe(ArticleStatus::Draft)
        ->and($article->published_at)->toBeNull()
        ->and($article->content)->toBeArray()
        ->and($article->content[0]['type'])->toBe('richText')
        ->and($article->content[0]['data']['content'])->toBeString();
});

test('published state sets a past published_at', function () {
    $article = Article::factory()->published()->create();

    expect($article->status)->toBe(ArticleStatus::Published)
        ->and($article->published_at)->toBeInstanceOf(Carbon::class)
        ->and($article->published_at->isPast())->toBeTrue();
});

test('scheduled state sets a future published_at', function () {
    $article = Article::factory()->scheduled()->create();

    expect($article->status)->toBe(ArticleStatus::Scheduled)
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
