<?php

namespace App\Filament\Pages;

use App\Enums\ImageVariant;
use App\Models\Setting;
use App\Support\Images\ImageSizeSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ImageSettings extends Page
{
    protected string $view = 'filament.pages.image-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Image settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(ImageSizeSettings::current()->toFormData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (ImageVariant $variant) => Section::make($variant->getLabel())
                    ->description(match ($variant) {
                        ImageVariant::Original => 'The stored original is downsized to these limits when the upload is larger.',
                        default => 'Maximum widths for the '.mb_strtolower($variant->getLabel()).' variant.',
                    })
                    ->columns(2)
                    ->schema([
                        TextInput::make("{$variant->value}_landscape")
                            ->label('Max width — horizontal images')
                            ->numeric()
                            ->required()
                            ->minValue(50)
                            ->maxValue(10000)
                            ->suffix('px'),
                        TextInput::make("{$variant->value}_portrait")
                            ->label('Max width — vertical images')
                            ->numeric()
                            ->required()
                            ->minValue(50)
                            ->maxValue(10000)
                            ->suffix('px'),
                    ]),
                ImageVariant::cases(),
            ))
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::setValue(ImageSizeSettings::SETTINGS_KEY, ImageSizeSettings::fromFormData($this->form->getState()));

        Notification::make()
            ->title('Image settings saved')
            ->success()
            ->send();
    }
}
