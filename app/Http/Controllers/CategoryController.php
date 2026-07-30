<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Models\Category;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    /**
     * Show a category and the articles filed under it.
     *
     * The template is a stand-in until the category page design is built; it exists so
     * the navigation links configured in the admin resolve to a real page.
     */
    public function __invoke(Category $category): View
    {
        return view('category', [
            'category' => $category,
            'articles' => $category->articles()
                ->where('status', ArticleStatus::Published)
                ->latest('published_at')
                ->get(),
        ]);
    }
}
