<?php

namespace App\Support\Site;

/**
 * First URL segments a page slug may not take.
 *
 * Root-level pages are the only content that will live directly under `/`, so their
 * slugs are the one place a collision with a real route can happen. The list is
 * checked against every page rather than only root pages, so that moving a child out
 * of its hub can never turn a valid slug into a broken URL.
 *
 * Kept as a hand-maintained list rather than read from the route table: the point is to
 * reserve segments the site will *grow into* (`/articles`, `/search`, `/sitemap.xml`),
 * which by definition are not registered yet.
 */
final class ReservedSlugs
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            // Registered routes and framework paths.
            'admin',
            'api',
            'broadcasting',
            'build',
            'categories',
            'go',
            'livewire',
            'login',
            'logout',
            'register',
            'storage',
            'up',
            'vendor',
            // Reserved for frontend routing that is not built yet.
            'articles',
            'feed',
            'robots.txt',
            'rss',
            'search',
            'sitemap',
            'sitemap.xml',
            'tags',
        ];
    }
}
