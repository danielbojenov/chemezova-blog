<?php

namespace Database\Factories;

use App\Enums\AffiliateLinkStatus;
use App\Models\AffiliateLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateLink>
 */
class AffiliateLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'url' => 'https://retailer.example/products/'.Str::slug($name),
            'retailer' => fake()->company(),
            'status' => AffiliateLinkStatus::Active,
            'notes' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AffiliateLinkStatus::Disabled,
        ]);
    }
}
