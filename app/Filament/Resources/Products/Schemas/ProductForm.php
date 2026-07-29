<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\AffiliateLinkStatus;
use App\Enums\IngredientType;
use App\Enums\ProductComposition;
use App\Enums\ProductStatus;
use App\Enums\SupplementForm;
use App\Filament\Support\SlugInput;
use App\Models\Product;
use App\Support\Images\ProductImageProcessor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        SlugInput::make('name'),
                        Select::make('status')
                            ->options(ProductStatus::class)
                            ->default(ProductStatus::Draft)
                            ->required(),
                        TextInput::make('rating')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1)
                            ->helperText('Editorial score out of 5, to one decimal place.'),
                    ]),
                Section::make('Classification')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                SlugInput::make('name'),
                            ]),
                        Select::make('form')
                            ->options(SupplementForm::class)
                            ->searchable(),
                        Select::make('primary_ingredient_id')
                            ->label('Primary ingredient')
                            ->relationship('primaryIngredient', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('The headline ingredient this product is bought for.')
                            ->createOptionForm(self::ingredientOptionForm()),
                        Select::make('composition')
                            ->options(ProductComposition::class),
                        Select::make('ingredients')
                            ->relationship('ingredients', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->createOptionForm(self::ingredientOptionForm()),
                    ]),
                Section::make('Pricing')
                    ->columns(3)
                    ->columnSpanFull()
                    ->description('The price per dose is calculated from these values.')
                    ->schema([
                        TextInput::make('price')
                            ->label('Pack price')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('doses_per_pack')
                            ->label('Doses per pack')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('currency')
                            ->required()
                            ->default('USD')
                            ->maxLength(3),
                    ]),
                Section::make('Image')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image')
                            ->hiddenLabel()
                            ->image()
                            ->imageEditor()
                            ->disk(ProductImageProcessor::DISK)
                            ->directory(ProductImageProcessor::TMP_DIRECTORY)
                            ->visibility('public')
                            ->maxSize(10240)
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => self::storageFileName($file),
                            ),
                    ]),
                Section::make('Description')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('description')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
                Section::make('Retailer links')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('affiliateLinks')
                            ->hiddenLabel()
                            ->relationship(
                                'affiliateLinks',
                                'name',
                                fn (Builder $query): Builder => $query->where('status', AffiliateLinkStatus::Active),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->maxItems(Product::MAX_AFFILIATE_LINKS)
                            ->helperText('Up to two retailer links. Articles may override or hide these per product card.'),
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
     * The inline creation form shared by both ingredient selects.
     *
     * @return array<int, Component>
     */
    private static function ingredientOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            SlugInput::make('name'),
            Select::make('type')
                ->options(IngredientType::class)
                ->default(IngredientType::Other)
                ->required(),
        ];
    }

    /**
     * Slug of the original name plus a temporary marker stripped after conversion,
     * with a server-derived extension (the client extension is never trusted).
     */
    private static function storageFileName(TemporaryUploadedFile $file): string
    {
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return ($baseName !== '' ? $baseName : 'image')
            .'-tmp'.Str::lower(Str::random(6))
            .'.'.$file->extension();
    }
}
