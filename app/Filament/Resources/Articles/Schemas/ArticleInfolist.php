<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Support\ArticleContentEntry;
use App\Filament\Support\ArticleTldrEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('title')
                            ->columnSpanFull()
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                Section::make('Taxonomy')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('categories.name')
                            ->label('Categories')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('tags.name')
                            ->label('Tags')
                            ->badge()
                            ->placeholder('—'),
                    ]),
                Section::make('TLDR')
                    ->columnSpanFull()
                    ->schema([
                        ArticleTldrEntry::make('tldr')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        ArticleContentEntry::make('content')
                            ->hiddenLabel()
                            ->columnSpanFull(),
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
