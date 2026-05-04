<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_phone_only_on_customer_subdomain(): void
    {
        $response = $this->post('https://cp.aviato.ir/register', [
            'name' => 'Customer User',
            'phone' => '+15551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/dashboard');
        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', [
            'phone' => '+15551234567',
            'email' => null,
        ]);
    }

    public function test_admin_can_register_with_email_on_admin_subdomain(): void
    {
        $response = $this->post('https://admin.aviato.ir/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('https://admin.aviato.ir/dashboard');
        $this->assertAuthenticated('admin');
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'phone' => null,
        ]);
    }

    public function test_customer_can_login_with_email_on_customer_subdomain(): void
    {
        Customer::factory()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'password' => 'password',
        ]);

        $response = $this->post('https://cp.aviato.ir/login', [
            'login' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('https://cp.aviato.ir/dashboard');
        $this->assertAuthenticated('customer');
    }

    public function test_admin_and_customer_use_separate_auth_models(): void
    {
        Customer::factory()->create([
            'phone' => '+15557654321',
            'email' => null,
            'password' => 'password',
        ]);

        User::factory()->create([
            'phone' => '+15550001111',
            'email' => null,
            'password' => 'password',
        ]);

        $this->post('https://admin.aviato.ir/login', [
            'login' => '+15557654321',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $response = $this->post('https://admin.aviato.ir/login', [
            'login' => '+15550001111',
            'password' => 'password',
        ]);

        $response->assertRedirect('https://admin.aviato.ir/dashboard');
        $this->assertAuthenticated('admin');
    }
}
