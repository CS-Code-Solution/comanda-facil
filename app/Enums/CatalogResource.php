<?php

namespace App\Enums;

use App\Models\CatalogCategory;
use App\Models\CatalogOption;
use App\Models\CatalogOptionGroup;
use App\Models\CatalogProduct;
use App\Models\CatalogPromotion;
use App\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Model;

enum CatalogResource: string
{
    case Categories = 'categorias';
    case Products = 'produtos';
    case Variants = 'variacoes';
    case Groups = 'grupos';
    case Options = 'opcoes';
    case Promotions = 'promocoes';

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::Categories => CatalogCategory::class, self::Products => CatalogProduct::class,
            self::Variants => CatalogVariant::class, self::Groups => CatalogOptionGroup::class,
            self::Options => CatalogOption::class, self::Promotions => CatalogPromotion::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Categories => 'Categorias', self::Products => 'Produtos', self::Variants => 'Variações',
            self::Groups => 'Grupos de opções', self::Options => 'Opções e adicionais', self::Promotions => 'Promoções',
        };
    }
}
