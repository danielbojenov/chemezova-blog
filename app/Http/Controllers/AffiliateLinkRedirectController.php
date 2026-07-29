<?php

namespace App\Http\Controllers;

use App\Enums\AffiliateLinkStatus;
use App\Models\AffiliateLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateLinkRedirectController extends Controller
{
    /**
     * Log the click and redirect to the link's current destination.
     *
     * Missing links return 404, disabled links 410. Click logging is a single
     * indexed insert; switch to a queued/batched insert if traffic outgrows it.
     */
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $link = AffiliateLink::query()->where('slug', $slug)->first();

        abort_if($link === null, 404);
        abort_if($link->status === AffiliateLinkStatus::Disabled, 410);

        $link->clicks()->create([
            'referer' => Str::limit((string) $request->headers->get('referer'), 2048, '') ?: null,
        ]);

        return redirect()->away($link->url, 302);
    }
}
