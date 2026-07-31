<?php

namespace App\Support\Articles;

/**
 * Reduces authored rich text to the words a reader sees.
 *
 * Rich editors store HTML, but several things the site does with that copy — counting it
 * for a reading estimate, shortening it into a card's lead-in — are about the text alone.
 * Doing the reduction in one place keeps those callers agreeing on what "the text" is.
 */
final class PlainText
{
    /**
     * The visible text of an HTML fragment, with whitespace runs collapsed.
     *
     * Tags become a space rather than being dropped, so that "</p><p>" does not weld the
     * last word of one paragraph to the first word of the next; strip_tags() then catches
     * anything the pattern left behind, such as an unterminated tag.
     */
    public static function from(?string $html): string
    {
        if (! is_string($html) || $html === '') {
            return '';
        }

        $text = html_entity_decode(
            strip_tags((string) preg_replace('/<[^>]*>/', ' ', $html)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
