<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves freshly uploaded content images out of the temporary upload directory,
 * generates WebP variants, rewrites the stored paths, and prunes orphaned files.
 *
 * Articles and pages are both handled here: each carries two independent sources of
 * images, the `image` blocks inside its content Builder and the single featured image
 * column. Both stage uploads in the same tmp directory, but the featured image is
 * converted into its own `featured/` subdirectory — see cleanupOrphans() for why that
 * separation matters. Where a record's files land is the record's own business, via
 * {@see HasContentImages::imageDirectory()}.
 */
final readonly class ContentImageProcessor
{
    public const string DISK = 'public';

    public const string TMP_DIRECTORY = 'content/tmp';

    public const string TMP_SUFFIX_PATTERN = '/-tmp[a-z0-9]{6}$/';

    public const string FEATURED_SUBDIRECTORY = 'featured';

    public function __construct(private ImageConverter $converter) {}

    /**
     * Convert new uploads referenced by the record's image blocks and featured
     * image column, then clean up orphans.
     *
     * @return bool whether anything was converted and the stored paths rewritten
     */
    public function process(Model&HasContentImages $record): bool
    {
        $disk = Storage::disk(self::DISK);
        $directory = $record->imageDirectory();
        $sizes = ImageSizeSettings::current();

        $attributes = [];

        $content = $this->convertContentBlocks($disk, $record, $directory, $sizes);

        if ($content !== null) {
            $attributes['content'] = $content;
        }

        $featured = $this->convertFeaturedImage($disk, $record, $this->featuredDirectory($record), $sizes);

        if ($featured !== null) {
            $attributes['featured_image'] = $featured;
        }

        // One write for both sources, quiet to avoid re-triggering model events
        // (and with them, re-entrant processing).
        if ($attributes !== []) {
            $record->updateQuietly($attributes);
        }

        $this->cleanupOrphans($record);

        return $attributes !== [];
    }

    /**
     * Convert every content block still pointing at a tmp upload.
     *
     * @return array<int, mixed>|null the rewritten content, or null when nothing changed
     */
    private function convertContentBlocks(Filesystem $disk, Model&HasContentImages $record, string $directory, ImageSizeSettings $sizes): ?array
    {
        $content = $record->content ?? [];
        $changed = false;

        foreach ($content as $index => $block) {
            $path = $this->imagePathOf($block);

            if ($path === null || ! $this->isPendingUpload($disk, $path)) {
                continue;
            }

            $converted = $this->convert($disk, $record, $path, $directory, $sizes);

            if ($converted === null) {
                continue;
            }

            $content[$index]['data']['image'] = $converted;
            $changed = true;
        }

        return $changed ? $content : null;
    }

    /**
     * Convert the featured image when it is still pointing at a tmp upload.
     *
     * @return string|null the rewritten path, or null when nothing changed
     */
    private function convertFeaturedImage(Filesystem $disk, Model&HasContentImages $record, string $directory, ImageSizeSettings $sizes): ?string
    {
        // Read by column name: the processor addresses records it knows nothing about
        // beyond the two columns the contract promises.
        $path = $record->getAttribute('featured_image');

        if (! is_string($path) || ! $this->isPendingUpload($disk, $path)) {
            return null;
        }

        return $this->convert($disk, $record, $path, $directory, $sizes);
    }

    /**
     * Generate the WebP variants for one staged upload and delete the original.
     *
     * @return string|null the Original variant's path, or null when conversion failed
     */
    private function convert(Filesystem $disk, Model&HasContentImages $record, string $path, string $directory, ImageSizeSettings $sizes): ?string
    {
        $baseName = $this->uniqueBaseName(
            $disk,
            $directory,
            (string) preg_replace(self::TMP_SUFFIX_PATTERN, '', pathinfo($path, PATHINFO_FILENAME)),
        );

        try {
            $paths = $this->converter->convert($disk, $path, $directory, $baseName, $sizes);
        } catch (Throwable $exception) {
            Log::warning('Failed to convert content image, leaving the upload untouched.', [
                'record' => $record::class,
                'record_id' => $record->getKey(),
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $disk->delete($path);

        return $paths[ImageVariant::Original->value];
    }

    /**
     * Whether the path points at a file still sitting in the tmp upload directory.
     */
    private function isPendingUpload(Filesystem $disk, string $path): bool
    {
        return str_starts_with($path, self::TMP_DIRECTORY.'/') && $disk->exists($path);
    }

    /**
     * Delete files whose base name is no longer referenced — images removed or
     * replaced in the Builder, and superseded featured images.
     *
     * The two passes cannot reach each other's files: `files()` is not recursive,
     * so the content pass never lists anything inside `featured/`, and the featured
     * pass only ever lists that subdirectory. Keeping the featured image in its own
     * folder is what makes each pass safe to run against a bare "everything here
     * that is unreferenced" rule.
     */
    public function cleanupOrphans(Model&HasContentImages $record): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = $record->imageDirectory();

        $referencedBaseNames = [];

        foreach ($record->content ?? [] as $block) {
            $path = $this->imagePathOf($block);

            if ($path !== null && str_starts_with($path, "{$directory}/")) {
                $referencedBaseNames[] = basename($path, '.webp');
            }
        }

        foreach ($disk->files($directory) as $file) {
            if (! in_array($this->variantBaseNameOf($file), $referencedBaseNames, true)) {
                $disk->delete($file);
            }
        }

        $featuredDirectory = $this->featuredDirectory($record);
        $featured = $record->getAttribute('featured_image');

        $referencedFeatured = is_string($featured) && str_starts_with($featured, "{$featuredDirectory}/")
            ? basename($featured, '.webp')
            : null;

        foreach ($disk->files($featuredDirectory) as $file) {
            if ($this->variantBaseNameOf($file) !== $referencedFeatured) {
                $disk->delete($file);
            }
        }
    }

    /**
     * The subdirectory holding the record's featured image variants.
     */
    private function featuredDirectory(Model&HasContentImages $record): string
    {
        return $record->imageDirectory().'/'.self::FEATURED_SUBDIRECTORY;
    }

    private function imagePathOf(mixed $block): ?string
    {
        if (! is_array($block) || ($block['type'] ?? null) !== 'image') {
            return null;
        }

        $path = $block['data']['image'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Resolve a base name that does not collide with an existing image set in the directory.
     */
    private function uniqueBaseName(Filesystem $disk, string $directory, string $baseName): string
    {
        $candidate = $baseName !== '' ? $baseName : 'image';
        $counter = 0;

        while ($disk->exists("{$directory}/{$candidate}.webp")) {
            $counter++;
            $candidate = "{$baseName}-{$counter}";
        }

        return $candidate;
    }

    /**
     * Strip the variant suffix (and extension) from a file path to get the image set's base name.
     */
    private function variantBaseNameOf(string $file): string
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
