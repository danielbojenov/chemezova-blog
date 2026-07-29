<?php

namespace App\Support\Products;

use App\Enums\RankingOrder;
use App\Models\Article;
use App\Models\Product;

/**
 * Keeps the article_product pivot in step with the article's product card blocks.
 *
 * Ranks are derived from each card's position among the product cards rather than
 * entered by the author, so deleting or reordering a card can never leave a gap or
 * a duplicate in the numbering. The rank is still stored on the pivot so a ranking
 * can be queried without parsing the content JSON.
 *
 * `ranksFor()` is the single source of truth: the view calls it too, so the number
 * printed on a card and the number stored on the pivot cannot drift apart.
 */
final readonly class ArticleProductSyncer
{
    public function sync(Article $article): void
    {
        $pivot = array_map(
            fn (int $rank): array => ['rank' => $rank],
            self::ranksFor($article),
        );

        $article->products()->sync($pivot);
    }

    /**
     * Map of product id to the rank its card should display in this article.
     *
     * Cards pointing at a product that no longer exists are dropped without
     * consuming a rank, so the numbering stays contiguous.
     *
     * @return array<int, int>
     */
    public static function ranksFor(Article $article): array
    {
        $productIds = self::productIdsIn($article->content ?? []);

        if ($productIds === []) {
            return [];
        }

        $existing = Product::query()->whereIn('id', $productIds)->pluck('id')->all();

        $ranked = array_values(array_filter(
            $productIds,
            fn (int $id): bool => in_array($id, $existing, true),
        ));

        $order = $article->ranking_order ?? RankingOrder::Descending;
        $total = count($ranked);

        $ranks = [];

        foreach ($ranked as $index => $productId) {
            $ranks[$productId] = $order->rankFor($index, $total);
        }

        return $ranks;
    }

    /**
     * The product ids referenced by the article's product card blocks, in the
     * order the cards appear. Duplicates are dropped — a product may only be
     * ranked once per article, and the pivot's composite key enforces that.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<int>
     */
    public static function productIdsIn(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            $id = self::productIdOf($block);

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The product id a single block references, or null when it is not a product card.
     */
    public static function productIdOf(mixed $block): ?int
    {
        if (! is_array($block) || ($block['type'] ?? null) !== 'productCard') {
            return null;
        }

        $id = $block['data']['product_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }
}
