<?php

use App\Models\Article;
use App\Models\Tag;
use Illuminate\Database\QueryException;

test('factory creates a valid tag', function () {
    $tag = Tag::factory()->create();

    expect($tag->name)->toBeString()
        ->and($tag->slug)->toBeString();
});

test('tag has many articles', function () {
    $tag = Tag::factory()->create();
    $articles = Article::factory(3)->create();

    $tag->articles()->attach($articles);

    expect($tag->articles()->count())->toBe(3);
});

test('slug must be unique', function () {
    Tag::factory()->create(['slug' => 'magnesium']);

    Tag::factory()->create(['slug' => 'magnesium']);
})->throws(QueryException::class);
