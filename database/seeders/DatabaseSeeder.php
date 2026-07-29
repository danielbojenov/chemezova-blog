<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $categories = Category::factory(5)->create();
        $tags = Tag::factory(10)->create();

        Article::factory(8)
            ->published()
            ->create()
            ->each(function (Article $article) use ($categories, $tags): void {
                $article->categories()->attach($categories->random(rand(1, 2)));
                $article->tags()->attach($tags->random(rand(1, 4)));
            });

        Article::factory(3)->draft()->create();
        Article::factory(2)->scheduled()->create();

        $brands = Brand::factory(6)->create();
        $ingredients = Ingredient::factory(12)->create();

        Product::factory(20)
            ->published()
            ->create()
            ->each(function (Product $product) use ($brands, $ingredients): void {
                $product->updateQuietly([
                    'brand_id' => $brands->random()->id,
                    'primary_ingredient_id' => $ingredients->random()->id,
                ]);

                $product->ingredients()->attach($ingredients->random(rand(1, 4)));
            });

        Product::factory(4)->draft()->create();
    }
}
