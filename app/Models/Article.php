<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\RankingOrder;
use App\Support\Articles\PlainText;
use App\Support\Images\HasContentImages;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperArticle
 */
#[Fillable([
    'title',
    'slug',
    'excerpt',
    'tldr',
    'content',
    'featured_image',
    'featured_image_alt',
    'featured_image_caption',
    'status',
    'published_at',
    'ranking_order',
    'meta_title',
    'meta_description',
])]
class Article extends Model implements HasContentImages
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * Longest lead-in {@see self::intro()} will return, and the cap the excerpt field
     * enforces. One or two sentences: enough to say what the article is about in a card
     * or a hero block, short enough that the layout can count on it.
     */
    public const int INTRO_LIMIT = 255;

    /**
     * Marks a TLDR that {@see self::intro()} had to cut short.
     */
    public const string INTRO_ELLIPSIS = '…';

    protected static function booted(): void
    {
        static::deleted(function (Article $article): void {
            Storage::disk('public')->deleteDirectory($article->imageDirectory());
        });
    }

    public function imageDirectory(): string
    {
        return "articles/{$this->id}";
    }

    /**
     * The article's public URL.
     */
    public function url(): string
    {
        return route('articles.show', ['article' => $this]);
    }

    /**
     * The short lead-in shown wherever the article is summarised rather than read.
     *
     * The excerpt is the author's own one or two sentences and is used verbatim. Failing
     * that the TLDR stands in, reduced to plain text and shortened: it is written to be
     * read above the article, so it is often several sentences and its markup has no
     * place in a card. Neither one written means no lead-in — better a headline on its
     * own than a sentence cut off mid-thought.
     */
    public function intro(): ?string
    {
        if (filled($this->excerpt)) {
            return trim((string) $this->excerpt);
        }

        $tldr = PlainText::from($this->tldr);

        if ($tldr === '') {
            return null;
        }

        // The ellipsis counts against the limit, so a shortened TLDR is never longer than
        // an excerpt the field would have accepted, and callers can size for one number.
        return Str::limit($tldr, self::INTRO_LIMIT - mb_strlen(self::INTRO_ELLIPSIS), end: self::INTRO_ELLIPSIS, preserveWords: true);
    }

    /**
     * Whether the article is live on the public site.
     *
     * Status is the single switch: a Scheduled article is not live until the
     * `articles:publish-scheduled` command flips it, and a Published one cannot be
     * holding a future date because `ArticleResource::fillPublishedAt()` pulls those back
     * to now on save. Keeping the date out of this check is what makes the article page
     * and the category listing agree.
     */
    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'ranking_order' => RankingOrder::class,
        ];
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Affiliate links placed in this article's content, synced on save.
     *
     * @return BelongsToMany<AffiliateLink, $this>
     */
    public function affiliateLinks(): BelongsToMany
    {
        return $this->belongsToMany(AffiliateLink::class);
    }

    /**
     * Products placed in this article's content as product cards, synced on save.
     * The pivot rank is derived from each card's position, so it is ordered here
     * by position rather than by rank, which may run either way.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('rank');
    }
}
