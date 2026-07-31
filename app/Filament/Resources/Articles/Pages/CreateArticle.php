<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Support\AffiliateLinks\ArticleAffiliateLinkSyncer;
use App\Support\Images\ContentImageProcessor;
use App\Support\Products\ArticleProductSyncer;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ArticleResource::fillPublishedAt($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Article) {
            app(ContentImageProcessor::class)->process($record);
            app(ArticleAffiliateLinkSyncer::class)->sync($record);
            app(ArticleProductSyncer::class)->sync($record);
        }
    }
}
