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
     * The featured block stores the tag's *slug*, not its id: the out-of-the-box default
     * has to name a tag that does not exist yet, and a slug that resolves to nothing is
     * exactly the "render no featured block" case the home page already has to handle.
     *
     * @return array{navigation: array{header: list<array{type: string, category_id: int|null, label: string|null}>}, home: array{featured: array{tag: string, title: string, button_label: string}}}
     */
    public static function defaults(): array
    {
        return [
            'navigation' => [
                'header' => [],
            ],
            'home' => [
                'featured' => [
                    'tag' => 'featured',
                    'title' => 'Featured',
                    'button_label' => 'Read the article',
                ],
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
     * The slug of the tag whose articles fill the home page's featured block.
     */
    public function featuredTagSlug(): string
    {
        return $this->featuredValue('tag');
    }

    /**
     * The label the featured tag is shown under, first in the block's tag line.
     */
    public function featuredTagTitle(): string
    {
        return $this->featuredValue('title');
    }

    /**
     * The call to action on the featured block.
     */
    public function featuredButtonLabel(): string
    {
        return $this->featuredValue('button_label');
    }

    /**
     * Read one featured setting, falling back to its default when the stored value is
     * blank or is not a string. Blank is treated as absent rather than as a deliberate
     * empty label: the block has no sensible rendering without a tag or a button.
     */
    private function featuredValue(string $key): string
    {
        $value = $this->values['home']['featured'][$key] ?? null;

        return filled($value) && is_string($value)
            ? trim($value)
            : self::defaults()['home']['featured'][$key];
    }

    /**
     * Flatten to form state keys.
     *
     * @return array{header_links: list<array{type: string, category_id: int|null, label: string|null}>, featured_tag: string, featured_title: string, featured_button_label: string}
     */
    public function toFormData(): array
    {
        return [
            'header_links' => $this->headerLinks(),
            'featured_tag' => $this->featuredTagSlug(),
            'featured_title' => $this->featuredTagTitle(),
            'featured_button_label' => $this->featuredButtonLabel(),
        ];
    }

    /**
     * Rebuild the nested storage array from flat form state.
     *
     * Repeater state arrives keyed by item UUID, so it is re-indexed here to store a
     * clean ordered list.
     *
     * @param  array<string, mixed>  $data
     * @return array{navigation: array{header: list<array{type: string, category_id: int|null, label: string|null}>}, home: array{featured: array{tag: string, title: string, button_label: string}}}
     */
    public static function fromFormData(array $data): array
    {
        $links = is_array($data['header_links'] ?? null) ? $data['header_links'] : [];
        $featuredDefaults = self::defaults()['home']['featured'];

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
            'home' => [
                'featured' => [
                    'tag' => self::trimmedOr($data['featured_tag'] ?? null, $featuredDefaults['tag']),
                    'title' => self::trimmedOr($data['featured_title'] ?? null, $featuredDefaults['title']),
                    'button_label' => self::trimmedOr($data['featured_button_label'] ?? null, $featuredDefaults['button_label']),
                ],
            ],
        ];
    }

    /**
     * Trim submitted form state, substituting the default when it is blank.
     */
    private static function trimmedOr(mixed $value, string $default): string
    {
        return filled($value) ? trim((string) $value) : $default;
    }
}
