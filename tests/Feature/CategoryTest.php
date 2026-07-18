<?php

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\QueryException;

test('factory creates a valid category', function () {
    $category = Category::factory()->create();

    expect($category->name)->toBeString()
        ->and($category->slug)->toBeString()
        ->and($category->description)->toBeString();
});

test('category has many articles', function () {
    $category = Category::factory()->create();
    $articles = Article::factory(2)->create();

    $category->articles()->attach($articles);

    expect($category->articles()->count())->toBe(2);
});

test('slug must be unique', function () {
    Category::factory()->create(['slug' => 'vitamins']);

    Category::factory()->create(['slug' => 'vitamins']);
})->throws(QueryException::class);
