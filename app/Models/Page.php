<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Support\Images\HasContentImages;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Standing site content — about, contact, legal — as opposed to the dated, categorised
 * content in {@see Article}.
 *
 * Pages carry no taxonomy. They group through a parent instead: a hub page (say "Legal
 * information") is an ordinary page with its own content that additionally lists the
 * pages beneath it. The tree is deliberately capped at that one level; see
 * {@see self::guardHierarchyDepth()}.
 *
 * @mixin IdeHelperPage
 */
#[Fillable([
    'parent_id',
    'title',
    'slug',
    'excerpt',
    'content',
    'featured_image',
    'featured_image_alt',
    'featured_image_caption',
    'status',
    'published_at',
    'sort_order',
    'meta_title',
    'meta_description',
])]
class Page extends Model implements HasContentImages
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->guardHierarchyDepth();
        });

        static::deleted(function (Page $page): void {
            Storage::disk('public')->deleteDirectory($page->imageDirectory());
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
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function imageDirectory(): string
    {
        return "pages/{$this->id}";
    }

    /**
     * The hub this page is grouped under, or null when the page sits at the root.
     *
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * The pages grouped under this one, in the order the hub lists them.
     *
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Whether this page sits at the root, and so may take children.
     */
    public function isHub(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * The page's canonical public URL.
     *
     * Two URL shapes back the same page — `/legal` and `/legal/privacy-policy` — so
     * callers ask the page rather than picking a route name and getting it wrong when a
     * page moves between hubs.
     */
    public function url(): string
    {
        if ($this->parent_id === null) {
            return route('pages.show', ['page' => $this]);
        }

        return route('pages.show.child', ['parent' => $this->parent, 'child' => $this]);
    }

    /**
     * Whether the page is live on the public site.
     */
    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /**
     * Keep the tree exactly two levels deep: root pages, and children of root pages.
     *
     * The parent Select in the form only offers valid options, so this exists to stop a
     * seeder, a console script, or a future bulk action from quietly building a deeper
     * tree that the URL scheme and the hub template cannot represent. Only runs when
     * `parent_id` actually changes, so ordinary saves cost no extra queries.
     */
    protected function guardHierarchyDepth(): void
    {
        if (! $this->isDirty('parent_id') || $this->parent_id === null) {
            return;
        }

        if ($this->exists && (int) $this->parent_id === (int) $this->getKey()) {
            throw new RuntimeException('A page cannot be its own parent.');
        }

        if (self::query()->whereKey($this->parent_id)->whereNotNull('parent_id')->exists()) {
            throw new RuntimeException("Page [{$this->parent_id}] is already a child, so it cannot be a parent.");
        }

        if ($this->exists && $this->children()->exists()) {
            throw new RuntimeException("Page [{$this->getKey()}] has children, so it cannot be given a parent.");
        }
    }
}
