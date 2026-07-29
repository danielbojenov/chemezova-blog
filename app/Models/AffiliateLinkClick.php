<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAffiliateLinkClick
 */
#[Fillable(['referer'])]
class AffiliateLinkClick extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<AffiliateLink, $this>
     */
    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }
}
