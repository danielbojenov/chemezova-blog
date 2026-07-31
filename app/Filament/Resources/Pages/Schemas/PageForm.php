<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\ContentStatus;
use App\Filament\Support\ContentBuilder;
use App\Filament\Support\ContentRichEditor;
use App\Filament\Support\FeaturedImageSection;
use App\Filament\Support\HeadingBlock;
use App\Filament\Support\ImageBlock;
use App\Filament\Support\SlugInput;
use App\Models\Page;
use App\Support\Site\ReservedSlugs;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Validation\Rule;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        SlugInput::make('title')
                            // Root pages are the only content that will sit directly
                            // under `/`, so their slugs are checked against the segments
                            // the site's own routes use, or will use.
                            ->rule(Rule::notIn(ReservedSlugs::all()))
                            ->validationMessages([
                                'not_in' => 'This slug is reserved for the site\'s own URLs. Pick another one.',
                            ]),
                        Select::make('status')
                            // No scheduled case: there is no editorial calendar for an
                            // About page, it is either live or it is not.
                            ->options([
                                ContentStatus::Draft->value => ContentStatus::Draft->getLabel(),
                                ContentStatus::Published->value => ContentStatus::Published->getLabel(),
                            ])
                            ->default(ContentStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (Get $get): bool => self::status($get) === ContentStatus::Published)
                            ->helperText('Leave empty when publishing to use the current time. Times are '.FilamentTimezone::get().'.'),
                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Shown next to this page\'s title in the list on its parent page.'),
                    ]),
                Section::make('Parent page')
                    ->columnSpanFull()
                    ->description('Group this page under a hub, such as Legal information. Leave empty for a page that stands on its own, or that acts as a hub itself.')
                    ->schema([
                        Select::make('parent_id')
                            ->hiddenLabel()
                            ->relationship(
                                'parent',
                                'title',
                                // Only root pages are offered, and never the record
                                // itself: pages nest exactly one level deep.
                                modifyQueryUsing: fn (EloquentBuilder $query, ?Page $record): EloquentBuilder => $query
                                    ->whereNull('parent_id')
                                    ->when($record, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKeyNot($record)),
                            )
                            ->searchable()
                            ->preload()
                            // A page that is already a hub cannot become a child without
                            // stranding its own children, so the field locks rather than
                            // letting the save fail on the model's guard.
                            ->disabled(fn (?Page $record): bool => $record?->children()->exists() ?? false)
                            ->helperText(fn (?Page $record): ?string => $record?->children()->exists()
                                ? 'This page has child pages of its own, so it cannot be moved under another page.'
                                : null),
                    ]),
                FeaturedImageSection::make(),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        ContentBuilder::make('content')
                            ->blocks([
                                HeadingBlock::make(),
                                Block::make('richText')
                                    ->label('Rich text')
                                    ->icon(Heroicon::Bars3BottomLeft)
                                    ->schema([
                                        ContentRichEditor::withoutAffiliateLinks('content')
                                            ->hiddenLabel()
                                            ->required(),
                                    ]),
                                ImageBlock::make(),
                            ])
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel('Add block'),
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
