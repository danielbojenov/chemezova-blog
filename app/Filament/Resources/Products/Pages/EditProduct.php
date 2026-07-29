<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Images\ProductImageProcessor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected bool $hasProcessedImages = false;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Product) {
            $this->hasProcessedImages = app(ProductImageProcessor::class)->process($record);
        }
    }

    /**
     * Remount the page after image conversion so FilePond previews the rewritten
     * file path; a partial in-place state refill leaves the upload field blank.
     */
    protected function getRedirectUrl(): ?string
    {
        if ($this->hasProcessedImages) {
            return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
        }

        return parent::getRedirectUrl();
    }
}
