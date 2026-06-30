<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

        $response = $this->post($this->adminBaseUrl.'/customers/'.$customer->id.'/impersonate');

        $location = $response->headers->get('Location');

        $response->assertRedirectContains('https://cp.localhost/impersonate/');
        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($admin, 'admin');

        $this->get($location)
            ->assertRedirect('https://cp.localhost/dashboard')
            ->assertSessionHas('impersonated_by_admin_id', $admin->id)
            ->assertSessionHas('impersonated_customer_id', $customer->id);

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_impersonation_handoff_is_single_use(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin');

        $location = $this->post($this->adminBaseUrl.'/customers/'.$customer->id.'/impersonate')
            ->headers->get('Location');

        $this->get($location)->assertRedirect('https://cp.localhost/dashboard');

        $this->get($location)->assertForbidden();
    }

    public function test_expired_or_unknown_impersonation_handoff_is_rejected(): void
    {
        Cache::clear();

        $this->get('https://cp.localhost/impersonate/'.str_repeat('a', 64))
            ->assertForbidden();
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
            ->assertRedirect('https://admin.localhost/dashboard');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }
}
