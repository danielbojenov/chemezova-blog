<?php

namespace App\Filament\Pages;

use App\Enums\NavigationLinkType;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Site\SitePageSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected string $view = 'filament.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Site settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SitePageSettings::current()->toFormData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pages')
                    ->tabs([
                        Tab::make('Navigation')
                            ->icon(Heroicon::OutlinedBars3)
                            ->schema([
                                $this->headerLinksRepeater(),
                            ]),

                        // Sections for the home page and, later, the category, tag and
                        // article pages are added here as each frontend page is built.
                        Tab::make('Home page')
                            ->icon(Heroicon::OutlinedHome)
                            ->schema([
                                Text::make('Controls for the home page sections land here as each one is wired up.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::setValue(SitePageSettings::SETTINGS_KEY, SitePageSettings::fromFormData($this->form->getState()));

        Notification::make()
            ->title('Site settings saved')
            ->success()
            ->send();
    }

    /**
     * The builder for the top navigation bar, which every page of the site renders.
     */
    private function headerLinksRepeater(): Repeater
    {
        return Repeater::make('header_links')
            ->label('Header links')
            ->helperText('Shown in the top navigation bar, left to right. Drag or use the arrows to reorder.')
            ->addActionLabel('Add link')
            ->defaultItems(0)
            ->collapsible()
            ->reorderableWithButtons()
            ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
            ->columns(3)
            ->schema([
                Select::make('type')
                    ->label('Type')
                    ->options(NavigationLinkType::class)
                    ->default(NavigationLinkType::Category->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),

                Select::make('category_id')
                    ->label('Category')
                    ->required()
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get): bool => NavigationLinkType::tryFromMixed($get('type')) === NavigationLinkType::Category)
                    ->options(fn (): array => Category::query()->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getSearchResultsUsing(fn (string $search): array => Category::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Category::find($value)?->name),

                TextInput::make('label')
                    ->label('Label override')
                    ->placeholder('Defaults to the category name')
                    ->maxLength(60)
                    ->live(onBlur: true),
            ]);
    }

    /**
     * Label a collapsed repeater item the way the link reads in the navigation bar.
     *
     * @param  array<string, mixed>  $state
     */
    private static function itemLabel(array $state): ?string
    {
        if (filled($state['label'] ?? null)) {
            return trim((string) $state['label']);
        }

        return Category::query()->whereKey($state['category_id'] ?? null)->value('name');
    }
}
