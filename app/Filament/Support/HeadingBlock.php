<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class HeadingBlock
{
    /**
     * Prefix on the collapsed block header, so a heading is recognisable as one at
     * a glance rather than reading as body copy. Matches the convention used by
     * {@see ProductCardBlock::LABEL_PREFIX}.
     */
    public const string LABEL_PREFIX = 'H2';

    /**
     * An article content block holding a single section heading. Section headings
     * are their own block rather than H2 markup inside a rich text block, so the
     * article outline stays visible while scrolling the collapsed builder and can
     * be read straight off the stored content for a table of contents.
     */
    public static function make(): Block
    {
        return Block::make('h2')
            ->label(fn (?array $state): string => filled($state['text'] ?? null)
                ? self::LABEL_PREFIX.' — '.$state['text']
                : self::LABEL_PREFIX)
            ->icon(Heroicon::H2)
            ->schema([
                TextInput::make('text')
                    ->hiddenLabel()
                    ->required()
                    ->maxLength(255)
                    // Keeps the collapsed block label in step with the heading.
                    ->live(onBlur: true),
            ]);
    }
}
