<?php

namespace App\Support\Articles;

use Illuminate\Support\Str;

class ArticleHeadings
{
    /**
     * The anchor id used when a heading slugifies to nothing. `Str::slug()`
     * transliterates Cyrillic, but a heading made only of punctuation, emoji, or
     * an unhandled script leaves nothing behind.
     */
    private const FALLBACK_SLUG = 'section';

    /**
     * Extract the article outline from the content blocks: every `h2` block, in
     * document order, with the anchor id its rendered heading carries. This is the
     * single source of truth for those ids — the content preview stamps them onto
     * the `<h2>` tags, and a table of contents built from the same call is
     * therefore guaranteed to link to anchors that exist.
     *
     * Keys are the block's position in `$content` (not its array key, so they line
     * up with a Blade `$loop->index`) and let a renderer iterating the blocks look
     * up the current one; a table of contents wants `array_values()`.
     *
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>|null  $content
     * @return array<int, array{id: string, text: string}>
     */
    public static function extract(?array $content): array
    {
        $headings = [];
        $slugCounts = [];
        $index = -1;

        foreach ($content ?? [] as $block) {
            $index++;

            if (($block['type'] ?? null) !== 'h2') {
                continue;
            }

            $text = $block['data']['text'] ?? null;

            if (blank($text)) {
                continue;
            }

            $slug = Str::slug($text) ?: self::FALLBACK_SLUG;

            $slugCounts[$slug] = ($slugCounts[$slug] ?? 0) + 1;

            $headings[$index] = [
                'id' => $slugCounts[$slug] > 1 ? $slug.'-'.$slugCounts[$slug] : $slug,
                'text' => (string) $text,
            ];
        }

        return $headings;
    }
}
