<?php

namespace App\Filament\Resources\AffiliateLinks\Schemas;

use App\Enums\AffiliateLinkStatus;
use App\Filament\Support\SlugInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AffiliateLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                SlugInput::make('name')
                    ->helperText('Changing the slug breaks existing /go/ links in published content.'),
                TextInput::make('url')
                    ->label('Destination URL')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                TextInput::make('retailer')
                    ->maxLength(255),
                Select::make('status')
                    ->options(AffiliateLinkStatus::class)
                    ->default(AffiliateLinkStatus::Active)
                    ->required(),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
