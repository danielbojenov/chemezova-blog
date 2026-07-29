<?php

namespace App\Models;

use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperProduct
 */
#[Fillable([
    'name',
    'slug',
    'brand_id',
    'primary_ingredient_id',
    'description',
    'image',
    'form',
    'composition',
    'price',
    'doses_per_pack',
    'currency',
    'rating',
    'status',
    'meta_title',
    'meta_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The most retailer links a single product may carry. Enforced in the form;
     * articles may override or hide them per product card.
     */
    public const int MAX_AFFILIATE_LINKS = 2;

    protected static function booted(): void
    {
        static::deleted(function (Product $product): void {
            Storage::disk('public')->deleteDirectory("products/{$product->id}");
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * `price_per_dose` is a stored generated column, so it is cast but never
     * written to.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'form' => SupplementForm::class,
            'composition' => ProductComposition::class,
            'price' => 'decimal:2',
            'price_per_dose' => 'decimal:4',
            'rating' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function primaryIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class);
    }

    /**
     * The product's own retailer links, used by product cards that do not
     * override them.
     *
     * @return BelongsToMany<AffiliateLink, $this>
     */
    public function affiliateLinks(): BelongsToMany
    {
        return $this->belongsToMany(AffiliateLink::class);
    }

    /**
     * Articles featuring this product, with the rank derived from the position of
     * its card in each article.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withPivot('rank');
    }
}
