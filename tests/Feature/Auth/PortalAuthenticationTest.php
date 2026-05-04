<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_phone_only(): void
    {
        $response = $this->post('/register', [
            'name' => 'Customer User',
            'phone' => '+15551234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('users', [
            'phone' => '+15551234567',
            'email' => null,
            'role' => 'customer',
        ]);
    }

    public function test_customer_can_login_with_email(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'phone' => null,
            'role' => 'customer',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'login' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('customer');
    }

    public function test_admin_can_login_only_from_admin_portal(): void
    {
        User::factory()->create([
            'email' => null,
            'phone' => '+15557654321',
            'role' => 'admin',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => '+15557654321',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $response = $this->post('/admin/login', [
            'login' => '+15557654321',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated('admin');
    }

    public function test_role_guards_protect_portals(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer, 'customer')->get('/admin/dashboard')->assertRedirect('/admin/login');
        $this->actingAs($admin, 'admin')->get('/dashboard')->assertRedirect('/login');
    }
}
