<?php

namespace App\Enums;

enum ImageVariant: string
{
    case Original = 'original';
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Thumbnail = 'thumbnail';

    /**
     * Filename suffix appended to the base name for this variant.
     */
    public function fileSuffix(): string
    {
        return match ($this) {
            self::Original => '',
            self::Desktop => '-desktop',
            self::Mobile => '-mobile',
            self::Thumbnail => '-thumbnail',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Original => 'Original',
            self::Desktop => 'Desktop',
            self::Mobile => 'Mobile',
            self::Thumbnail => 'Thumbnail',
        };
    }
}
