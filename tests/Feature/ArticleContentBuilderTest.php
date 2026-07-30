<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The content builder's "add first" button, which prepends a block instead of appending it.
 */
function addFirstAction(): TestAction
{
    return TestAction::make('addFirst')->schemaComponent('content');
}

test('the add first button is hidden while the article has no content blocks', function () {
    $article = Article::factory()->create(['content' => []]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden(addFirstAction());

    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertDontSee('addFirst');
});

test('the add first button is visible once the article has a content block', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
        ],
    ]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionVisible(addFirstAction());

    // The button is rendered by the forked builder view, above the block list.
    $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
        ->assertSuccessful()
        ->assertSee('addFirst');
});

test('the add first button inserts the new block ahead of the existing ones', function () {
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p>Intro</p>']],
            ['type' => 'richText', 'data' => ['content' => '<p>Body</p>']],
        ],
    ]);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction(addFirstAction()->arguments(['block' => 'h2']))
        ->fillForm(fn (array $state): array => [
            'content' => array_replace_recursive($state['content'], [
                array_key_first($state['content']) => ['data' => ['text' => 'A new heading']],
            ]),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $content = $article->refresh()->content;

    expect($content)->toHaveCount(3)
        ->and($content[0]['type'])->toBe('h2')
        ->and($content[0]['data']['text'])->toBe('A new heading')
        ->and($content[1]['data']['content'])->toBe('<p>Intro</p>')
        ->and($content[2]['data']['content'])->toBe('<p>Body</p>');
});
