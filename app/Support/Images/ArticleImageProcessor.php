<?php

namespace App\Support\Images;

use App\Enums\ImageVariant;
use App\Models\Article;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves freshly uploaded article images out of the temporary upload directory,
 * generates WebP variants, rewrites content block paths, and prunes orphaned files.
 */
final readonly class ArticleImageProcessor
{
    public const string DISK = 'public';

    public const string TMP_DIRECTORY = 'articles/tmp';

    public const string TMP_SUFFIX_PATTERN = '/-tmp[a-z0-9]{6}$/';

    public function __construct(private ImageConverter $converter)
    {
    }

    /**
     * Convert new uploads referenced by the article's image blocks and clean up orphans.
     *
     * @return bool whether any block was converted and the content rewritten
     */
    public function process(Article $article): bool
    {
        $disk = Storage::disk(self::DISK);
        $directory = "articles/{$article->id}";
        $sizes = ImageSizeSettings::current();

        $content = $article->content ?? [];
        $changed = false;

        foreach ($content as $index => $block) {
            $path = $this->imagePathOf($block);

            if ($path === null || ! str_starts_with($path, self::TMP_DIRECTORY.'/')) {
                continue;
            }

            if (! $disk->exists($path)) {
                continue;
            }

            $baseName = $this->uniqueBaseName(
                $disk,
                $directory,
                (string) preg_replace(self::TMP_SUFFIX_PATTERN, '', pathinfo($path, PATHINFO_FILENAME)),
            );

            try {
                $paths = $this->converter->convert($disk, $path, $directory, $baseName, $sizes);
            } catch (Throwable $exception) {
                Log::warning('Failed to convert article image, leaving the upload untouched.', [
                    'article_id' => $article->id,
                    'path' => $path,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            $disk->delete($path);
            $content[$index]['data']['image'] = $paths[ImageVariant::Original->value];
            $changed = true;
        }

        if ($changed) {
            $article->updateQuietly(['content' => $content]);
        }

        $this->cleanupOrphans($article);

        return $changed;
    }

    /**
     * Delete files in the article's directory whose base name is no longer referenced by any image block.
     */
    public function cleanupOrphans(Article $article): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = "articles/{$article->id}";

        $referencedBaseNames = [];

        foreach ($article->content ?? [] as $block) {
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
    }

    /**
     * @param  mixed  $block
     */
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
