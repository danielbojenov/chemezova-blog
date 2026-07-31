<?php

use App\Enums\ContentStatus;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('a scheduled article whose date has passed is published', function () {
    $article = Article::factory()->create([
        'slug' => 'due-now',
        'status' => ContentStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($article->refresh()->status)->toBe(ContentStatus::Published);
});

test('the original publication date is kept, not overwritten with the run time', function () {
    $intended = now()->subDays(2);

    $article = Article::factory()->create([
        'status' => ContentStatus::Scheduled,
        'published_at' => $intended,
    ]);

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    // The date is when the article was meant to go live, and is what the public site
    // will order by — the scheduler must not rewrite it to the minute it happened to run.
    expect($article->refresh()->published_at->timestamp)->toBe($intended->timestamp);
});

test('a scheduled article with a future date is left alone', function () {
    $article = Article::factory()->scheduled()->create();

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($article->refresh()->status)->toBe(ContentStatus::Scheduled);
});

test('drafts are never published by the scheduler', function () {
    $article = Article::factory()->create([
        'status' => ContentStatus::Draft,
        'published_at' => now()->subWeek(),
    ]);

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($article->refresh()->status)->toBe(ContentStatus::Draft);
});

test('a scheduled article with no date is left alone', function () {
    $article = Article::factory()->create([
        'status' => ContentStatus::Scheduled,
        'published_at' => null,
    ]);

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($article->refresh()->status)->toBe(ContentStatus::Scheduled);
});

test('the command reports when nothing is due', function () {
    Article::factory()->scheduled()->create();

    $this->artisan('articles:publish-scheduled')
        ->expectsOutputToContain('No scheduled articles were due.')
        ->assertSuccessful();
});

test('a published article becomes reachable on the site after the scheduler runs', function () {
    Article::factory()->create([
        'title' => 'DISTINCT_SCHEDULED_TITLE',
        'slug' => 'due-now',
        'status' => ContentStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/articles/due-now')->assertNotFound();

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    $this->get('/articles/due-now')
        ->assertSuccessful()
        ->assertSee('DISTINCT_SCHEDULED_TITLE');
});

test('the command is registered on the schedule', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'articles:publish-scheduled'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});

test('moving a scheduled article to published pulls its future date back to now', function () {
    // The other half of the contract: the scheduler flips status when a date arrives,
    // and this stops an editor doing it early from silently hiding the article behind a
    // date that has not. No clearing the field by hand.
    $article = Article::factory()->scheduled()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['status' => ContentStatus::Published->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $article->refresh();

    expect($article->status)->toBe(ContentStatus::Published)
        ->and($article->published_at->isFuture())->toBeFalse()
        ->and($article->isPublished())->toBeTrue();
});

test('publishing a draft with no date stamps it with now', function () {
    $article = Article::factory()->draft()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm(['status' => ContentStatus::Published->value, 'published_at' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->published_at)->not->toBeNull();
});

test('a deliberate back-dated publication date is preserved', function () {
    // Expressed in the panel's timezone, because that is what an editor types into the
    // picker; the stored instant must match, not the literal digits.
    $backdated = now()->timezone(AdminPanelProvider::TIMEZONE)->subYear()->startOfMinute();

    $article = Article::factory()->draft()->create();

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'status' => ContentStatus::Published->value,
            'published_at' => $backdated->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->published_at->timestamp)->toBe($backdated->timestamp);
});
