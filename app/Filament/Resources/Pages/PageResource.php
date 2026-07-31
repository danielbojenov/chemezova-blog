<?php

namespace App\Filament\Resources\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Pages\ViewPage;
use App\Filament\Resources\Pages\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Filament\Resources\Pages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    /**
     * Normalize `published_at` for a page being published.
     *
     * Same rule as {@see ArticleResource::fillPublishedAt()} — an empty or future date is
     * pulled to now, so a live page never carries a date that has not arrived. Pages have
     * no Scheduled status at all, so a future date here is simply a typo rather than an
     * intent to schedule, and correcting it is safe.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillPublishedAt(array $data): array
    {
        $status = $data['status'] ?? null;
        $status = $status instanceof ContentStatus ? $status : ContentStatus::tryFrom((string) $status);

        if ($status !== ContentStatus::Published) {
            return $data;
        }

        $publishedAt = $data['published_at'] ?? null;

        if (empty($publishedAt) || Carbon::parse($publishedAt)->isFuture()) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * The children list only exists on hub pages; the relation manager hides itself on
     * pages that already have a parent. See {@see ChildrenRelationManager}.
     */
    public static function getRelations(): array
    {
        return [
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'view' => ViewPage::route('/{record}'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
