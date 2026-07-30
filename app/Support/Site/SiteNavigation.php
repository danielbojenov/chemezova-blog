<?php

namespace App\Support\Site;

use App\Enums\NavigationLinkType;
use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Turns the stored navigation settings into links the templates can render.
 */
final class SiteNavigation
{
    /**
     * The top navigation bar links, in the configured order.
     *
     * Entries pointing at a category that has since been deleted are dropped, so a
     * deleted category can never break the header.
     *
     * @return list<NavigationLink>
     */
    public static function headerLinks(): array
    {
        $links = SitePageSettings::current()->headerLinks();

        $categories = self::categories($links);

        return collect($links)
            ->map(fn (array $link): ?NavigationLink => match (NavigationLinkType::tryFrom($link['type'])) {
                NavigationLinkType::Category => self::categoryLink($link, $categories),
                default => null,
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Every category referenced by the links, keyed by id, in a single query.
     *
     * @param  list<array{type: string, category_id: int|null, label: string|null}>  $links
     * @return Collection<int, Category>
     */
    private static function categories(array $links): Collection
    {
        $ids = collect($links)
            ->where('type', NavigationLinkType::Category->value)
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Category::query()->whereKey($ids)->get()->keyBy('id');
    }

    /**
     * @param  array{type: string, category_id: int|null, label: string|null}  $link
     * @param  Collection<int, Category>  $categories
     */
    private static function categoryLink(array $link, Collection $categories): ?NavigationLink
    {
        $category = $categories->get($link['category_id']);

        if (! $category instanceof Category) {
            return null;
        }

        return new NavigationLink(
            label: $link['label'] ?? $category->name,
            url: route('categories.show', $category),
            isCurrent: request()->routeIs('categories.show')
                && request()->route('category') instanceof Category
                && request()->route('category')->is($category),
        );
    }
}
