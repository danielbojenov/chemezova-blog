<?php

namespace App\Support\Site;

use App\Enums\NavigationLinkType;
use App\Models\Setting;

/**
 * Editor-configurable content for the public pages (home, category, tag, article).
 *
 * The header navigation lives here; controls for the individual page sections land as
 * entries in {@see self::defaults()} plus fields on the settings page as each section
 * of the frontend is wired up.
 */
final readonly class SitePageSettings
{
    public const string SETTINGS_KEY = 'site.pages';

    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values) {}

    public static function current(): self
    {
        $stored = Setting::getValue(self::SETTINGS_KEY, []);

        return new self(array_replace_recursive(self::defaults(), is_array($stored) ? $stored : []));
    }

    /**
     * Nested defaults, merged underneath whatever is stored.
     *
     * The header link list must default to an empty array: `current()` merges with
     * `array_replace_recursive`, which merges lists index by index, so a non-empty
     * default would leak its tail into shorter stored lists.
     *
     * @return array{navigation: array{header: list<array{type: string, category_id: int|null, label: string|null}>}}
     */
    public static function defaults(): array
    {
        return [
            'navigation' => [
                'header' => [],
            ],
        ];
    }

    /**
     * The links shown in the top navigation bar, in the configured order.
     *
     * Stored JSON is not trusted to still match the shape the settings page wrote, so
     * every entry is coerced back into it here. Unrecognised types are left in place for
     * the renderer to drop.
     *
     * @return list<array{type: string, category_id: int|null, label: string|null}>
     */
    public function headerLinks(): array
    {
        $links = $this->values['navigation']['header'] ?? [];

        if (! is_array($links)) {
            return [];
        }

        return array_values(array_map(
            fn (array $link): array => [
                'type' => is_string($link['type'] ?? null) ? $link['type'] : '',
                'category_id' => filled($link['category_id'] ?? null) ? (int) $link['category_id'] : null,
                'label' => filled($link['label'] ?? null) ? trim((string) $link['label']) : null,
            ],
            array_filter($links, 'is_array'),
        ));
    }

    /**
     * Flatten to form state keys.
     *
     * @return array{header_links: list<array{type: string, category_id: int|null, label: string|null}>}
     */
    public function toFormData(): array
    {
        return [
            'header_links' => $this->headerLinks(),
        ];
    }

    /**
     * Rebuild the nested storage array from flat form state.
     *
     * Repeater state arrives keyed by item UUID, so it is re-indexed here to store a
     * clean ordered list.
     *
     * @param  array<string, mixed>  $data
     * @return array{navigation: array{header: list<array{type: string, category_id: int|null, label: string|null}>}}
     */
    public static function fromFormData(array $data): array
    {
        $links = is_array($data['header_links'] ?? null) ? $data['header_links'] : [];

        return [
            'navigation' => [
                'header' => array_values(array_map(
                    fn (array $link): array => [
                        'type' => (NavigationLinkType::tryFromMixed($link['type'] ?? null) ?? NavigationLinkType::Category)->value,
                        'category_id' => filled($link['category_id'] ?? null) ? (int) $link['category_id'] : null,
                        'label' => filled($link['label'] ?? null) ? trim((string) $link['label']) : null,
                    ],
                    array_filter($links, 'is_array'),
                )),
            ],
        ];
    }
}
