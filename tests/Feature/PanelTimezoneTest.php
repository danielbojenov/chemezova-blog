<?php

use App\Enums\ContentStatus;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the panel displays in the editors timezone while the app stays on UTC', function () {
    // Storage stays UTC and portable; only the panel converts. If these ever agree by
    // accident the round-trip test below stops proving anything.
    expect(FilamentTimezone::get())->toBe(AdminPanelProvider::TIMEZONE)
        ->and(config('app.timezone'))->toBe('UTC')
        ->and(FilamentTimezone::get())->not->toBe(config('app.timezone'));
});

test('a time entered in the panel is stored as the matching UTC instant', function () {
    $article = Article::factory()->draft()->create();

    // What an editor types off their own wall clock.
    $local = '2026-08-01 09:30:00';

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'status' => ContentStatus::Scheduled->value,
            'published_at' => $local,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $stored = $article->refresh()->published_at;

    // Stored as the same instant expressed in UTC, not as the literal digits typed.
    expect($stored->timezone->getName())->toBe('UTC')
        ->and($stored->toDateTimeString())
        ->toBe(Carbon::parse($local, AdminPanelProvider::TIMEZONE)->utc()->toDateTimeString());
});

test('scheduling for a moment just passed publishes on the next scheduler run', function () {
    // The bug this guards: a time entered as local was stored verbatim as UTC, landing
    // hours in the future, so the article silently never published.
    $justPassed = now()->timezone(AdminPanelProvider::TIMEZONE)->subMinute();

    $article = Article::factory()->draft()->create(['slug' => 'due-now']);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->fillForm([
            'status' => ContentStatus::Scheduled->value,
            'published_at' => $justPassed->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->published_at->isPast())->toBeTrue();

    $this->artisan('articles:publish-scheduled')->assertSuccessful();

    expect($article->refresh()->status)->toBe(ContentStatus::Published);

    $this->get('/articles/due-now')->assertSuccessful();
});
