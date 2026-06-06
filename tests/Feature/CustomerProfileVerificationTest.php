<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerProfileVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_customer_can_verify_national_code_from_profile_when_verification_is_enabled(): void
    {
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_ENABLED, true, 'boolean', 'customer');
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_TOKEN, 'secret-token', 'string', 'customer');

        $customer = Customer::factory()->create([
            'phone' => '09120000001',
        ]);

        Http::fake(function (HttpRequest $request) {
            $this->assertSame('https://service.zohal.io/api/v0/services/inquiry/shahkar', $request->url());
            $this->assertSame('Bearer secret-token', $request->header('Authorization')[0] ?? null);
            $this->assertSame([
                'mobile' => '09120000001',
                'national_code' => '0100000002',
            ], $request->data());

            return Http::response([
                'response_body' => [
                    'data' => [
                        'matched' => true,
                    ],
                    'error_code' => null,
                    'message' => 'موفق',
                ],
                'result' => 1,
            ], 200);
        });

        $this->actingAs($customer, 'customer');
        $this->patch($this->customerBaseUrl.'/profile/national-code', [
            'national_code' => '0100000002',
        ])->assertRedirect();

        $customer->refresh();

        $this->assertTrue($customer->hasVerifiedNationalCode());
        $this->assertSame('0100000002', $customer->national_code);
        $this->assertNotNull($customer->national_code_hash);
    }

    public function test_customer_can_save_national_code_without_api_verification_when_disabled(): void
    {
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_ENABLED, false, 'boolean', 'customer');

        $customer = Customer::factory()->create([
            'phone' => '09120000002',
        ]);

        Http::fake();

        $this->actingAs($customer, 'customer');
        $this->patch($this->customerBaseUrl.'/profile/national-code', [
            'national_code' => '0100000002',
        ])->assertRedirect();

        Http::assertNothingSent();

        $customer->refresh();

        $this->assertTrue($customer->hasVerifiedNationalCode());
        $this->assertSame('0100000002', $customer->national_code);
    }

    public function test_customer_is_limited_to_five_national_code_verification_attempts_per_hour(): void
    {
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_ENABLED, true, 'boolean', 'customer');
        AppSetting::setValue(AppSetting::NATIONAL_CODE_VERIFICATION_TOKEN, 'secret-token', 'string', 'customer');

        $customer = Customer::factory()->create([
            'phone' => '09120000003',
        ]);

        RateLimiter::clear('national-code-verification:customer:'.$customer->id);

        Http::fake([
            'service.zohal.io/*' => Http::response([
                'response_body' => [
                    'data' => [],
                    'error_code' => 'invalid',
                    'message' => 'national_code: National code is wrong',
                ],
                'result' => 6,
            ], 400),
        ]);

        $this->actingAs($customer, 'customer');

        for ($i = 0; $i < 5; $i++) {
            $this->from($this->customerBaseUrl.'/profile')
                ->patch($this->customerBaseUrl.'/profile/national-code', [
                    'national_code' => '0100000002',
                ])
                ->assertRedirect($this->customerBaseUrl.'/profile')
                ->assertSessionHasErrors('national_code');
        }

        $this->from($this->customerBaseUrl.'/profile')
            ->patch($this->customerBaseUrl.'/profile/national-code', [
                'national_code' => '0100000002',
            ])
            ->assertRedirect($this->customerBaseUrl.'/profile')
            ->assertSessionHasErrors('national_code');

        Http::assertSentCount(5);
        $this->assertFalse($customer->fresh()->hasVerifiedNationalCode());
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
