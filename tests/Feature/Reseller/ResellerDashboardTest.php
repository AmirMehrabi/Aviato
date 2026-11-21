<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FundsCustomerWallet;
use Tests\TestCase;

class ResellerDashboardTest extends TestCase
{
    use RefreshDatabase, FundsCustomerWallet;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_reseller_can_access_reseller_dashboard(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller')->assertOk();
    }

    public function test_reseller_can_access_customers_page(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller/customers')->assertOk();
    }

    public function test_reseller_can_access_commissions_page(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller/commissions')->assertOk();
    }

    public function test_commissions_page_groups_hourly_entries_by_service_date_customer_rate_and_status(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');
        $customer = Customer::factory()->create();
        $entry = [
            'customer_id' => $customer->id,
            'service_date' => '2026-11-20',
            'settlement_amount' => 1000,
            'commission_pct' => 10,
            'commission_amount' => 100,
            'payout_method' => 'withdrawable',
            'status' => 'pending',
        ];

        $reseller->resellerCommissions()->createMany([
            $entry,
            array_replace($entry, ['settlement_amount' => 3000, 'commission_amount' => 300]),
            array_replace($entry, ['status' => 'withdrawn']),
            array_replace($entry, ['commission_pct' => 20, 'commission_amount' => 200]),
            array_replace($entry, ['customer_id' => Customer::factory()->create()->id]),
            array_replace($entry, ['service_date' => '2026-11-19']),
        ]);

        $response = $this->actingAs($reseller, 'customer')
            ->get($this->customerBaseUrl.'/reseller/commissions')
            ->assertOk();

        $rows = $response->viewData('commissions');
        $this->assertSame(5, $rows->total());
        $this->assertSame(4000, $rows->first()->settlement_amount);
        $this->assertSame(400, $rows->first()->commission_amount);
        $this->assertSame($customer->id, $rows->first()->customer->id);
    }

    public function test_reseller_can_access_referral_page(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller/referral')->assertOk();
    }

    public function test_reseller_can_access_withdrawals_page(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'withdrawable');

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller/withdrawals')->assertOk();
    }

    public function test_non_reseller_cannot_access_reseller_routes(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller')->assertForbidden();
        $this->get($this->customerBaseUrl.'/reseller/customers')->assertForbidden();
        $this->get($this->customerBaseUrl.'/reseller/commissions')->assertForbidden();
        $this->get($this->customerBaseUrl.'/reseller/referral')->assertForbidden();
        $this->get($this->customerBaseUrl.'/reseller/withdrawals')->assertForbidden();
    }

    public function test_suspended_reseller_cannot_access_reseller_routes(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');
        app(ResellerService::class)->suspendReseller($customer);

        $this->actingAs($customer, 'customer');

        $this->get($this->customerBaseUrl.'/reseller')->assertForbidden();
    }
}
