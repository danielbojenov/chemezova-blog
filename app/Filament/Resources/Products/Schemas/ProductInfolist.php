<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpanFull()
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('rating')
                            ->numeric(decimalPlaces: 1)
                            ->suffix(' / 5')
                            ->placeholder('—'),
                    ]),
                Section::make('Image')
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('image')
                            ->hiddenLabel()
                            ->disk('public')
                            ->placeholder('—'),
                    ]),
                Section::make('Classification')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('brand.name')
                            ->label('Brand')
                            ->placeholder('—'),
                        TextEntry::make('form')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('primaryIngredient.name')
                            ->label('Primary ingredient')
                            ->placeholder('—'),
                        TextEntry::make('composition')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('ingredients.name')
                            ->label('Ingredients')
                            ->badge()
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Section::make('Pricing')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('price')
                            ->label('Pack price')
                            ->money(fn (Product $record): string => $record->currency)
                            ->placeholder('—'),
                        TextEntry::make('doses_per_pack')
                            ->label('Doses per pack')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('price_per_dose')
                            ->label('Price per dose')
                            ->money(fn (Product $record): string => $record->currency)
                            ->placeholder('—')
                            ->helperText('Calculated by the database.'),
                    ]),
                Section::make('Description')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Section::make('Retailer links')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('affiliateLinks.name')
                            ->hiddenLabel()
                            ->badge()
                            ->placeholder('No retailer links set.'),
                    ]),
                Section::make('Featured in')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('articles.title')
                            ->hiddenLabel()
                            ->badge()
                            ->placeholder('This product is not used in any article yet.'),
                    ]),
                Section::make('SEO')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('meta_title')
                            ->placeholder('—'),
                        TextEntry::make('meta_description')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
