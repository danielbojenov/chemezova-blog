<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Converts a stored source image into WebP variants sized per orientation-aware settings.
 */
final readonly class ImageConverter
{
    private const int WEBP_QUALITY = 82;

    public function __construct(private ImageManager $manager)
    {
    }

    /**
     * Convert the source file into "{base}.webp" plus suffixed variants inside $directory.
     *
     * @return array<string, string> map of variant value to the stored file path
     */
    public function convert(Filesystem $disk, string $sourcePath, string $directory, string $baseName, ImageSizeSettings $sizes): array
    {
        $image = $this->manager->decodeBinary($disk->get($sourcePath));
        $landscape = $image->width() >= $image->height();

        $paths = [];

        foreach (ImageVariant::cases() as $variant) {
            $copy = clone $image;
            $copy->scaleDown(width: $sizes->maxWidth($variant, $landscape));

            $path = "{$directory}/{$baseName}{$variant->fileSuffix()}.webp";
            $disk->put($path, (string) $copy->encode(new WebpEncoder(quality: self::WEBP_QUALITY)));

            $paths[$variant->value] = $path;
        }

        return $paths;
    }
}
