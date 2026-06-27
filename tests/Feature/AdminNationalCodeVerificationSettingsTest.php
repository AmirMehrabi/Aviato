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
            'tax_enabled' => 0,
            'tax_rate_percentage' => 0,
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

    public function test_admin_can_save_mellat_payment_settings_and_preserve_password(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $payload = [
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
            'tax_enabled' => 0,
            'tax_rate_percentage' => 0,
            'payments_enabled' => 1,
            'default_payment_gateway' => 'mellat',
            'mellat_payment_enabled' => 1,
            'mellat_payment_mode' => 'test',
            'mellat_terminal_id' => 1234,
            'mellat_username' => 'merchant',
            'mellat_password' => 'mellat-secret',
        ];

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', $payload)
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertTrue(AppSetting::mellatPaymentEnabled());
        $this->assertTrue(AppSetting::paymentsEnabled());
        $this->assertSame('mellat', AppSetting::defaultPaymentGateway());
        $this->assertSame('test', AppSetting::mellatPaymentMode());
        $this->assertSame('1234', AppSetting::mellatTerminalId());
        $this->assertSame('merchant', AppSetting::mellatUsername());
        $this->assertSame('mellat-secret', AppSetting::mellatPassword());

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', array_merge($payload, [
                'mellat_payment_mode' => 'production',
                'mellat_password' => '',
            ]))
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertSame('production', AppSetting::mellatPaymentMode());
        $this->assertSame('mellat-secret', AppSetting::mellatPassword());

        $hesabroPayload = array_merge($payload, [
            'default_payment_gateway' => 'hesabro',
            'mellat_payment_enabled' => 0,
            'hesabro_payment_enabled' => 1,
            'hesabro_client' => 'sabz-co',
            'hesabro_client_id' => 'hesabro-client',
            'hesabro_client_secret' => 'hesabro-secret',
        ]);

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', $hesabroPayload)
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertTrue(AppSetting::hesabroPaymentEnabled());
        $this->assertSame('hesabro', AppSetting::defaultPaymentGateway());
        $this->assertSame('hesabro-client', AppSetting::hesabroClientId());
        $this->assertSame('hesabro-secret', AppSetting::hesabroClientSecret());

        $this->from($this->adminBaseUrl.'/settings')
            ->patch($this->adminBaseUrl.'/settings', array_merge($hesabroPayload, [
                'hesabro_client_secret' => '',
            ]))
            ->assertRedirect($this->adminBaseUrl.'/settings')
            ->assertSessionHas('status');

        $this->assertSame('hesabro-secret', AppSetting::hesabroClientSecret());
    }
}
