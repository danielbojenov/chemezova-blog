<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The dosage form a supplement is sold in.
 */
enum SupplementForm: string implements HasLabel
{
    case Capsule = 'capsule';
    case Tablet = 'tablet';
    case Softgel = 'softgel';
    case Gummy = 'gummy';
    case Powder = 'powder';
    case Liquid = 'liquid';
    case Drops = 'drops';
    case Spray = 'spray';
    case Chewable = 'chewable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Capsule => 'Capsule',
            self::Tablet => 'Tablet',
            self::Softgel => 'Softgel',
            self::Gummy => 'Gummy',
            self::Powder => 'Powder',
            self::Liquid => 'Liquid',
            self::Drops => 'Drops',
            self::Spray => 'Spray',
            self::Chewable => 'Chewable',
        };
    }
}
