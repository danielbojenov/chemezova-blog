<?php

namespace App\Support\AffiliateLinks;

use App\Models\AffiliateLink;
use App\Models\Article;

/**
 * Keeps the affiliate_link_article pivot in step with the article's content by
 * scanning every rich text HTML field for internal /go/{slug} redirect hrefs.
 */
final readonly class ArticleAffiliateLinkSyncer
{
    /**
     * Matches relative and absolute /go/ hrefs; capture group 1 is the slug.
     */
    private const string HREF_PATTERN = '~href="(?:https?://[^"/]+)?/go/([^"/?#]+)~';

    public function sync(Article $article): void
    {
        $slugs = $this->extractSlugs($article->content ?? []);

        $ids = $slugs === []
            ? []
            : AffiliateLink::query()->whereIn('slug', $slugs)->pluck('id')->all();

        $article->affiliateLinks()->sync($ids);
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
            default => [],
        };
    }
}
