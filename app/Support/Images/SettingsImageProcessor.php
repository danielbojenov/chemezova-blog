<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The {@see ContentImageProcessor} equivalent for images that belong to a settings row
 * rather than to a model: the author's avatar, and whatever else the settings pages grow.
 *
 * The two processors cannot be one. ContentImageProcessor addresses a record — it reads
 * the record's content blocks, writes back to its columns, and derives its directory from
 * it — where a setting has none of that. What they do share, the staging directory and
 * the "every unreferenced file in this directory is an orphan" cleanup rule, is reused
 * here from ContentImageProcessor's constants.
 */
final readonly class SettingsImageProcessor
{
    /**
     * Where the author's avatar variants live. Each settings image needs its own
     * directory, because the cleanup below deletes everything else in it.
     */
    public const string AUTHOR_DIRECTORY = 'site/author';

    public function __construct(private ImageConverter $converter) {}

    /**
     * Convert a freshly staged upload into $directory and drop the image it replaces.
     *
     * Anything that is not a pending upload — an already-converted path from a previous
     * save, or null when the field was cleared — passes straight through, so this is safe
     * to call on every save. Clearing the field prunes the directory as a side effect.
     *
     * @return string|null the stored path of the Original variant
     */
    public function process(?string $path, string $directory): ?string
    {
        $disk = Storage::disk(ContentImageProcessor::DISK);

        $converted = $path;

        if (is_string($path) && str_starts_with($path, ContentImageProcessor::TMP_DIRECTORY.'/') && $disk->exists($path)) {
            $converted = $this->convert($path, $directory);

            // A failed conversion leaves the upload in tmp rather than storing a path
            // into a directory that has nothing in it.
            if ($converted === null) {
                return null;
            }

            $disk->delete($path);
        }

        $this->cleanupOrphans($directory, $converted);

        return $converted;
    }

    /**
     * Generate the WebP variants for one staged upload.
     *
     * The base name is carried over from the upload, which gives every replacement a new
     * filename and so a new URL — the avatar is rendered on every page, and reusing one
     * filename would leave browsers showing the old face until their cache expired.
     */
    private function convert(string $path, string $directory): ?string
    {
        $baseName = (string) preg_replace(
            ContentImageProcessor::TMP_SUFFIX_PATTERN,
            '',
            pathinfo($path, PATHINFO_FILENAME),
        );

        try {
            $paths = $this->converter->convert(
                Storage::disk(ContentImageProcessor::DISK),
                $path,
                $directory,
                $baseName !== '' ? $baseName : 'image',
                ImageSizeSettings::current(),
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to convert a settings image, leaving the upload untouched.', [
                'directory' => $directory,
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return $paths[ImageVariant::Original->value];
    }

    /**
     * Delete every variant in the directory that does not belong to the current image.
     */
    private function cleanupOrphans(string $directory, ?string $current): void
    {
        $disk = Storage::disk(ContentImageProcessor::DISK);

        $keep = is_string($current) && str_starts_with($current, "{$directory}/")
            ? basename($current, '.webp')
            : null;

        foreach ($disk->files($directory) as $file) {
            if (self::variantBaseNameOf($file) !== $keep) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Strip the variant suffix (and extension) from a path to get the image set's base name.
     */
    private static function variantBaseNameOf(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);

        foreach (ImageVariant::cases() as $variant) {
            $suffix = $variant->fileSuffix();

            if ($suffix !== '' && str_ends_with($name, $suffix)) {
                return substr($name, 0, -strlen($suffix));
            }
        }

        return $name;
    }
}
