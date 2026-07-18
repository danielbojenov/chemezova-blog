<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
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
    }
}
