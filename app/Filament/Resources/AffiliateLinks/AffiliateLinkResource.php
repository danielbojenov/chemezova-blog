<?php

namespace App\Filament\Resources\AffiliateLinks;

use App\Filament\Resources\AffiliateLinks\Pages\CreateAffiliateLink;
use App\Filament\Resources\AffiliateLinks\Pages\EditAffiliateLink;
use App\Filament\Resources\AffiliateLinks\Pages\ListAffiliateLinks;
use App\Filament\Resources\AffiliateLinks\Pages\ViewAffiliateLink;
use App\Filament\Resources\AffiliateLinks\Schemas\AffiliateLinkForm;
use App\Filament\Resources\AffiliateLinks\Schemas\AffiliateLinkInfolist;
use App\Filament\Resources\AffiliateLinks\Tables\AffiliateLinksTable;
use App\Models\AffiliateLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateLinkResource extends Resource
{
    protected static ?string $model = AffiliateLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AffiliateLinkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateLinkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateLinksTable::configure($table);
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
            'index' => ListAffiliateLinks::route('/'),
            'create' => CreateAffiliateLink::route('/create'),
            'view' => ViewAffiliateLink::route('/{record}'),
            'edit' => EditAffiliateLink::route('/{record}/edit'),
        ];
    }
}
