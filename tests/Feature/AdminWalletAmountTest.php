<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWalletAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_credits_and_debits_toman_in_each_storage_currency(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin');

        foreach (['IRR' => 10, 'IRT' => 1] as $currency => $multiplier) {
            AppSetting::setValue(AppSetting::BILLING_CURRENCY, $currency);
            $customer = Customer::factory()->create();
            $url = 'https://admin.localhost/customers/'.$customer->id.'/wallet-transactions';

            $this->post($url, ['type' => 'credit', 'amount_toman' => '۱۰۰٬۰۰۰', 'description' => 'Manual credit'])
                ->assertSessionHasNoErrors()->assertSessionHas('status', '۱۰۰,۰۰۰ تومان به کیف پول اضافه شد.');
            $this->assertSame(100000 * $multiplier, app(WalletService::class)->walletFor($customer)->balance);

            $this->post($url, ['type' => 'debit', 'amount_toman' => '25,000', 'description' => 'Manual debit'])
                ->assertSessionHasNoErrors()->assertSessionHas('status', '۲۵,۰۰۰ تومان از کیف پول کسر شد.');
            $this->assertSame(75000 * $multiplier, app(WalletService::class)->walletFor($customer)->balance);
            $this->assertDatabaseHas('wallet_transactions', ['customer_id' => $customer->id, 'amount' => -25000 * $multiplier]);
        }
    }

    public function test_invalid_or_legacy_amounts_do_not_change_wallet(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin');
        $customer = Customer::factory()->create();
        $url = 'https://admin.localhost/customers/'.$customer->id.'/wallet-transactions';

        foreach (['0', '-1', '1.5', 'abc', (string) PHP_INT_MAX, null] as $amount) {
            $this->post($url, ['type' => 'credit', 'amount_toman' => $amount, 'amount' => 100000, 'description' => 'Invalid'])
                ->assertSessionHasErrors('amount_toman');
        }

        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
