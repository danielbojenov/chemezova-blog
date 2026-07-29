<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;
use App\Filament\Support\ArticleRichEditor;
use App\Filament\Support\FaqBlock;
use App\Filament\Support\ImageBlock;
use App\Filament\Support\SlugInput;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        SlugInput::make('title'),
                        Select::make('status')
                            ->options(ArticleStatus::class)
                            ->default(ArticleStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (Get $get): bool => in_array(self::status($get), [ArticleStatus::Published, ArticleStatus::Scheduled], true))
                            ->required(fn (Get $get): bool => self::status($get) === ArticleStatus::Scheduled)
                            ->helperText('Leave empty when publishing to use the current time.'),
                    ]),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        Builder::make('content')
                            // ->hiddenLabel()
                            ->blocks([
                                Block::make('richText')
                                    ->label('Rich text')
                                    ->icon(Heroicon::Bars3BottomLeft)
                                    ->schema([
                                        ArticleRichEditor::make('content')
                                            ->hiddenLabel()
                                            ->required(),
                                    ]),
                                ImageBlock::make(),
                                FaqBlock::make(),
                            ])
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel('Add block'),
                    ]),
                Section::make('Taxonomy')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                SlugInput::make('name'),
                            ]),
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                SlugInput::make('name'),
                            ]),
                    ]),
                Section::make('SEO')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * Normalize the form state, which may hold an enum instance or its backing value.
     */
    private static function status(Get $get): ?ArticleStatus
    {
        $state = $get('status');

        return $state instanceof ArticleStatus ? $state : ArticleStatus::tryFrom((string) $state);
    }
}
