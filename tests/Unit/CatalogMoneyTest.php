<?php

namespace Tests\Unit;

use App\Domain\Catalog\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogMoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_decimal_strings_are_converted_exactly(string $input, int $cents): void
    {
        $this->assertSame($cents, Money::minor($input, 'price', true));
    }

    public static function validAmounts(): array
    {
        return [['0,00', 0], ['19,90', 1990], ['0.01', 1], ['1234.56', 123456], ['1000000', 100000000]];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_or_ambiguous_money_is_rejected(mixed $input): void
    {
        $this->expectException(ValidationException::class);
        Money::minor($input, 'price');
    }

    public static function badAmounts(): array
    {
        return [[-1], [19.9], ['1.000,00'], ['1,001'], ['NaN'], ['1e3'], [''], ['-0,01'], ['0'], ['1000000,01']];
    }
}
