<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerAutoSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_can_toggle_customer_auto_suspension(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch($this->adminBaseUrl.'/customers/'.$customer->id.'/auto-suspension', [
                'auto_suspend_vms' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'auto_suspend_vms' => 0,
        ]);
    }

    public function test_customer_cannot_toggle_auto_suspension(): void
    {
        $customer = Customer::factory()->create();

        $this->patch($this->adminBaseUrl.'/customers/'.$customer->id.'/auto-suspension', [
            'auto_suspend_vms' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'auto_suspend_vms' => 1,
        ]);
    }
}
