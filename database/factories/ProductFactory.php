<?php

namespace Database\Factories;

use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use App\Models\AffiliateLink;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `price_per_dose` is never set here — the database derives it from `price`
     * and `doses_per_pack`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'brand_id' => Brand::factory(),
            'primary_ingredient_id' => Ingredient::factory(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'image' => null,
            'form' => fake()->randomElement(SupplementForm::cases()),
            'composition' => fake()->randomElement(ProductComposition::cases()),
            'price' => fake()->randomFloat(2, 5, 80),
            'doses_per_pack' => fake()->randomElement([30, 60, 90, 120]),
            'currency' => 'USD',
            'rating' => fake()->randomFloat(1, 3.0, 5.0),
            'status' => ProductStatus::Draft,
            'meta_title' => $name,
            'meta_description' => fake()->sentence(12),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Draft,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Published,
        ]);
    }

    /**
     * Fix the pack price and dose count so the derived per-dose price is known.
     */
    public function priced(float $price, int $dosesPerPack): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => $price,
            'doses_per_pack' => $dosesPerPack,
        ]);
    }

    /**
     * Point the image column at an already-converted path, skipping the upload
     * and conversion pipeline.
     */
    public function withImage(?string $path = null): static
    {
        return $this->afterCreating(function (Product $product) use ($path): void {
            $product->updateQuietly([
                'image' => $path ?? "products/{$product->id}/".Str::slug($product->name).'.webp',
            ]);
        });
    }

    public function withIngredients(int $count = 3): static
    {
        return $this->afterCreating(function (Product $product) use ($count): void {
            $product->ingredients()->attach(Ingredient::factory()->count($count)->create());
        });
    }

    public function withAffiliateLinks(int $count = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($count): void {
            $product->affiliateLinks()->attach(AffiliateLink::factory()->count($count)->create());
        });
    }
}
