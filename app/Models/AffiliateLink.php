<?php

namespace App\Models;

use App\Enums\AffiliateLinkStatus;
use Database\Factories\AffiliateLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAffiliateLink
 */
#[Fillable(['name', 'slug', 'url', 'retailer', 'status', 'notes'])]
class AffiliateLink extends Model
{
    /** @use HasFactory<AffiliateLinkFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AffiliateLinkStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Products carrying this link as one of their retailer links.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return HasMany<AffiliateLinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateLinkClick::class);
    }

    /**
     * The internal redirect path placed in content instead of the raw retailer URL.
     */
    public function redirectPath(): string
    {
        return "/go/{$this->slug}";
    }
}
