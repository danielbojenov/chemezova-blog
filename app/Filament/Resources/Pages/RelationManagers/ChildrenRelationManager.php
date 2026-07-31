<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The pages grouped under a hub, in the order the hub will list them.
 *
 * Children are associated here rather than created here: a page needs its content
 * builder, featured image and SEO fields, none of which belong in a relation manager
 * modal. Editors write the page in the main resource, then group it — from either end,
 * since the page's own form carries a parent selector too.
 */
class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Child pages';

    /**
     * Hidden on pages that are themselves children, since the tree stops at two levels.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Page && $ownerRecord->isHub();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // Drag ordering is only meaningful within one parent, which is exactly the
            // scope of this table, so `sort_order` is maintained here and nowhere else.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->url(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('excerpt')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('Add child page')
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        // Only ungrouped pages, and never one that is a hub itself.
                        ->whereNull('parent_id')
                        ->whereKeyNot($this->getOwnerRecord())
                        ->whereDoesntHave('children'))
                    ->recordSelectSearchColumns(['title', 'slug'])
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record])),
                DissociateAction::make()
                    ->label('Remove from hub'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No child pages yet')
            ->emptyStateDescription('Add an existing page to group it under this one.');
    }
}
