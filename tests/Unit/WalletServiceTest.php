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

    public function test_available_balance_rounds_up_to_ten_thousand_toman_without_changing_exact_format(): void
    {
        $wallets = new WalletService;

        $this->assertSame('۵۰,۰۰۰ تومان', $wallets->formatAvailableBalance(490001, 'IRR'));
        $this->assertSame('۵۰,۰۰۰ تومان', $wallets->formatAvailableBalance(49001, 'IRT'));
        $this->assertSame('۵۰,۰۰۰ تومان', $wallets->formatAvailableBalance(500000, 'IRR'));
        $this->assertSame('۰ تومان', $wallets->formatAvailableBalance(0, 'IRR'));
        $this->assertSame('-۱۰,۰۰۰ تومان', $wallets->formatAvailableBalance(-1, 'IRR'));
        $this->assertSame('-۵۰,۰۰۰ تومان', $wallets->formatAvailableBalance(-490001, 'IRR'));
        $this->assertSame('۴۹,۰۰۰.۱ تومان', $wallets->format(490001, 'IRR'));
    }
}
