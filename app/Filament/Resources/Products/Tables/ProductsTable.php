<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The supplement catalog. Sorting and filtering are the point of this table, so
 * every property an editor might browse by is either a sortable column or a
 * filter.
 */
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('primaryIngredient.name')
                    ->label('Primary ingredient')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('form')
                    ->badge()
                    ->sortable(),
                TextColumn::make('composition')
                    ->badge()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Pack price')
                    ->money(fn (Product $record): string => $record->currency)
                    ->sortable(),
                TextColumn::make('price_per_dose')
                    ->label('Per dose')
                    ->money(fn (Product $record): string => $record->currency)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('doses_per_pack')
                    ->label('Doses')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rating')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
                SelectFilter::make('form')
                    ->options(SupplementForm::class)
                    ->multiple(),
                SelectFilter::make('composition')
                    ->options(ProductComposition::class)
                    ->multiple(),
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('primaryIngredient')
                    ->label('Primary ingredient')
                    ->relationship('primaryIngredient', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('ingredients')
                    ->relationship('ingredients', 'name')
                    ->multiple()
                    ->preload(),
                Filter::make('price_per_dose')
                    ->schema([
                        TextInput::make('from')
                            ->numeric()
                            ->label('Price per dose from'),
                        TextInput::make('to')
                            ->numeric()
                            ->label('Price per dose to'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, mixed $from): Builder => $query->where('price_per_dose', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $query, mixed $to): Builder => $query->where('price_per_dose', '<=', $to))),
                Filter::make('rating')
                    ->schema([
                        TextInput::make('min')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1)
                            ->label('Minimum rating'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['min'] ?? null, fn (Builder $query, mixed $min): Builder => $query->where('rating', '>=', $min))),
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
