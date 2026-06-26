<?php

namespace Tests\Feature\Reseller;

use App\Models\Customer;
use App\Models\ResellerCustomer;
use App\Models\User;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    public function test_admin_can_enable_reseller_for_customer(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/resellers', [
            'customer_id' => $customer->id,
            'commission_pct' => 10.00,
            'payout_method' => 'auto_credit',
        ])->assertRedirect();

        $customer->refresh();
        $this->assertTrue($customer->is_reseller);
        $this->assertEquals('active', $customer->reseller_status);
        $this->assertEquals(10.00, $customer->reseller_commission_pct);
        $this->assertEquals('auto_credit', $customer->reseller_payout_method);
        $this->assertNotNull($customer->reseller_code);
    }

    public function test_admin_can_update_reseller_settings(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($admin, 'admin');

        $this->put($this->adminBaseUrl.'/resellers/'.$customer->id, [
            'commission_pct' => 15.00,
            'payout_method' => 'withdrawable',
        ])->assertRedirect();

        $customer->refresh();
        $this->assertEqualsWithDelta(15.00, (float) $customer->reseller_commission_pct, 0.01);
        $this->assertEquals('withdrawable', $customer->reseller_payout_method);
    }

    public function test_admin_can_suspend_and_activate_reseller(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($admin, 'admin');

        $this->patch($this->adminBaseUrl.'/resellers/'.$customer->id.'/suspend')->assertRedirect();
        $customer->refresh();
        $this->assertEquals('suspended', $customer->reseller_status);

        $this->patch($this->adminBaseUrl.'/resellers/'.$customer->id.'/activate')->assertRedirect();
        $customer->refresh();
        $this->assertEquals('active', $customer->reseller_status);
    }

    public function test_admin_can_disable_reseller(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');

        $this->actingAs($admin, 'admin');

        $this->delete($this->adminBaseUrl.'/resellers/'.$customer->id)->assertRedirect();

        $customer->refresh();
        $this->assertFalse($customer->is_reseller);
        $this->assertNull($customer->reseller_status);
        $this->assertNull($customer->reseller_code);
    }

    public function test_admin_can_assign_customer_to_reseller(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/resellers/'.$reseller->id.'/assign', [
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'admin',
            'unassigned_at' => null,
        ]);
    }

    public function test_admin_can_unassign_customer_from_reseller(): void
    {
        $admin = User::factory()->create();
        $reseller = Customer::factory()->create();
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'auto_credit');
        app(ResellerService::class)->assignCustomer($reseller, $customer, 'admin', $admin);

        $this->actingAs($admin, 'admin');

        $this->delete($this->adminBaseUrl.'/resellers/'.$reseller->id.'/assign/'.$customer->id)->assertRedirect();

        $this->assertDatabaseHas('reseller_customers', [
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => 'admin',
        ]);
        $this->assertNotNull(ResellerCustomer::where('reseller_id', $reseller->id)->where('customer_id', $customer->id)->first()->unassigned_at);
    }

    public function test_reseller_stats_return_correct_values(): void
    {
        $reseller = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($reseller, 10.00, 'withdrawable');

        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        app(ResellerService::class)->assignCustomer($reseller, $customer1, 'admin');
        app(ResellerService::class)->assignCustomer($reseller, $customer2, 'referral');

        $stats = app(ResellerService::class)->resellerStats($reseller);

        $this->assertEquals(2, $stats['active_customers']);
        $this->assertEquals(0, $stats['total_earned']);
        $this->assertEquals(0, $stats['pending_balance']);
        $this->assertEquals(0, $stats['monthly_commissions']);
    }
}
