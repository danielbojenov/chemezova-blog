<?php

namespace App\Filament\Pages;

use App\Enums\ContentStatus;
use App\Enums\NavigationLinkType;
use App\Models\Category;
use App\Models\Page as ContentPage;
use App\Models\Setting;
use App\Models\Tag;
use App\Support\Images\ContentImageProcessor;
use App\Support\Images\SettingsImageProcessor;
use App\Support\Images\TmpUploadName;
use App\Support\Site\SiteGeneralSettings;
use App\Support\Site\SitePageSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
        $this->fillFromSettings();
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

                        // Sections for the category, tag and article pages are added here
                        // as each frontend page is built.
                        Tab::make('Home page')
                            ->icon(Heroicon::OutlinedHome)
                            ->schema([
                                $this->featuredArticleSection(),
                            ]),

                        Tab::make('General')
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->schema([
                                $this->authorSection(),
                                $this->readingTimeSection(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // The avatar is submitted as a path in the shared staging directory the first time
        // it is uploaded; converting it here means only the final path is ever stored.
        $state['author_avatar'] = app(SettingsImageProcessor::class)->process(
            filled($state['author_avatar'] ?? null) ? (string) $state['author_avatar'] : null,
            SettingsImageProcessor::AUTHOR_DIRECTORY,
        );

        Setting::setValue(SitePageSettings::SETTINGS_KEY, SitePageSettings::fromFormData($state));
        Setting::setValue(SiteGeneralSettings::SETTINGS_KEY, SiteGeneralSettings::fromFormData($state));

        // Re-read rather than leave the submitted state in place: the upload field is
        // still holding the staging path its file has just been moved out of, and saving
        // a second time would store that dead path.
        $this->fillFromSettings();

        Notification::make()
            ->title('Site settings saved')
            ->success()
            ->send();
    }

    /**
     * Load the form from what is stored, defaults included.
     */
    private function fillFromSettings(): void
    {
        $this->form->fill([
            ...SitePageSettings::current()->toFormData(),
            ...SiteGeneralSettings::current()->toFormData(),
        ]);
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
     * The hero block at the top of the home page.
     */
    private function featuredArticleSection(): Section
    {
        return Section::make('Featured article')
            ->key('featuredArticle')
            ->description('The block at the top of the home page. One published article carrying the tag below fills it, picked at random on each visit.')
            ->headerActions([
                Action::make('resetFeatured')
                    ->label('Reset to defaults')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->link()
                    // The button label is deliberately left alone: it is wording the
                    // editor chose for the site, not part of the tag choice.
                    ->action(function (Set $set): void {
                        $defaults = SitePageSettings::defaults()['home']['featured'];

                        $set('featured_tag', $defaults['tag']);
                        $set('featured_title', $defaults['title']);
                    }),
            ])
            ->columns(2)
            ->schema([
                Select::make('featured_tag')
                    ->label('Featured tag')
                    ->helperText('While no tag has this slug, or no published article carries it, the home page leaves the block out entirely.')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => Tag::query()->orderBy('name')->limit(50)->pluck('name', 'slug')->all())
                    ->getSearchResultsUsing(fn (string $search): array => Tag::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'slug')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): string => self::tagOptionLabel($value)),

                TextInput::make('featured_title')
                    ->label('Tag label')
                    ->helperText('Shown above the headline, ahead of the article\'s own first tag.')
                    ->required()
                    ->maxLength(40),

                TextInput::make('featured_button_label')
                    ->label('Button label')
                    ->required()
                    ->maxLength(40),
            ]);
    }

    /**
     * The byline the site publishes under. Articles carry no author of their own, so this
     * is the one the featured block and, later, the article pages render.
     */
    private function authorSection(): Section
    {
        return Section::make('Author')
            ->description('Shown in article bylines.')
            ->columns(2)
            ->schema([
                TextInput::make('author_name')
                    ->label('Name')
                    ->maxLength(80),

                Select::make('author_page_id')
                    ->label('Author page')
                    ->helperText('The name links here. Leave empty, or choose a page that is later unpublished, and it renders as plain text.')
                    ->placeholder('Do not link the name')
                    ->searchable()
                    ->options(fn (): array => self::authorPageOptions())
                    ->getSearchResultsUsing(fn (string $search): array => self::authorPageOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ContentPage::find($value)?->title),

                FileUpload::make('author_avatar')
                    ->label('Photo')
                    ->avatar()
                    ->imageEditor()
                    ->disk(ContentImageProcessor::DISK)
                    ->directory(ContentImageProcessor::TMP_DIRECTORY)
                    ->visibility('public')
                    ->maxSize(10240)
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => TmpUploadName::for($file),
                    )
                    ->columnSpanFull(),
            ]);
    }

    private function readingTimeSection(): Section
    {
        return Section::make('Reading time')
            ->description('How the "8 min read" estimate on every article is worked out.')
            ->schema([
                TextInput::make('characters_per_minute')
                    ->label('Characters read per minute')
                    ->helperText('1,000 is roughly 200 words a minute, the usual pace for this kind of writing. Lower it to quote longer times.')
                    ->numeric()
                    ->required()
                    ->minValue(SiteGeneralSettings::MIN_CHARACTERS_PER_MINUTE)
                    ->maxValue(SiteGeneralSettings::MAX_CHARACTERS_PER_MINUTE),
            ]);
    }

    /**
     * Published pages, which are the only ones worth linking a byline to.
     *
     * @return array<int, string>
     */
    private static function authorPageOptions(?string $search = null): array
    {
        return ContentPage::query()
            ->where('status', ContentStatus::Published)
            ->when($search !== null, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->limit(50)
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * Label the selected featured tag.
     *
     * The default slug names a tag that does not exist until someone creates it, so a
     * value with no tag behind it is spelled out rather than shown as an empty select —
     * it is the reason the home page would be leaving the block out.
     */
    private static function tagOptionLabel(mixed $value): string
    {
        $slug = trim((string) $value);

        $name = Tag::query()->where('slug', $slug)->value('name');

        return is_string($name) ? $name : "{$slug} — no tag with this slug yet";
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
