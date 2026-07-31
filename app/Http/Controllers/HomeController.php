<?php

namespace App\Http\Controllers;

use App\Support\Site\FeaturedArticle;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page.
     *
     * The featured block is wired to real content; the latest and popular sections still
     * carry the design's placeholder copy and get their queries here as each is built.
     */
    public function __invoke(): View
    {
        return view('home', [
            'featured' => FeaturedArticle::current(),
        ]);
    }
}
