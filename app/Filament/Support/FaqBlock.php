<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class FaqBlock
{
    /**
     * An article content block with an optional heading and a list of
     * question/answer pairs, rendered as an accordion on the frontend.
     */
    public static function make(): Block
    {
        return Block::make('faq')
            ->label('FAQ')
            ->icon(Heroicon::QuestionMarkCircle)
            ->schema([
                TextInput::make('heading')
                    ->maxLength(255),
                Repeater::make('items')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('question')
                            ->required()
                            ->maxLength(500)
                            ->live(onBlur: true),
                        ArticleRichEditor::withoutHeadings('answer')
                            ->required(),
                    ])
                    ->required()
                    ->minItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                    ->reorderableWithButtons()
                    ->addActionLabel('Add question'),
            ]);
    }
}
