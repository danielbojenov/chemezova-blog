<?php

namespace App\Filament\Support;

use App\Enums\AffiliateLinkStatus;
use App\Models\AffiliateLink;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

/**
 * The modal behind the "Insert affiliate link" toolbar button: pick an active
 * registry link (or create one on the fly) and wrap the editor selection in an
 * <a href="/go/{slug}"> tag. Removing a link is done with the built-in link tool.
 */
class AffiliateLinkAction
{
    public static function make(): Action
    {
        return Action::make('affiliateLink')
            ->label('Insert affiliate link')
            ->modalHeading('Insert affiliate link')
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'affiliate_link_id' => AffiliateLink::query()
                    ->where('slug', (string) str((string) ($arguments['href'] ?? ''))->after('/go/'))
                    ->value('id'),
            ])
            ->schema([
                Select::make('affiliate_link_id')
                    ->label('Affiliate link')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => self::activeLinks()->limit(50)->pluck('name', 'id')->all())
                    ->getSearchResultsUsing(fn (string $search): array => self::activeLinks()
                        ->where(fn (Builder $query): Builder => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('retailer', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%"))
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => AffiliateLink::find($value)?->name)
                    ->actionSchemaModel(AffiliateLink::class)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        SlugInput::make('name'),
                        TextInput::make('url')
                            ->url()
                            ->required()
                            ->maxLength(2048),
                        TextInput::make('retailer')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->createOptionUsing(fn (array $data): int => AffiliateLink::create([
                        ...$data,
                        'status' => AffiliateLinkStatus::Active,
                    ])->getKey()),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $link = AffiliateLink::find($data['affiliate_link_id'] ?? null);

                if ($link === null) {
                    return;
                }

                $isCollapsedSelection = ($arguments['editorSelection']['head'] ?? null) === ($arguments['editorSelection']['anchor'] ?? null);

                $component->runCommands(
                    [
                        ...($isCollapsedSelection ? [EditorCommand::make(
                            'extendMarkRange',
                            arguments: ['link'],
                        )] : []),
                        EditorCommand::make(
                            'setLink',
                            arguments: [['href' => $link->redirectPath()]],
                        ),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }

    /**
     * @return Builder<AffiliateLink>
     */
    private static function activeLinks(): Builder
    {
        return AffiliateLink::query()
            ->where('status', AffiliateLinkStatus::Active)
            ->orderBy('name');
    }
}
