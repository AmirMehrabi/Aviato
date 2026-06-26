<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerDashboardTest extends TestCase
{
    use RefreshDatabase;

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
