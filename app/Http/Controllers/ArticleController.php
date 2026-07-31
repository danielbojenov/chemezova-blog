<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Contracts\View\View;

class ArticleController extends Controller
{
    /**
     * Show a single article.
     *
     * The template is a stand-in until the article page design is built; it exists so
     * articles authored in the admin resolve to a real URL.
     */
    public function __invoke(Article $article): View
    {
        abort_unless($article->isPublished(), 404);

        return view('article', ['article' => $article]);
    }
}
