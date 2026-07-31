<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Schemas\ArticleInfolist;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openOnSite')
                ->label('Open on site')
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Article $record): string => $record->url())
                ->openUrlInNewTab()
                // A draft or a scheduled article has no public URL to open yet.
                ->visible(fn (Article $record): bool => $record->isPublished()),
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return ArticleInfolist::configure($schema);
    }
}
