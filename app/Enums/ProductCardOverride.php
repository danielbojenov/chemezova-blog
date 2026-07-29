<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How one field on a product card resolves against the catalog: take the
 * product's own value, replace it for this article, or drop it from the card.
 * Shared by the retailer links and the description so the two behave alike; the
 * field's own label supplies the context, which is why these read generically.
 *
 * Stored inside the article's content JSON rather than a column, so it is read
 * back as a plain string — see resolve().
 */
enum ProductCardOverride: string implements HasLabel
{
    case Inherit = 'inherit';
    case Custom = 'custom';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inherit => 'Take from the catalog',
            self::Custom => 'Override for this article',
            self::None => 'Hide on this card',
        };
    }

    /**
     * Resolve block state, which may hold the enum, its backing value, or nothing
     * at all on cards saved before the field existed.
     */
    public static function resolve(mixed $state): self
    {
        if ($state instanceof self) {
            return $state;
        }

        return is_string($state) ? (self::tryFrom($state) ?? self::Inherit) : self::Inherit;
    }
}
