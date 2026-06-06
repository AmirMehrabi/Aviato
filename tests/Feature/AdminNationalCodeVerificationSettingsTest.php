<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNationalCodeVerificationSettingsTest extends TestCase
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

    public function test_admin_can_enable_and_disable_national_code_verification(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $basePayload = [
            'currency' => 'IRR',
            'customer_verification_mode' => 'email',
            'national_code_verification_enabled' => 0,
            'national_code_verification_token' => '',
            'sms_gateway' => 'sms0098',
            'sms0098_username' => 'demo-user',
            'sms0098_password' => '',
            'sms0098_panel_no' => '1234',
            'kavenegar_api_key' => '',
            'kavenegar_template' => '',
            'vm_creation_charge_enabled' => 0,
            'vm_creation_charge_percentage' => 0,
            'unverified_customer_vm_limit' => 2,
            'verified_customer_vm_limit' => 0,
            'deleted_vm_cooldown_days' => 30,
            'vm_rebuild_fee_multiplier_percentage' => 50,
        ];

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', $basePayload)
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertFalse(AppSetting::nationalCodeVerificationEnabled());

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', array_merge($basePayload, [
                'national_code_verification_enabled' => 1,
                'national_code_verification_token' => 'shahkar-secret',
            ]))
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertTrue(AppSetting::nationalCodeVerificationEnabled());
        $this->assertSame('shahkar-secret', AppSetting::nationalCodeVerificationToken());
    }
}
