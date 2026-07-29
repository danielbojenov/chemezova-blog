<?php

namespace App\Support\AffiliateLinks;

use App\Enums\ProductCardOverride;
use App\Models\AffiliateLink;
use App\Models\Article;
use App\Models\Product;

/**
 * Keeps the affiliate_link_article pivot in step with the article's content.
 *
 * Links reach an article two ways: written into rich text as /go/{slug} hrefs, and
 * attached to a product card. Product cards store link ids rather than hrefs, so
 * both sources are collected here — otherwise every retailer link shown on a
 * product card would be missing from placement tracking. A card's description is
 * scanned only when it overrides the catalog, since an inherited one belongs to
 * the product rather than to this article.
 */
final readonly class ArticleAffiliateLinkSyncer
{
    /**
     * Matches relative and absolute /go/ hrefs; capture group 1 is the slug.
     */
    private const string HREF_PATTERN = '~href="(?:https?://[^"/]+)?/go/([^"/?#]+)~';

    public function sync(Article $article): void
    {
        $blocks = $article->content ?? [];

        $slugs = $this->extractSlugs($blocks);

        $ids = $slugs === []
            ? []
            : AffiliateLink::query()->whereIn('slug', $slugs)->pluck('id')->all();

        $ids = [...$ids, ...$this->productCardLinkIds($blocks)];

        $article->affiliateLinks()->sync(array_values(array_unique($ids)));
    }

    /**
     * Collect the unique affiliate link slugs referenced by the article's content
     * blocks. Slugs without a matching registry entry are ignored by sync().
     *
     * @param  array<int, mixed>  $blocks
     * @return list<string>
     */
    private function extractSlugs(array $blocks): array
    {
        $slugs = [];

        foreach ($blocks as $block) {
            foreach ($this->richTextFieldsOf($block) as $html) {
                if (preg_match_all(self::HREF_PATTERN, $html, $matches)) {
                    $slugs = [...$slugs, ...$matches[1]];
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Link ids contributed by product cards: the card's own overrides when it
     * customises them, the product's default links when it inherits, and nothing
     * when the card hides them.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<int>
     */
    private function productCardLinkIds(array $blocks): array
    {
        $ids = [];
        $inheritingProductIds = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'productCard') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $mode = ProductCardOverride::resolve($data['links_mode'] ?? null);

            if ($mode === ProductCardOverride::Custom) {
                foreach (is_array($data['affiliate_link_ids'] ?? null) ? $data['affiliate_link_ids'] : [] as $id) {
                    if (is_numeric($id)) {
                        $ids[] = (int) $id;
                    }
                }
            }

            if ($mode === ProductCardOverride::Inherit && is_numeric($data['product_id'] ?? null)) {
                $inheritingProductIds[] = (int) $data['product_id'];
            }
        }

        if ($inheritingProductIds !== []) {
            $ids = [
                ...$ids,
                ...Product::query()
                    ->whereIn('id', array_unique($inheritingProductIds))
                    ->with('affiliateLinks:id')
                    ->get()
                    ->flatMap(fn (Product $product): array => $product->affiliateLinks->pluck('id')->all())
                    ->all(),
            ];
        }

        // Only links still present in the registry may be attached.
        return $ids === []
            ? []
            : array_values(AffiliateLink::query()->whereIn('id', array_unique($ids))->pluck('id')->all());
    }

    /**
     * @return list<string> every rich text HTML string within the block
     */
    private function richTextFieldsOf(mixed $block): array
    {
        if (! is_array($block)) {
            return [];
        }

        $data = $block['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        return match ($block['type'] ?? null) {
            'richText' => is_string($data['content'] ?? null) ? [$data['content']] : [],
            'faq' => array_values(array_filter(
                array_map(fn (mixed $item): mixed => is_array($item) ? ($item['answer'] ?? null) : null, is_array($data['items'] ?? null) ? $data['items'] : []),
                is_string(...),
            )),
            /**
             * Only an overriding description is article-authored and therefore
             * scanned. An inherited one belongs to the product, and its links are
             * the product's placements rather than this article's.
             */
            'productCard' => ProductCardOverride::resolve($data['description_mode'] ?? null) === ProductCardOverride::Custom
                && is_string($data['description'] ?? null)
                    ? [$data['description']]
                    : [],
            default => [],
        };
    }
}
