<?php

namespace Tests\Unit;

use App\Services\WalletService;
use PHPUnit\Framework\TestCase;

class WalletServiceTest extends TestCase
{
    public function test_format_uses_toman_label_for_iranian_currencies(): void
    {
        $wallets = new WalletService;

        $this->assertSame('۴۹,۰۰۰ تومان', $wallets->format(490000, 'IRR'));
        $this->assertSame('۴۹۰,۰۰۰ تومان', $wallets->format(490000, 'IRT'));
        $this->assertSame('-۴۹,۰۰۰ تومان', $wallets->format(-490000, 'IRR'));
        $this->assertSame('۱ تومان', $wallets->format(10, 'IRR'));
        $this->assertSame('۱.۵ تومان', $wallets->format(15, 'IRR'));
    }

    public function test_format_keeps_non_iranian_currency_codes(): void
    {
        $wallets = new WalletService;

        $this->assertSame('۱۰ USD', $wallets->format(10, 'USD'));
    }
}
