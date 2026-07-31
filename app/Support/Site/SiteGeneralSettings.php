<?php

namespace App\Support\Site;

use App\Models\Setting;
use App\Support\Images\SettingsImageProcessor;

/**
 * Site-wide editorial settings, as opposed to the per-page content in
 * {@see SitePageSettings}: the byline the site publishes under, and the reading speed
 * behind every "N min read" estimate.
 *
 * The site publishes under a single byline, so the author is configuration rather than a
 * relation on each article. Should articles ever need their own authors, this becomes the
 * fallback rather than the only source.
 */
final readonly class SiteGeneralSettings
{
    public const string SETTINGS_KEY = 'site.general';

    /**
     * Slowest and fastest reading speeds accepted, matching the bounds the settings page
     * enforces. Stored JSON is re-clamped on read so a value written around the form —
     * by a seeder, or by an earlier version of these settings — cannot divide a reading
     * estimate by zero or flatten every article to one minute.
     */
    public const int MIN_CHARACTERS_PER_MINUTE = 200;

    public const int MAX_CHARACTERS_PER_MINUTE = 5000;

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
     * 1,000 characters a minute is roughly 200 words a minute, the usual figure for adult
     * reading of non-technical prose, at about five characters plus a space per word.
     *
     * @return array{author: array{name: string|null, page_id: int|null, avatar: string|null}, reading: array{characters_per_minute: int}}
     */
    public static function defaults(): array
    {
        return [
            'author' => [
                'name' => 'Elena Chemezova',
                'page_id' => null,
                'avatar' => null,
            ],
            'reading' => [
                'characters_per_minute' => 1000,
            ],
        ];
    }

    /**
     * The name shown in article bylines, or null when the byline carries no name.
     */
    public function authorName(): ?string
    {
        $name = $this->values['author']['name'] ?? null;

        return filled($name) ? trim((string) $name) : null;
    }

    /**
     * The page the author's name links to, or null when the name is not a link.
     */
    public function authorPageId(): ?int
    {
        $id = $this->values['author']['page_id'] ?? null;

        return filled($id) ? (int) $id : null;
    }

    /**
     * The stored path of the author's avatar — the Original variant, as written by
     * {@see SettingsImageProcessor} — or null when none is set.
     */
    public function authorAvatarPath(): ?string
    {
        $path = $this->values['author']['avatar'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function charactersPerMinute(): int
    {
        return self::clampReadingSpeed($this->values['reading']['characters_per_minute'] ?? null);
    }

    /**
     * Flatten to form state keys.
     *
     * @return array{author_name: string|null, author_page_id: int|null, author_avatar: string|null, characters_per_minute: int}
     */
    public function toFormData(): array
    {
        return [
            'author_name' => $this->authorName(),
            'author_page_id' => $this->authorPageId(),
            'author_avatar' => $this->authorAvatarPath(),
            'characters_per_minute' => $this->charactersPerMinute(),
        ];
    }

    /**
     * Rebuild the nested storage array from flat form state.
     *
     * The avatar arrives as whatever path the upload field holds; converting a freshly
     * staged upload into its final location is the caller's job, so that the file work
     * stays out of a value object and can be skipped when nothing was uploaded.
     *
     * @param  array<string, mixed>  $data
     * @return array{author: array{name: string|null, page_id: int|null, avatar: string|null}, reading: array{characters_per_minute: int}}
     */
    public static function fromFormData(array $data): array
    {
        return [
            'author' => [
                'name' => filled($data['author_name'] ?? null) ? trim((string) $data['author_name']) : null,
                'page_id' => filled($data['author_page_id'] ?? null) ? (int) $data['author_page_id'] : null,
                'avatar' => filled($data['author_avatar'] ?? null) ? (string) $data['author_avatar'] : null,
            ],
            'reading' => [
                'characters_per_minute' => self::clampReadingSpeed($data['characters_per_minute'] ?? null),
            ],
        ];
    }

    /**
     * Coerce a reading speed into the accepted range, falling back to the default when
     * it is not a usable number at all.
     */
    private static function clampReadingSpeed(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::defaults()['reading']['characters_per_minute'];
        }

        return max(self::MIN_CHARACTERS_PER_MINUTE, min(self::MAX_CHARACTERS_PER_MINUTE, (int) $value));
    }
}
