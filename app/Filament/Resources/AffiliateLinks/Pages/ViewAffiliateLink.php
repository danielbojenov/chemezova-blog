<?php

namespace App\Filament\Resources\AffiliateLinks\Pages;

use App\Filament\Resources\AffiliateLinks\AffiliateLinkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateLink extends ViewRecord
{
    protected static string $resource = AffiliateLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
