<?php

namespace Tests\Unit;

use App\Application\Cash\CashMoney;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashMoneyTest extends TestCase
{
    public function test_cash_and_electronic_reconciliation_keep_exact_cents(): void
    {
        $this->assertSame(12345, CashMoney::parse('123,45', 'counted'));
        $this->assertSame(0, CashMoney::parse('0', 'counted'));
        $this->assertSame(-12345, CashMoney::parse('-123.45', 'counted', true));
        $this->assertSame('R$ -123,45', CashMoney::label(-12345));
        $this->assertSame('R$ -0,05', CashMoney::label(-5));
    }

    public function test_cash_count_cannot_be_negative(): void
    {
        $this->expectException(ValidationException::class);
        CashMoney::parse('-1,00', 'cash');
    }

    public function test_floats_and_ambiguous_thousands_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        CashMoney::parse(1.23, 'amount');
    }
}
