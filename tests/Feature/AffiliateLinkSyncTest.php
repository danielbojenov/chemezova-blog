<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\User;
use App\Support\AffiliateLinks\ArticleAffiliateLinkSyncer;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the syncer attaches links referenced in rich text blocks', function () {
    $link = AffiliateLink::factory()->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p>Buy it <a href=\"/go/{$link->slug}\">here</a>.</p>"]],
        ],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->all())->toBe([$link->id]);
});

test('the syncer finds links inside faq answers and absolute urls', function () {
    [$faqLink, $absoluteLink] = AffiliateLink::factory()->count(2)->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p><a href=\"https://blog.example/go/{$absoluteLink->slug}\">absolute</a></p>"]],
            ['type' => 'faq', 'data' => [
                'heading' => 'FAQ',
                'items' => [
                    ['question' => 'Where to buy?', 'answer' => "<p><a href=\"/go/{$faqLink->slug}\">here</a></p>"],
                ],
            ]],
        ],
    ]);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->sort()->values()->all())
        ->toBe(collect([$faqLink->id, $absoluteLink->id])->sort()->values()->all());
});

test('the syncer ignores unknown slugs and regular links, and detaches removed ones', function () {
    $link = AffiliateLink::factory()->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => '<p><a href="/go/nope">unknown</a> <a href="https://example.com/page">regular</a></p>']],
        ],
    ]);
    $article->affiliateLinks()->attach($link);

    app(ArticleAffiliateLinkSyncer::class)->sync($article);

    expect($article->affiliateLinks()->count())->toBe(0);
});

test('creating an article through the panel syncs affiliate link placements', function () {
    $link = AffiliateLink::factory()->create();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Best Vitamin D Supplements',
            'slug' => 'best-vitamin-d-supplements',
            'content' => [
                ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$link->slug}\">Buy</a></p>"]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($link->articles()->pluck('slug')->all())->toBe(['best-vitamin-d-supplements']);
});

test('saving an article through the panel swaps placements to match the content', function () {
    [$oldLink, $newLink] = AffiliateLink::factory()->count(2)->create();
    $article = Article::factory()->create([
        'content' => [
            ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$oldLink->slug}\">old</a></p>"]],
        ],
    ]);
    $article->affiliateLinks()->attach($oldLink);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'content' => [
                ['type' => 'richText', 'data' => ['content' => "<p><a href=\"/go/{$newLink->slug}\">new</a></p>"]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->affiliateLinks()->pluck('affiliate_links.id')->all())->toBe([$newLink->id]);
});
