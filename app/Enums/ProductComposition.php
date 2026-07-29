<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How many active ingredients a supplement combines.
 */
enum ProductComposition: string implements HasColor, HasLabel
{
    case Single = 'single';
    case Complex = 'complex';
    case Multivitamin = 'multivitamin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Mono-supplement',
            self::Complex => '2–3 components',
            self::Multivitamin => 'Multivitamin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'info',
            self::Complex => 'warning',
            self::Multivitamin => 'success',
        };
    }
}
