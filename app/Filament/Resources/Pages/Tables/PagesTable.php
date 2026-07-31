<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\ContentStatus;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parent.title')
                    ->label('Parent')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('children_count')
                    ->label('Children')
                    ->counts('children')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Children read as a group under their hub. Ordering within a hub is set on
            // the hub's own Children relation manager, not here, since drag ordering only
            // means anything within one parent.
            ->defaultSort('title')
            ->groups([
                Group::make('parent.title')
                    ->label('Parent page')
                    // Keyed off `parent_id` rather than the relation, so a page with no
                    // hub gets a real group heading instead of an unlabelled one.
                    ->getTitleFromRecordUsing(fn (Page $record): string => $record->parent_id === null
                        ? 'Top-level pages'
                        : $record->parent->title),
            ])
            ->defaultGroup('parent.title')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ContentStatus::Draft->value => ContentStatus::Draft->getLabel(),
                        ContentStatus::Published->value => ContentStatus::Published->getLabel(),
                    ]),
                SelectFilter::make('parent')
                    ->label('Parent page')
                    ->relationship('parent', 'title', fn (Builder $query): Builder => $query->whereNull('parent_id'))
                    ->searchable()
                    ->preload(),
                Filter::make('hubs')
                    ->label('Only top-level pages')
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id')),
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
