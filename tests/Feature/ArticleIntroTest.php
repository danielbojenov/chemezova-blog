<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the excerpt and the tldr share one Intro section on the form', function () {
    $article = Article::factory()->create();

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('Intro')
        ->assertSee('Excerpt')
        ->assertSee('TLDR');
});

test('the excerpt can be edited and is stored on the article', function () {
    $article = Article::factory()->create(['excerpt' => null]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['excerpt' => 'What to look for in a D3 supplement.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->excerpt)->toBe('What to look for in a D3 supplement.');
});

test('an excerpt longer than the intro limit is rejected', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['excerpt' => str_repeat('a', Article::INTRO_LIMIT + 1)])
        ->call('save')
        ->assertHasFormErrors(['excerpt']);
});

test('an excerpt exactly at the intro limit is accepted', function () {
    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['excerpt' => $excerpt = str_repeat('a', Article::INTRO_LIMIT)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->excerpt)->toBe($excerpt);
});

test('the view page shows the excerpt alongside the tldr', function () {
    $article = Article::factory()->create([
        'excerpt' => 'DISTINCT_EXCERPT_COPY',
        'tldr' => '<p>DISTINCT_TLDR_COPY</p>',
    ]);

    $this->get(ArticleResource::getUrl('view', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('DISTINCT_EXCERPT_COPY')
        ->assertSee('DISTINCT_TLDR_COPY');
});
