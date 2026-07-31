<?php

namespace App\Filament\Support;

use Filament\Infolists\Components\Entry;

/**
 * Read-only infolist entry that renders a `content` Builder's blocks as a styled
 * reading preview. Filament has no built-in Builder entry, so the JSON blocks are
 * iterated and rendered in the Blade view.
 *
 * Serves both articles and pages. The view handles every block type either can hold;
 * pages simply never reach the faq and product card branches.
 */
class ContentEntry extends Entry
{
    protected string $view = 'filament.infolists.content';
}
