<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'parent_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'content' => [
                [
                    'type' => 'richText',
                    'data' => [
                        'content' => '<p>'.fake()->paragraphs(2, true).'</p>',
                    ],
                ],
            ],
            'status' => ContentStatus::Draft,
            'published_at' => null,
            'sort_order' => 0,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 month'),
        ]);
    }

    /**
     * A page grouped under the given hub. Passing no hub creates one.
     */
    public function childOf(?Page $parent = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent?->getKey() ?? Page::factory(),
        ]);
    }

    /**
     * A featured image already through the pipeline, living in the page's own
     * `featured/` subdirectory. The file itself is not created — states that need
     * it on disk should put it there themselves.
     */
    public function withFeaturedImage(string $baseName = 'featured-photo'): static
    {
        return $this
            ->state(fn (array $attributes): array => [
                'featured_image_alt' => fake()->sentence(4),
                'featured_image_caption' => fake()->sentence(8),
            ])
            ->afterCreating(fn (Page $page) => $page->updateQuietly([
                'featured_image' => "pages/{$page->id}/featured/{$baseName}.webp",
            ]));
    }
}
