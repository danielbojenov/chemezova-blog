<?php

use App\Enums\AffiliateLinkStatus;
use App\Filament\Resources\AffiliateLinks\AffiliateLinkResource;
use App\Filament\Resources\AffiliateLinks\Pages\CreateAffiliateLink;
use App\Filament\Resources\AffiliateLinks\Pages\EditAffiliateLink;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('affiliate link resource pages load', function () {
    $link = AffiliateLink::factory()->create();

    $this->get(AffiliateLinkResource::getUrl('index'))->assertSuccessful();
    $this->get(AffiliateLinkResource::getUrl('create'))->assertSuccessful();
    $this->get(AffiliateLinkResource::getUrl('view', ['record' => $link]))->assertSuccessful();
    $this->get(AffiliateLinkResource::getUrl('edit', ['record' => $link]))->assertSuccessful();
});

test('an affiliate link can be created through the panel', function () {
    Livewire::test(CreateAffiliateLink::class)
        ->fillForm([
            'name' => 'NOW Vitamin D3 5000 IU',
            'slug' => 'now-vitamin-d3-5000',
            'url' => 'https://retailer.example/now-d3',
            'retailer' => 'iHerb',
            'notes' => 'Spring campaign',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $link = AffiliateLink::query()->where('slug', 'now-vitamin-d3-5000')->sole();

    expect($link->name)->toBe('NOW Vitamin D3 5000 IU')
        ->and($link->url)->toBe('https://retailer.example/now-d3')
        ->and($link->status)->toBe(AffiliateLinkStatus::Active);
});

test('a duplicate slug is rejected', function () {
    AffiliateLink::factory()->create(['slug' => 'taken-slug']);

    Livewire::test(CreateAffiliateLink::class)
        ->fillForm([
            'name' => 'Another Link',
            'slug' => 'taken-slug',
            'url' => 'https://retailer.example/another',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

test('an affiliate link can be edited through the panel', function () {
    $link = AffiliateLink::factory()->create();

    Livewire::test(EditAffiliateLink::class, ['record' => $link->id])
        ->fillForm([
            'url' => 'https://retailer.example/updated-destination',
            'status' => AffiliateLinkStatus::Disabled->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $link->refresh();

    expect($link->url)->toBe('https://retailer.example/updated-destination')
        ->and($link->status)->toBe(AffiliateLinkStatus::Disabled);
});

test('the list page shows click and article counts', function () {
    $link = AffiliateLink::factory()->create();
    $link->clicks()->create(['referer' => null]);
    $link->clicks()->create(['referer' => null]);
    $link->articles()->attach(Article::factory()->create());

    $this->get(AffiliateLinkResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($link->name)
        ->assertSee("/go/{$link->slug}");
});

test('the view page shows placements and click statistics', function () {
    $link = AffiliateLink::factory()->create();
    $link->clicks()->create(['referer' => null]);
    $link->articles()->attach(Article::factory()->create(['title' => 'A Distinct Placement Title']));

    $this->get(AffiliateLinkResource::getUrl('view', ['record' => $link]))
        ->assertSuccessful()
        ->assertSee('A Distinct Placement Title')
        ->assertSee('Total clicks');
});
