<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Tag;
use Illuminate\Contracts\View\View;

class TagController extends Controller
{
    /**
     * Show a tag and the articles filed under it.
     *
     * The template is a stand-in until the tag page design is built; it exists so the
     * tag badges shown elsewhere resolve to a real page.
     */
    public function __invoke(Tag $tag): View
    {
        return view('tag', [
            'tag' => $tag,
            'articles' => $tag->articles()
                ->where('status', ContentStatus::Published)
                ->latest('published_at')
                ->get(),
        ]);
    }
}
