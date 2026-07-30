<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\RankingOrder;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

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
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleted(function (Article $article): void {
            Storage::disk('public')->deleteDirectory("articles/{$article->id}");
        });
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
            'status' => ArticleStatus::class,
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
