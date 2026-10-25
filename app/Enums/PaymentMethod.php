<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Dinheiro', self::Pix => 'Pix', self::Card => 'Cartão',
        };
    }
}
