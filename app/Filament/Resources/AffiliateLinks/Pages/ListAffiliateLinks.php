<?php

namespace App\Filament\Resources\AffiliateLinks\Pages;

use App\Filament\Resources\AffiliateLinks\AffiliateLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateLinks extends ListRecords
{
    protected static string $resource = AffiliateLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
