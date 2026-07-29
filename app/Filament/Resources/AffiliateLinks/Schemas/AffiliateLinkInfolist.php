<?php

namespace App\Filament\Resources\AffiliateLinks\Schemas;

use App\Models\AffiliateLink;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class AffiliateLinkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Affiliate link')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpanFull()
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextEntry::make('slug')
                            ->label('Redirect URL')
                            ->state(fn (AffiliateLink $record): string => $record->redirectPath())
                            ->copyable()
                            ->copyableState(fn (AffiliateLink $record): string => url($record->redirectPath()))
                            ->copyMessage('Redirect URL copied'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('url')
                            ->label('Destination URL')
                            ->url(fn (AffiliateLink $record): string => $record->url, shouldOpenInNewTab: true)
                            ->columnSpanFull(),
                        TextEntry::make('retailer')
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->placeholder('—'),
                    ]),
                Section::make('Statistics')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('clicks_count')
                            ->label('Total clicks')
                            ->state(fn (AffiliateLink $record): int => $record->clicks()->count()),
                        TextEntry::make('last_clicked_at')
                            ->label('Last clicked')
                            ->state(fn (AffiliateLink $record): ?string => $record->clicks()->latest('created_at')->value('created_at'))
                            ->dateTime()
                            ->placeholder('Never'),
                    ]),
                Section::make('Used in articles')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('articles.title')
                            ->hiddenLabel()
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('Not used in any article'),
                    ]),
            ]);
    }
}
