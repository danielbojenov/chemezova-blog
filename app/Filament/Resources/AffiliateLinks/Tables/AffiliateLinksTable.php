<?php

namespace App\Filament\Resources\AffiliateLinks\Tables;

use App\Enums\AffiliateLinkStatus;
use App\Models\AffiliateLink;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Redirect URL')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => "/go/{$state}")
                    ->copyable()
                    ->copyableState(fn (AffiliateLink $record): string => url($record->redirectPath()))
                    ->copyMessage('Redirect URL copied'),
                TextColumn::make('retailer')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('clicks_count')
                    ->counts('clicks')
                    ->label('Clicks')
                    ->sortable(),
                TextColumn::make('articles_count')
                    ->counts('articles')
                    ->label('Articles')
                    ->sortable(),
                TextColumn::make('url')
                    ->label('Destination URL')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AffiliateLinkStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
