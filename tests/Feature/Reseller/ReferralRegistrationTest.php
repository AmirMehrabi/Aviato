<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Models\ResellerCustomer;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_handle_referral_registration_creates_assignment(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $customer = Customer::factory()->create();

        app(ResellerService::class)->handleReferralRegistration($customer, $reseller->reseller_code);

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
        ]);
    }

    public function test_handle_referral_registration_ignores_invalid_code(): void
    {
        $customer = Customer::factory()->create();

        app(ResellerService::class)->handleReferralRegistration($customer, 'INVALID');

        $this->assertDatabaseCount('reseller_customers', 0);
    }

    public function test_handle_referral_registration_ignores_inactive_reseller(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        $reseller->update(['reseller_status' => 'suspended']);

        $customer = Customer::factory()->create();
        app(ResellerService::class)->handleReferralRegistration($customer, $reseller->reseller_code);

        $this->assertDatabaseCount('reseller_customers', 0);
    }

    public function test_handle_referral_registration_replaces_previous_reseller(): void
    {
        $reseller1 = Customer::factory()->create();
        $reseller2 = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller1, 10.00, 'auto_credit');
        app(ResellerService::class)->enableReseller($reseller2, 15.00, 'auto_credit');

        $customer = Customer::factory()->create();
        app(ResellerService::class)->assignCustomer($reseller1, $customer, 'admin');
        app(ResellerService::class)->handleReferralRegistration($customer, $reseller2->reseller_code);

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller2->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'referral',
            'unassigned_at' => null,
        ]);

        $oldAssignment = ResellerCustomer::where('reseller_id', $reseller1->id)->where('customer_id', $customer->id)->first();
        $this->assertNotNull($oldAssignment->unassigned_at);
    }
}
