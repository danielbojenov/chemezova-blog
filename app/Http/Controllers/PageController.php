<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    /**
     * Show a root-level page.
     *
     * Slugs are unique across the whole table, so this route also matches a page that
     * has since been grouped under a hub. Rather than serving the same content at two
     * URLs, it redirects to the canonical nested one.
     */
    public function show(Page $page): View|RedirectResponse
    {
        abort_unless($page->isPublished(), 404);

        if ($page->parent_id !== null) {
            return redirect($page->url(), 301);
        }

        return $this->render($page);
    }

    /**
     * Show a page nested under its hub.
     *
     * The `$child` parameter is resolved through the hub's `children()` relation by
     * Laravel's scoped implicit binding, so a page requested under the wrong hub never
     * reaches this method — it 404s during resolution.
     */
    public function showChild(Page $parent, Page $child): View
    {
        return $this->render($child);
    }

    private function render(Page $page): View
    {
        abort_unless($page->isPublished(), 404);

        return view('page', ['page' => $page]);
    }
}
