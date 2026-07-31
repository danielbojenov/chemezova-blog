<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\ImageVariant;
use App\Filament\Support\ContentEntry;
use App\Models\Page;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class PageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
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
                        TextEntry::make('parent.title')
                            ->label('Parent page')
                            ->placeholder('Top level'),
                        TextEntry::make('children.title')
                            ->label('Child pages')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('excerpt')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Section::make('Featured image')
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('featured_image')
                            ->hiddenLabel()
                            ->disk('public')
                            // Preview the -mobile sibling rather than the full-size
                            // Original, matching the content block preview.
                            ->getStateUsing(fn (Page $record): ?string => filled($record->featured_image)
                                ? ImageVariant::Mobile->pathFor($record->featured_image)
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('featured_image_alt')
                            ->label('Alt text')
                            ->placeholder('—'),
                        TextEntry::make('featured_image_caption')
                            ->label('Caption')
                            ->placeholder('—'),
                    ]),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        // The same renderer the article preview uses. Pages only ever
                        // hold the heading, rich text and image blocks, so the product
                        // card branch of the view is simply never reached.
                        ContentEntry::make('content')
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
