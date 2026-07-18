<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
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
            'excerpt' => fake()->paragraph(),
            'content' => [
                [
                    'type' => 'richText',
                    'data' => [
                        'content' => '<p>'.fake()->paragraphs(2, true).'</p>',
                    ],
                ],
            ],
            'status' => ArticleStatus::Draft,
            'published_at' => null,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 month'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Scheduled,
            'published_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }
}
