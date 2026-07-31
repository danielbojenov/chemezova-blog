<?php

namespace App\Filament\Resources\Articles;

use App\Console\Commands\PublishScheduledArticles;
use App\Enums\ContentStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\ViewArticle;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Models\Article;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure($table);
    }

    /**
     * Normalize `published_at` for an article being published.
     *
     * Status is what decides visibility, so a Published article must never carry a date
     * that has not arrived yet: it would read as live in the panel while the public site
     * showed nothing. Both an empty date and a future one are pulled to now, which is
     * also what makes moving Scheduled → Published safe without clearing the field by
     * hand. {@see PublishScheduledArticles} takes the other path,
     * flipping the status once a scheduled date arrives.
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'view' => ViewArticle::route('/{record}'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
