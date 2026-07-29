<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use App\Models\Product;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves a freshly uploaded product image out of the temporary upload directory,
 * generates WebP variants, rewrites the stored path, and prunes orphaned files.
 *
 * The article equivalent walks a list of content blocks; a product carries a
 * single `image` column, so this keeps its own simpler implementation rather
 * than sharing a base class with ArticleImageProcessor.
 */
final readonly class ProductImageProcessor
{
    public const string DISK = 'public';

    public const string TMP_DIRECTORY = 'products/tmp';

    public const string TMP_SUFFIX_PATTERN = '/-tmp[a-z0-9]{6}$/';

    public function __construct(private ImageConverter $converter) {}

    /**
     * Convert a new upload referenced by the product's image column and clean up
     * orphans.
     *
     * @return bool whether the image was converted and the path rewritten
     */
    public function process(Product $product): bool
    {
        $disk = Storage::disk(self::DISK);
        $directory = "products/{$product->id}";
        $path = $product->image;

        $changed = false;

        if (is_string($path) && str_starts_with($path, self::TMP_DIRECTORY.'/') && $disk->exists($path)) {
            $baseName = $this->uniqueBaseName(
                $disk,
                $directory,
                (string) preg_replace(self::TMP_SUFFIX_PATTERN, '', pathinfo($path, PATHINFO_FILENAME)),
            );

            try {
                $paths = $this->converter->convert($disk, $path, $directory, $baseName, ImageSizeSettings::current());

                $disk->delete($path);
                $product->updateQuietly(['image' => $paths[ImageVariant::Original->value]]);
                $changed = true;
            } catch (Throwable $exception) {
                Log::warning('Failed to convert product image, leaving the upload untouched.', [
                    'product_id' => $product->id,
                    'path' => $path,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->cleanupOrphans($product);

        return $changed;
    }

    /**
     * Delete files in the product's directory whose base name is no longer
     * referenced by the image column.
     */
    public function cleanupOrphans(Product $product): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = "products/{$product->id}";

        $path = $product->image;
        $referenced = is_string($path) && str_starts_with($path, "{$directory}/")
            ? basename($path, '.webp')
            : null;

        foreach ($disk->files($directory) as $file) {
            if ($this->variantBaseNameOf($file) !== $referenced) {
                $disk->delete($file);
            }
        }
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
