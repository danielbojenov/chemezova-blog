<?php

namespace App\Support\Site;

/**
 * A resolved navigation link, ready to render.
 */
final readonly class NavigationLink
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $isCurrent,
    ) {}
}
