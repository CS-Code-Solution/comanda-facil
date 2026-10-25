<?php

namespace App\Domain\Catalog;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function minor(mixed $value, string $field, bool $allowZero = false): int
    {
        if (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,6})(?:[,.][0-9]{1,2})?\z/', trim($value))) {
            throw ValidationException::withMessages([$field => 'Informe um valor decimal, sem separador de milhar.']);
        }

        $parts = explode('.', str_replace(',', '.', trim($value)));
        $minor = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');

        if ($minor > 100000000 || $minor < ($allowZero ? 0 : 1)) {
            throw ValidationException::withMessages([$field => 'Valor fora do intervalo permitido.']);
        }

        return $minor;
    }

    public static function decimal(int $value): string
    {
        return intdiv($value, 100).','.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function label(int $value): string
    {
        return 'R$ '.number_format(intdiv($value, 100), 0, ',', '.').','.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function discount(int $gross, string $type, int $value): int
    {
        return min($gross, $type === 'percentage' ? intdiv($gross * $value + 5000, 10000) : $value);
    }
}
