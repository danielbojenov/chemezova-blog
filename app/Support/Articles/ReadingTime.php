<?php

namespace App\Support\Articles;

use App\Support\Site\SiteGeneralSettings;

/**
 * Estimates how long an article takes to read, from the text its content blocks hold.
 *
 * Counted in characters rather than words because the site's reading speed is one editor
 * setting rather than a per-language table: words vary in length far more than characters
 * do, so a single characters-per-minute figure stays roughly right across the mix of
 * plain prose and nutrient names the articles carry.
 */
class ReadingTime
{
    /**
     * The number of minutes the article's content blocks represent, never less than one:
     * a byline reading "0 min read" is worse than an obvious rounding up.
     *
     * The reading speed is resolved from the site settings when it is not passed, so a
     * caller rendering several articles can read the setting once and hand it down rather
     * than paying for a query per article.
     *
     * @param  array<int, mixed>|null  $content
     */
    public static function minutes(?array $content, ?int $charactersPerMinute = null): int
    {
        $charactersPerMinute ??= SiteGeneralSettings::current()->charactersPerMinute();

        return max(1, (int) ceil(self::characters($content) / max(1, $charactersPerMinute)));
    }

    /**
     * Total characters of readable text across the content blocks.
     *
     * Only the blocks a reader actually reads through are counted: headings, body copy
     * and FAQ pairs. Image captions and product cards are skipped — cards are scanned for
     * a price and a button, not read start to finish, and counting them would inflate the
     * estimate most on exactly the TOP 10 articles that are quickest to skim.
     *
     * @param  array<int, mixed>|null  $content
     */
    public static function characters(?array $content): int
    {
        $characters = 0;

        foreach ($content ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            $characters += match ($block['type'] ?? null) {
                'h2' => self::lengthOf($data['text'] ?? null),
                'richText' => self::lengthOf($data['content'] ?? null),
                'faq' => self::faqLength($data),
                default => 0,
            };
        }

        return $characters;
    }

    /**
     * The heading plus every question and answer in an FAQ block.
     *
     * @param  array<string, mixed>  $data
     */
    private static function faqLength(array $data): int
    {
        $length = self::lengthOf($data['heading'] ?? null);

        foreach ($data['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $length += self::lengthOf($item['question'] ?? null) + self::lengthOf($item['answer'] ?? null);
        }

        return $length;
    }

    /**
     * Characters of visible text in an authored value, markup and indentation excluded.
     */
    private static function lengthOf(mixed $value): int
    {
        return mb_strlen(PlainText::from(is_string($value) ? $value : null));
    }
}
