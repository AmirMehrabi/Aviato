<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_layout_exposes_account_actions_on_desktop_and_mobile(): void
    {
        $customer = Customer::factory()->create(['name' => 'مشتری آزمایشی']);

        $response = $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('حساب کاربری '.$customer->name, false)
            ->assertSee('aria-controls="customer-account-menu"', false)
            ->assertSee('lg:hidden', false)
            ->assertSee(route('customer.profile.show', [], false));

        $this->assertSame(2, substr_count($response->getContent(), 'خروج از حساب'));
        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('customer.logout', [], false).'"'));
    }

    public function test_customer_can_sign_out_from_account_actions(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/logout')
            ->assertRedirect();

        $this->assertGuest('customer');
    }
}
