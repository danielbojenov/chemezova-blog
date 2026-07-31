<?php

namespace App\Filament\Support;

use App\Enums\AffiliateLinkStatus;
use App\Enums\ProductCardOverride;
use App\Enums\ProductStatus;
use App\Models\AffiliateLink;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\Product;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * An article content block referencing a catalog product. Everything shown on the
 * card — image, price, rating, brand, ingredients — is pulled live from the
 * product, so a catalog correction updates every article at once. Only the two
 * override choices (retailer links and description) are stored per article;
 * editorial commentary goes in ordinary rich text blocks around the card.
 *
 * The card carries no rank. Ranks are derived from the position of each product
 * card within the article, so removing one can never leave a gap in the numbering.
 */
class ProductCardBlock
{
    /**
     * Prefix on the collapsed block header, so the article's structure stays
     * readable when every block is collapsed.
     */
    public const string LABEL_PREFIX = 'Product card';

    public static function make(): Block
    {
        return Block::make('productCard')
            ->icon(Heroicon::Cube)
            ->label(function (?array $state): string {
                if ($state === null) {
                    return self::LABEL_PREFIX;
                }

                $name = Product::query()->whereKey($state['product_id'] ?? null)->value('name');

                return $name === null
                    ? self::LABEL_PREFIX
                    : self::LABEL_PREFIX.' — '.$name;
            })
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(fn (): array => self::products()->limit(50)->pluck('name', 'id')->all())
                    ->getSearchResultsUsing(fn (string $search): array => self::products()
                        ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%"))
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Product::find($value)?->name)
                    // Without this the slug below is validated for uniqueness
                    // against the article being edited rather than the products table.
                    ->actionSchemaModel(Product::class)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        SlugInput::make('name'),
                        Select::make('brand_id')
                            ->label('Brand')
                            ->searchable()
                            ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all()),
                        Select::make('primary_ingredient_id')
                            ->label('Primary ingredient')
                            ->searchable()
                            ->options(fn (): array => Ingredient::query()->orderBy('name')->pluck('name', 'id')->all()),
                    ])
                    ->createOptionUsing(fn (array $data): int => Product::create([
                        ...$data,
                        'status' => ProductStatus::Draft,
                    ])->getKey())
                    ->helperText('New products are created as drafts — finish them in the catalog.'),

                /**
                 * A thumbnail of the selected product, so an author reordering
                 * blocks can recognise a card by its bottle rather than by reading
                 * the header. Kept in sync by the `live()` select above.
                 */
                View::make('filament.forms.product-card-preview')
                    ->viewData(fn (Get $get): array => [
                        'product' => Product::with(['brand', 'primaryIngredient', 'ingredients'])
                            ->find($get('product_id')),
                    ]),

                Select::make('description_mode')
                    ->label('Description')
                    ->options(ProductCardOverride::class)
                    ->default(ProductCardOverride::Inherit)
                    /**
                     * Filament does not back-fill defaults for keys missing from
                     * already-saved block data, so without this a card stored
                     * before this field existed would fail the required rule on
                     * its next save.
                     */
                    ->formatStateUsing(fn (mixed $state): string => ProductCardOverride::resolve($state)->value)
                    ->required()
                    ->live(),
                ContentRichEditor::withoutHeadings('description')
                    ->label('Description override')
                    ->visible(fn (Get $get): bool => ProductCardOverride::resolve($get('description_mode')) === ProductCardOverride::Custom)
                    ->requiredIf('description_mode', ProductCardOverride::Custom->value),

                Select::make('links_mode')
                    ->label('Retailer links')
                    ->options(ProductCardOverride::class)
                    ->default(ProductCardOverride::Inherit)
                    ->formatStateUsing(fn (mixed $state): string => ProductCardOverride::resolve($state)->value)
                    ->required()
                    ->live(),
                Select::make('affiliate_link_ids')
                    ->label('Links')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->maxItems(Product::MAX_AFFILIATE_LINKS)
                    ->options(fn (): array => AffiliateLink::query()
                        ->where('status', AffiliateLinkStatus::Active)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->visible(fn (Get $get): bool => ProductCardOverride::resolve($get('links_mode')) === ProductCardOverride::Custom)
                    ->requiredIf('links_mode', ProductCardOverride::Custom->value),
            ]);
    }

    /**
     * @return EloquentBuilder<Product>
     */
    private static function products(): EloquentBuilder
    {
        return Product::query()->orderBy('name');
    }
}
