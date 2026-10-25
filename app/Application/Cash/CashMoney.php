<?php

namespace App\Application\Cash;

use App\Domain\Catalog\Money;

class CashMoney
{
    public static function parse(mixed $input, string $field, bool $signed = false): int
    {
        if ($signed && is_string($input) && str_starts_with(trim($input), '-')) {
            return -Money::minor(substr(trim($input), 1), $field, true);
        }

        return Money::minor($input, $field, true);
    }

    public static function label(int $cents): string
    {
        return $cents < 0 ? str_replace('R$ ', 'R$ -', Money::label(abs($cents))) : Money::label($cents);
    }
}
