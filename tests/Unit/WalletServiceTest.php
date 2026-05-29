<?php

namespace Tests\Unit;

use App\Services\WalletService;
use PHPUnit\Framework\TestCase;

class WalletServiceTest extends TestCase
{
    public function test_format_uses_toman_label_for_iranian_currencies(): void
    {
        $wallets = new WalletService();

        $this->assertSame('49,000 تومان', $wallets->format(490000, 'IRR'));
        $this->assertSame('490,000 تومان', $wallets->format(490000, 'IRT'));
        $this->assertSame('-49,000 تومان', $wallets->format(-490000, 'IRR'));
        $this->assertSame('1 تومان', $wallets->format(10, 'IRR'));
        $this->assertSame('1.5 تومان', $wallets->format(15, 'IRR'));
    }

    public function test_format_keeps_non_iranian_currency_codes(): void
    {
        $wallets = new WalletService();

        $this->assertSame('10 USD', $wallets->format(10, 'USD'));
    }
}
