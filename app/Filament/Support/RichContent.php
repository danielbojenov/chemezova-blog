<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use stdClass;

/**
 * Renders authored rich text HTML for display, for articles and pages alike. All
 * affiliate /go/ links get rel="sponsored nofollow" here, centrally, instead of
 * storing it in content.
 */
class RichContent
{
    public static function renderer(?string $html): RichContentRenderer
    {
        return RichContentRenderer::make($html ?? '')
            ->processNodesUsing(function (object &$node): void {
                // Text nodes carry the link marks but are skipped by the node
                // walker, so mutate them through their visited parent.
                foreach ($node->content ?? [] as $child) {
                    foreach ($child->marks ?? [] as $mark) {
                        if (($mark->type ?? null) !== 'link') {
                            continue;
                        }

                        if (str_starts_with((string) ($mark->attrs->href ?? ''), '/go/')) {
                            $mark->attrs ??= new stdClass;
                            $mark->attrs->rel = 'sponsored nofollow';
                        }
                    }
                }
            });
    }
}
