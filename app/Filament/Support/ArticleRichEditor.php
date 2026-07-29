<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

/**
 * The rich editor used for all article content HTML (rich text blocks and FAQ
 * answers), so every editor carries the affiliate link toolbar button.
 */
class ArticleRichEditor
{
    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->plugins([AffiliateLinkPlugin::make()])
            ->enableToolbarButtons(['affiliateLink']);
    }
}
