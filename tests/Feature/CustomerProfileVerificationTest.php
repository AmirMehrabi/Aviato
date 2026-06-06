<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_verify_national_code_from_profile(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');
        $this->patch($this->customerBaseUrl.'/profile/national-code', [
            'national_code' => '0100000002',
        ])->assertRedirect();

        $customer->refresh();

        $this->assertTrue($customer->hasVerifiedNationalCode());
        $this->assertSame('0100000002', $customer->national_code);
        $this->assertNotNull($customer->national_code_hash);
    }

    public function test_customer_cannot_verify_invalid_national_code(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/profile')
            ->patch($this->customerBaseUrl.'/profile/national-code', [
                'national_code' => '1111111111',
            ])
            ->assertRedirect($this->customerBaseUrl.'/profile')
            ->assertSessionHasErrors('national_code');

        $this->assertFalse($customer->fresh()->hasVerifiedNationalCode());
    }

    public function test_national_code_must_be_unique_between_customers(): void
    {
        Customer::factory()->create([
            'national_code' => '0100000002',
            'national_code_hash' => hash('sha256', '0100000002'),
            'national_code_verified_at' => now(),
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/profile')
            ->patch($this->customerBaseUrl.'/profile/national-code', [
                'national_code' => '0100000002',
            ])
            ->assertRedirect($this->customerBaseUrl.'/profile')
            ->assertSessionHasErrors('national_code');

        $this->assertFalse($customer->fresh()->hasVerifiedNationalCode());
    }
}
