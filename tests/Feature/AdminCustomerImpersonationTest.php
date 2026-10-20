<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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

    public function test_impersonation_does_not_create_or_email_a_sign_in_notification(): void
    {
        Queue::fake();
        AppSetting::setValue('notifications.templates', ['signed_in' => ['email_enabled' => true, 'in_app_enabled' => true]]);
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($admin, 'admin');
        $location = $this->post($this->adminBaseUrl.'/customers/'.$customer->id.'/impersonate')->headers->get('Location');
        $this->get($location)->assertRedirect('https://cp.localhost/dashboard');
        $this->assertSame(0, $customer->notifications()->where('data->event', 'signed_in')->count());
        Queue::assertNotPushed(SendNotificationEmail::class);
    }

    public function test_impersonation_logout_without_admin_guard_redirects_to_admin_login(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->withSession([
            'impersonated_by_admin_id' => $admin->id,
            'impersonated_customer_id' => $customer->id,
            'customer' => ['active_project_id' => $customer->ensureDefaultProject()->id],
        ])->post('https://cp.localhost/logout')->assertRedirect('https://admin.localhost/login')
            ->assertSessionMissing('impersonated_by_admin_id')->assertSessionMissing('customer');
        $this->assertGuest('customer');
        $this->assertGuest('admin');
    }

    public function test_logout_preserves_the_persisted_admin_guard_session(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create();
        Auth::guard('admin')->login($admin);
        Auth::guard('customer')->login($customer);
        $this->withSession([
            'impersonated_by_admin_id' => $admin->id,
            'impersonated_customer_id' => $customer->id,
        ])->post('https://cp.localhost/logout')->assertRedirect('https://admin.localhost/dashboard');
        // Resolve fresh guards to avoid a cached user masking a lost session login.
        Auth::forgetGuards();
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }

    public function test_direct_customer_login_still_creates_a_sign_in_notification(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['password' => 'customer-password']);
        $this->post('https://cp.localhost/login', ['login' => $customer->email, 'password' => 'customer-password'])->assertRedirect();
        $this->assertSame(1, $customer->notifications()->where('data->event', 'signed_in')->count());
    }
}
