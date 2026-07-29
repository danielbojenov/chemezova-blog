<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The nutritional class an ingredient belongs to. This lives on the ingredient
 * rather than the product because "Vitamin D" is a vitamin regardless of which
 * product contains it; a multivitamin's types are derived from its ingredients.
 */
enum IngredientType: string implements HasColor, HasLabel
{
    case Vitamin = 'vitamin';
    case Mineral = 'mineral';
    case AminoAcid = 'amino_acid';
    case FattyAcid = 'fatty_acid';
    case Probiotic = 'probiotic';
    case Botanical = 'botanical';
    case Enzyme = 'enzyme';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vitamin => 'Vitamin',
            self::Mineral => 'Mineral',
            self::AminoAcid => 'Amino acid',
            self::FattyAcid => 'Fatty acid',
            self::Probiotic => 'Probiotic',
            self::Botanical => 'Botanical',
            self::Enzyme => 'Enzyme',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vitamin => 'warning',
            self::Mineral => 'info',
            self::AminoAcid => 'success',
            self::FattyAcid => 'danger',
            self::Probiotic => 'primary',
            self::Botanical => 'success',
            self::Enzyme => 'info',
            self::Other => 'gray',
        };
    }
}
