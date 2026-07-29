<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Support\AffiliateLinks\ArticleAffiliateLinkSyncer;
use App\Support\Images\ArticleImageProcessor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected bool $hasProcessedImages = false;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ArticleResource::fillPublishedAt($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Article) {
            $this->hasProcessedImages = app(ArticleImageProcessor::class)->process($record);
            app(ArticleAffiliateLinkSyncer::class)->sync($record);
        }
    }

    /**
     * Remount the page after image conversion so FilePond previews the rewritten
     * file paths; a partial in-place state refill leaves the upload field blank.
     */
    protected function getRedirectUrl(): ?string
    {
        if ($this->hasProcessedImages) {
            return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
        }

        return parent::getRedirectUrl();
    }
}
