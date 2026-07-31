<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            // A sentence, not a paragraph: the excerpt field is capped at
            // Article::INTRO_LIMIT, so a factory default that could exceed it would make
            // an article the resource cannot re-save.
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

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Scheduled,
            'published_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }

    /**
     * A featured image already through the pipeline, living in the article's own
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
            ->afterCreating(fn (Article $article) => $article->updateQuietly([
                'featured_image' => "articles/{$article->id}/featured/{$baseName}.webp",
            ]));
    }

    /**
     * The `tldr` column is null by default, matching the optional field.
     */
    public function withTldr(?string $html = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'tldr' => $html ?? '<p>'.fake()->sentence(12).'</p>',
        ]);
    }
}
