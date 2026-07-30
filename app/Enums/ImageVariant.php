<?php

namespace App\Enums;

use Illuminate\Support\Str;

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

    /**
     * Rewrite a stored image path so it points at this variant instead.
     *
     * Expects the Original path as stored on the record (no variant suffix);
     * anything that is not a converted `.webp` — an upload still sitting in a
     * tmp directory — is returned untouched.
     */
    public function pathFor(string $path): string
    {
        return str_ends_with($path, '.webp')
            ? Str::replaceLast('.webp', $this->fileSuffix().'.webp', $path)
            : $path;
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
