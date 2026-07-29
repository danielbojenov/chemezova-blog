<?php

namespace App\Filament\Support;

use Filament\Infolists\Components\Entry;

/**
 * Read-only infolist entry that renders the article `content` Builder blocks
 * (rich text, image, faq) as a styled reading preview. Filament has no built-in
 * Builder entry, so the JSON blocks are iterated and rendered in the Blade view.
 */
class ArticleContentEntry extends Entry
{
    protected string $view = 'filament.infolists.article-content';
}
