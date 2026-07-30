<?php

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;

test('a category page loads by slug', function () {
    Category::factory()->create([
        'name' => 'DISTINCT_CATEGORY_NAME',
        'slug' => 'vitamins',
        'description' => 'Everything about vitamins.',
    ]);

    $this->get('/categories/vitamins')
        ->assertSuccessful()
        ->assertSee('DISTINCT_CATEGORY_NAME')
        ->assertSee('Everything about vitamins.');
});

test('an unknown category slug returns a 404', function () {
    $this->get('/categories/nope')->assertNotFound();
});

test('the category page lists only published articles', function () {
    $category = Category::factory()->create();

    $published = Article::factory()->create([
        'title' => 'A published guide',
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $draft = Article::factory()->create([
        'title' => 'An unfinished draft',
        'status' => ArticleStatus::Draft,
    ]);

    $category->articles()->attach([$published->id, $draft->id]);

    $this->get(route('categories.show', $category))
        ->assertSee('A published guide')
        ->assertDontSee('An unfinished draft');
});

test('a category with no articles renders an empty state', function () {
    $category = Category::factory()->create();

    $this->get(route('categories.show', $category))
        ->assertSuccessful()
        ->assertSee('No articles in this category yet.');
});
