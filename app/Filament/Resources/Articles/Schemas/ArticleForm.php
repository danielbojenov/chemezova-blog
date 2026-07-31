<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ContentStatus;
use App\Enums\RankingOrder;
use App\Filament\Support\ContentBuilder;
use App\Filament\Support\ContentRichEditor;
use App\Filament\Support\FaqBlock;
use App\Filament\Support\FeaturedImageSection;
use App\Filament\Support\HeadingBlock;
use App\Filament\Support\ImageBlock;
use App\Filament\Support\ProductCardBlock;
use App\Filament\Support\SlugInput;
use App\Models\Article;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
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
                            ->options(ContentStatus::class)
                            ->default(ContentStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (Get $get): bool => in_array(self::status($get), [ContentStatus::Published, ContentStatus::Scheduled], true))
                            ->required(fn (Get $get): bool => self::status($get) === ContentStatus::Scheduled)
                            // The zone is spelled out because the picker shows a bare
                            // clock: stored timestamps are UTC and only the panel
                            // converts, so an unlabelled field invites scheduling an
                            // article hours away from where the editor meant.
                            ->helperText('Leave empty when publishing to use the current time. Times are '.FilamentTimezone::get().'.'),
                    ]),
                FeaturedImageSection::make(),
                Section::make('Intro')
                    ->description('The two ways the article introduces itself: a line for listings, and a summary for readers who have arrived.')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(Article::INTRO_LIMIT)
                            ->columnSpanFull()
                            ->helperText('One or two sentences, shown wherever the article is listed rather than read. Left empty, a shortened TLDR stands in.'),
                        RichEditor::make('tldr')
                            ->label('TLDR')
                            ->columnSpanFull()
                            ->disableToolbarButtons(['h2', 'h3'])
                            ->helperText('Optional short summary shown above the article.'),
                    ]),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('ranking_order')
                            ->label('Product card numbering')
                            ->options(RankingOrder::class)
                            ->default(RankingOrder::Descending)
                            ->required()
                            ->helperText('Numbers the product cards below. Descending suits a TOP 10 countdown; ranks always follow card order, so removing a card leaves no gap.'),
                        ContentBuilder::make('content')
                            // ->hiddenLabel()
                            ->blocks([
                                HeadingBlock::make(),
                                Block::make('richText')
                                    ->label('Rich text')
                                    ->icon(Heroicon::Bars3BottomLeft)
                                    ->schema([
                                        ContentRichEditor::make('content')
                                            ->hiddenLabel()
                                            ->required(),
                                    ]),
                                ImageBlock::make(),
                                FaqBlock::make(),
                                ProductCardBlock::make(),
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
    private static function status(Get $get): ?ContentStatus
    {
        $state = $get('status');

        return $state instanceof ContentStatus ? $state : ContentStatus::tryFrom((string) $state);
    }
}
