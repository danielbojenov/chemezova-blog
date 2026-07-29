<?php

use App\Models\AffiliateLink;
use App\Models\AffiliateLinkClick;

test('an active link redirects to its destination and logs a click', function () {
    $link = AffiliateLink::factory()->create(['url' => 'https://retailer.example/vitamin-d']);

    $this->get("/go/{$link->slug}")
        ->assertRedirect('https://retailer.example/vitamin-d');

    expect($link->clicks()->count())->toBe(1);
});

test('the referer header is recorded on the click', function () {
    $link = AffiliateLink::factory()->create();

    $this->get("/go/{$link->slug}", ['Referer' => 'https://example.com/some-article']);

    expect($link->clicks()->sole()->referer)->toBe('https://example.com/some-article');
});

test('a click without a referer records null', function () {
    $link = AffiliateLink::factory()->create();

    $this->get("/go/{$link->slug}");

    expect($link->clicks()->sole()->referer)->toBeNull();
});

test('an unknown slug returns 404 and logs nothing', function () {
    $this->get('/go/does-not-exist')->assertNotFound();

    expect(AffiliateLinkClick::query()->count())->toBe(0);
});

test('a disabled link returns 410 and logs nothing', function () {
    $link = AffiliateLink::factory()->disabled()->create();

    $this->get("/go/{$link->slug}")->assertGone();

    expect($link->clicks()->count())->toBe(0);
});

test('every click creates its own row', function () {
    $link = AffiliateLink::factory()->create();

    $this->get("/go/{$link->slug}");
    $this->get("/go/{$link->slug}");

    expect($link->clicks()->count())->toBe(2);
});
