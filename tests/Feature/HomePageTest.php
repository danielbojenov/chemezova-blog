<?php

use App\Models\Category;
use App\Models\Tag;

test('the home page loads', function () {
    $this->get(route('home'))->assertSuccessful();
});

test('the home page renders every section of the design', function () {
    $article = articleTaggedWith(Tag::factory()->create(['name' => 'Featured', 'slug' => 'featured']));

    $this->get(route('home'))
        ->assertSee('chemezova', escape: false)
        ->assertSee($article->title)
        ->assertSee('Latest articles')
        ->assertSee('Best Vitamin D Supplements, Ranked')
        ->assertSee('Popular now')
        ->assertSee('The Nutrient Letter')
        ->assertSee('Browse topics')
        ->assertSee('Some links are affiliate links', escape: false);
});

/*
 * The names below are deliberately distinctive: the footer and topic scroller still
 * carry the design's placeholder copy, which mentions real topic names like "Vitamins".
 */
test('the header renders the configured navigation links', function () {
    $named = Category::factory()->create(['name' => 'DISTINCT_NAV_NAME', 'slug' => 'distinct-nav-name']);
    $overridden = Category::factory()->create(['name' => 'DISTINCT_NAV_IGNORED', 'slug' => 'distinct-nav-ignored']);

    storeHeaderLinks([
        ['type' => 'category', 'category_id' => $named->id, 'label' => null],
        ['type' => 'category', 'category_id' => $overridden->id, 'label' => 'DISTINCT_NAV_OVERRIDE'],
    ]);

    $response = $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee(route('categories.show', $named), escape: false)
        ->assertSee(route('categories.show', $overridden), escape: false)
        ->assertSee('DISTINCT_NAV_NAME')
        ->assertSee('DISTINCT_NAV_OVERRIDE')
        ->assertDontSee('DISTINCT_NAV_IGNORED');

    // Once in the desktop navigation, once in the mobile overlay.
    expect(substr_count($response->getContent(), route('categories.show', $named)))->toBe(2);
});

test('the header renders the mobile menu when links are configured', function () {
    storeHeaderLinks([
        ['type' => 'category', 'category_id' => Category::factory()->create()->id, 'label' => null],
    ]);

    $this->get(route('home'))
        ->assertSee('id="mobile-menu"', escape: false)
        ->assertSee('aria-label="Open menu"', escape: false)
        ->assertSee('aria-label="Close menu"', escape: false)
        ->assertSee('x-data="{ open: false }"', escape: false);
});

test('the header hides the menu button and overlay when no links are configured', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('id="mobile-menu"', escape: false)
        ->assertDontSee('aria-label="Open menu"', escape: false)
        ->assertSee('aria-label="Search"', escape: false);
});

test('the header drops links whose category was deleted', function () {
    $category = Category::factory()->create(['name' => 'DISTINCT_NAV_DELETED', 'slug' => 'distinct-nav-deleted']);

    storeHeaderLinks([['type' => 'category', 'category_id' => $category->id, 'label' => null]]);

    $url = route('categories.show', $category);

    $category->delete();

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('DISTINCT_NAV_DELETED')
        ->assertDontSee($url, escape: false);
});
