<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The direction product cards are numbered in an article. Ranks are always
 * derived from card position, never entered by the author, so removing a card
 * can never leave a gap in the numbering.
 */
enum RankingOrder: string implements HasLabel
{
    case Ascending = 'ascending';
    case Descending = 'descending';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ascending => 'Ascending (1, 2, 3 …)',
            self::Descending => 'Descending (… 3, 2, 1)',
        };
    }

    /**
     * The rank shown for the card at $index of $total product cards.
     */
    public function rankFor(int $index, int $total): int
    {
        return match ($this) {
            self::Ascending => $index + 1,
            self::Descending => $total - $index,
        };
    }
}
