<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Schemas\PageInfolist;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewPage extends ViewRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openOnSite')
                ->label('Open on site')
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Page $record): string => $record->url())
                ->openUrlInNewTab()
                // A draft has no public URL to open yet.
                ->visible(fn (Page $record): bool => $record->isPublished()),
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return PageInfolist::configure($schema);
    }
}
