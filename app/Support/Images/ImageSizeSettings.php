<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use App\Models\Setting;

/**
 * Per-variant maximum widths (in pixels) for landscape and portrait images.
 */
final readonly class ImageSizeSettings
{
    public const string SETTINGS_KEY = 'images.sizes';

    /**
     * @param  array<string, array{landscape: int, portrait: int}>  $widths
     */
    public function __construct(private array $widths)
    {
    }

    public static function current(): self
    {
        $stored = Setting::getValue(self::SETTINGS_KEY, []);

        return new self(array_replace_recursive(self::defaults(), is_array($stored) ? $stored : []));
    }

    /**
     * @return array<string, array{landscape: int, portrait: int}>
     */
    public static function defaults(): array
    {
        return [
            ImageVariant::Original->value => ['landscape' => 2560, 'portrait' => 1440],
            ImageVariant::Desktop->value => ['landscape' => 1920, 'portrait' => 1080],
            ImageVariant::Mobile->value => ['landscape' => 768, 'portrait' => 480],
            ImageVariant::Thumbnail->value => ['landscape' => 320, 'portrait' => 240],
        ];
    }

    public function maxWidth(ImageVariant $variant, bool $landscape): int
    {
        return (int) $this->widths[$variant->value][$landscape ? 'landscape' : 'portrait'];
    }

    /**
     * Flatten to form state keys such as "desktop_landscape".
     *
     * @return array<string, int>
     */
    public function toFormData(): array
    {
        $data = [];

        foreach (ImageVariant::cases() as $variant) {
            $data["{$variant->value}_landscape"] = (int) $this->widths[$variant->value]['landscape'];
            $data["{$variant->value}_portrait"] = (int) $this->widths[$variant->value]['portrait'];
        }

        return $data;
    }

    /**
     * Rebuild the nested storage array from flat form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{landscape: int, portrait: int}>
     */
    public static function fromFormData(array $data): array
    {
        $widths = [];

        foreach (ImageVariant::cases() as $variant) {
            $widths[$variant->value] = [
                'landscape' => (int) ($data["{$variant->value}_landscape"] ?? self::defaults()[$variant->value]['landscape']),
                'portrait' => (int) ($data["{$variant->value}_portrait"] ?? self::defaults()[$variant->value]['portrait']),
            ];
        }

        return $widths;
    }
}
