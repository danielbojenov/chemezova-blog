<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

/**
 * The rich editors used for all article content HTML, so every editor carries the
 * affiliate link toolbar button.
 *
 * Two variants differ only in the heading buttons they offer. Section headings are
 * a dedicated `h2` builder block (see {@see HeadingBlock}), so no editor exposes
 * H2: body copy gets H3/H4 for sub-headings, and prose nested inside an already
 * headed block gets no headings at all.
 */
class ArticleRichEditor
{
    /**
     * Body copy: the article's rich text blocks.
     */
    public static function make(string $name): RichEditor
    {
        return self::base($name, ['h3', 'h4']);
    }

    /**
     * Short prose nested inside a block that already provides its own heading:
     * FAQ answers and the product card description override.
     */
    public static function withoutHeadings(string $name): RichEditor
    {
        return self::base($name, []);
    }

    /**
     * The toolbar is set explicitly rather than through `enableToolbarButtons()`,
     * which only appends to Filament's defaults and so can neither drop H2 nor
     * place H4 alongside H3.
     *
     * @param  array<int, string>  $headingButtons
     */
    private static function base(string $name, array $headingButtons): RichEditor
    {
        return RichEditor::make($name)
            ->plugins([AffiliateLinkPlugin::make()])
            ->toolbarButtons(array_values(array_filter([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                $headingButtons,
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
                ['affiliateLink'],
            ])));
    }
}
