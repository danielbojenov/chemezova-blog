<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationLinkType: string implements HasLabel
{
    case Category = 'category';

    /**
     * Resolve a case from either its stored value or the case itself, or null.
     *
     * A `Select` backed by an enum reads and writes the case rather than its value, so
     * both forms turn up depending on whether the link came from the form or storage.
     */
    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) ? self::tryFrom($value) : null;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Category => 'Category',
        };
    }
}
