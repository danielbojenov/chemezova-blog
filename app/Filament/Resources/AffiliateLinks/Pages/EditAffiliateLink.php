<?php

namespace App\Filament\Resources\AffiliateLinks\Pages;

use App\Filament\Resources\AffiliateLinks\AffiliateLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateLink extends EditRecord
{
    protected static string $resource = AffiliateLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
