<?php

use App\Models\Category;
use App\Support\Site\SiteNavigation;
use Illuminate\Support\Facades\DB;

test('no links are resolved when nothing is configured', function () {
    expect(SiteNavigation::headerLinks())->toBe([]);
});

test('a category link falls back to the category name', function () {
    $category = Category::factory()->create(['name' => 'Vitamins', 'slug' => 'vitamins']);

    storeHeaderLinks([['type' => 'category', 'category_id' => $category->id, 'label' => null]]);

    $links = SiteNavigation::headerLinks();

    expect($links)->toHaveCount(1)
        ->and($links[0]->label)->toBe('Vitamins')
        ->and($links[0]->url)->toBe(route('categories.show', $category))
        ->and($links[0]->isCurrent)->toBeFalse();
});

test('a label override wins over the category name', function () {
    $category = Category::factory()->create(['name' => 'Vitamins']);

    storeHeaderLinks([['type' => 'category', 'category_id' => $category->id, 'label' => 'All vitamins']]);

    expect(SiteNavigation::headerLinks()[0]->label)->toBe('All vitamins');
});

test('the configured order is preserved', function () {
    $first = Category::factory()->create(['name' => 'Zinc']);
    $second = Category::factory()->create(['name' => 'Magnesium']);

    storeHeaderLinks([
        ['type' => 'category', 'category_id' => $first->id, 'label' => null],
        ['type' => 'category', 'category_id' => $second->id, 'label' => null],
    ]);

    expect(collect(SiteNavigation::headerLinks())->pluck('label')->all())->toBe(['Zinc', 'Magnesium']);
});

test('links to deleted categories and unknown types are dropped', function () {
    $category = Category::factory()->create(['name' => 'Vitamins']);

    storeHeaderLinks([
        ['type' => 'category', 'category_id' => $category->id, 'label' => null],
        ['type' => 'category', 'category_id' => $category->id + 999, 'label' => null],
        ['type' => 'category', 'category_id' => null, 'label' => 'Orphan'],
        ['type' => 'page', 'category_id' => null, 'label' => 'About'],
    ]);

    expect(collect(SiteNavigation::headerLinks())->pluck('label')->all())->toBe(['Vitamins']);
});

test('the link for the category being viewed is marked as current', function () {
    $vitamins = Category::factory()->create(['name' => 'Vitamins', 'slug' => 'vitamins']);
    $minerals = Category::factory()->create(['name' => 'Minerals', 'slug' => 'minerals']);

    storeHeaderLinks([
        ['type' => 'category', 'category_id' => $vitamins->id, 'label' => null],
        ['type' => 'category', 'category_id' => $minerals->id, 'label' => null],
    ]);

    $this->get(route('categories.show', $vitamins))
        ->assertSuccessful()
        ->assertSee('aria-current="page"', escape: false);
});

test('resolving the links only queries the categories table once', function () {
    $categories = Category::factory()->count(3)->create();

    storeHeaderLinks($categories->map(fn (Category $category): array => [
        'type' => 'category',
        'category_id' => $category->id,
        'label' => null,
    ])->all());

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(SiteNavigation::headerLinks())->toHaveCount(3)
        // One read of the settings row, one read of the categories.
        ->and($queries)->toBe(2);
});
