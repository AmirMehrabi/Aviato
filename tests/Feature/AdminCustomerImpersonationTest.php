<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerImpersonationTest extends TestCase
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

    public function test_admin_can_impersonate_customer_from_admin_portal(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/customers/'.$customer->id.'/impersonate')
            ->assertRedirect('https://cp.localhost/dashboard')
            ->assertSessionHas('impersonated_by_admin_id', $admin->id)
            ->assertSessionHas('impersonated_customer_id', $customer->id);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_customer_logout_during_impersonation_keeps_admin_session(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->actingAs($customer, 'customer');
        $this->withSession([
            'impersonated_by_admin_id' => $admin->id,
            'impersonated_customer_id' => $customer->id,
        ]);

        $this->post('https://cp.localhost/logout')
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }
}
