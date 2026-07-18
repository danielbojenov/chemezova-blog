<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class SlugInput
{
    /**
     * A slug input with a suffix button that generates the slug from another field.
     */
    public static function make(string $sourceField): TextInput
    {
        return TextInput::make('slug')
            ->required()
            ->maxLength(255)
            ->alphaDash()
            ->unique(ignoreRecord: true)
            ->suffixAction(
                Action::make('generateSlug')
                    ->label('Generate slug')
                    ->icon(Heroicon::ArrowPath)
                    ->action(function (Get $get, Set $set) use ($sourceField): void {
                        $set('slug', Str::slug((string) $get($sourceField)));
                    }),
            );
    }
}
