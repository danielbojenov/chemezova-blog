<?php

namespace App\Support\Site;

use App\Enums\ContentStatus;
use App\Enums\ImageVariant;
use App\Models\Article;
use App\Models\Page;
use App\Models\Tag;
use App\Support\Articles\ReadingTime;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;

/**
 * The article shown in the home page's featured block, resolved from the settings and
 * flattened into everything the template needs.
 *
 * Resolving here rather than in the template is what lets the block disappear cleanly:
 * a tag slug that names no tag, or a tag no published article carries, produces null
 * from {@see self::current()} and the section renders nothing at all — the same way
 * {@see SiteNavigation} drops links whose category has been deleted.
 */
final readonly class FeaturedArticle
{
    public function __construct(
        public Article $article,
        /**
         * The tag line above the headline: the configured title, followed by the
         * alphabetically first of the article's other tags — "Featured · Magnesium" —
         * or the title alone when the featured tag is the only one it carries.
         */
        public string $eyebrow,
        /** The article's lead-in, or null when it has neither an excerpt nor a TLDR. */
        public ?string $intro,
        public string $buttonLabel,
        public int $readingMinutes,
        /** Desktop variant of the article's featured image, null when it has none. */
        public ?string $imageUrl,
        /** Mobile variant of the same image, offered to small screens through srcset. */
        public ?string $imageMobileUrl,
        public string $imageAlt,
        public ?string $authorName,
        /** The author page's URL, null when no page is configured or it is not live. */
        public ?string $authorUrl,
        public ?string $authorAvatarUrl,
    ) {}

    /**
     * The current featured article, or null when the block has nothing to show.
     *
     * A different article is picked on every call when several carry the tag, so the
     * home page rotates through them rather than pinning one until it is retagged.
     */
    public static function current(): ?self
    {
        $pages = SitePageSettings::current();

        $tag = Tag::query()->where('slug', $pages->featuredTagSlug())->first();

        if (! $tag instanceof Tag) {
            return null;
        }

        $article = $tag->articles()
            ->where('status', ContentStatus::Published)
            // Ordered by name, which is the only stable order available: the article_tag
            // pivot records no attachment order, and an unordered read would let the same
            // article show a different second tag from one visit to the next.
            ->with(['tags' => fn (Relation $tags): Relation => $tags->orderBy('name')])
            ->inRandomOrder()
            ->first();

        if (! $article instanceof Article) {
            return null;
        }

        $general = SiteGeneralSettings::current();
        $secondaryTag = $article->tags->first(fn (Tag $other): bool => ! $other->is($tag));

        return new self(
            article: $article,
            eyebrow: $secondaryTag instanceof Tag
                ? $pages->featuredTagTitle().' · '.$secondaryTag->name
                : $pages->featuredTagTitle(),
            intro: $article->intro(),
            buttonLabel: $pages->featuredButtonLabel(),
            readingMinutes: ReadingTime::minutes($article->content, $general->charactersPerMinute()),
            imageUrl: self::variantUrl($article->featured_image, ImageVariant::Desktop),
            imageMobileUrl: self::variantUrl($article->featured_image, ImageVariant::Mobile),
            imageAlt: filled($article->featured_image_alt) ? (string) $article->featured_image_alt : $article->title,
            authorName: $general->authorName(),
            authorUrl: self::authorUrl($general->authorPageId()),
            authorAvatarUrl: self::variantUrl($general->authorAvatarPath(), ImageVariant::Thumbnail),
        );
    }

    /**
     * The public URL of one variant of a stored image, or null when there is no image.
     */
    private static function variantUrl(?string $path, ImageVariant $variant): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($variant->pathFor($path));
    }

    /**
     * Where the byline's name links to.
     *
     * A page that has been deleted or taken offline since it was chosen degrades to an
     * unlinked name rather than a link into a 404.
     */
    private static function authorUrl(?int $pageId): ?string
    {
        if ($pageId === null) {
            return null;
        }

        $page = Page::query()->whereKey($pageId)->first();

        return $page instanceof Page && $page->isPublished() ? $page->url() : null;
    }
}
