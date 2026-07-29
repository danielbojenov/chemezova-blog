<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use App\Enums\IngredientType;
use App\Filament\Support\SlugInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                SlugInput::make('name'),
                Select::make('type')
                    ->options(IngredientType::class)
                    ->default(IngredientType::Other)
                    ->required(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
