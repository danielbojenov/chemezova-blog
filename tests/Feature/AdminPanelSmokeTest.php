<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('article resource pages load', function () {
    $article = Article::factory()->create();

    $this->get(ArticleResource::getUrl('index'))->assertSuccessful();
    $this->get(ArticleResource::getUrl('create'))->assertSuccessful();
    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertSuccessful();
});

test('article edit page loads with an image block in the content', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
            ['type' => 'image', 'data' => ['image' => 'articles/1/photo.webp', 'alt' => 'A photo', 'caption' => 'Caption']],
        ],
    ]);

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))->assertSuccessful();
});

test('image settings page loads', function () {
    $this->get(App\Filament\Pages\ImageSettings::getUrl())->assertSuccessful();
});

test('category resource pages load', function () {
    $category = Category::factory()->create();

    $this->get(CategoryResource::getUrl('index'))->assertSuccessful();
    $this->get(CategoryResource::getUrl('create'))->assertSuccessful();
    $this->get(CategoryResource::getUrl('edit', ['record' => $category]))->assertSuccessful();
});

test('tag resource pages load', function () {
    $tag = Tag::factory()->create();

    $this->get(TagResource::getUrl('index'))->assertSuccessful();
    $this->get(TagResource::getUrl('create'))->assertSuccessful();
    $this->get(TagResource::getUrl('edit', ['record' => $tag]))->assertSuccessful();
});
