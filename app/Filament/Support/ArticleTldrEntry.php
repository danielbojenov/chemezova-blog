<?php

namespace App\Filament\Support;

use Filament\Infolists\Components\Entry;

/**
 * Read-only infolist entry that renders the article `tldr` rich text. A custom
 * view is used instead of `TextEntry::make('tldr')->html()` so the summary picks
 * up the same preview typography as the content blocks, and so an empty TLDR
 * still shows the block with a placeholder message.
 */
class ArticleTldrEntry extends Entry
{
    protected string $view = 'filament.infolists.article-tldr';
}
