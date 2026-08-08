<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_sees_task_oriented_settings_cards(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('https://admin.localhost/settings')
            ->assertOk()
            ->assertSee('تنظیمات عمومی')
            ->assertSee('پرداخت آنلاین')
            ->assertSee(route('admin.settings.section', 'payments'), false);
    }

    public function test_admin_can_update_a_single_settings_section(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/settings/general', ['currency' => 'USD'])
            ->assertRedirect('https://admin.localhost/settings/general')
            ->assertSessionHas('status');

        $this->assertSame('USD', AppSetting::currency());
    }

    public function test_admin_can_configure_company_identity_and_receipt_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/settings/general', [
                'currency' => 'IRR',
                'company_name' => 'شرکت زیرساخت ابری آویاتو',
                'company_logo' => UploadedFile::fake()->image('aviato-logo.png', 400, 160),
                'company_national_id' => '14001234567',
                'company_registration_number' => '123456',
                'company_economic_code' => '411111111111',
                'company_phone' => '02112345678',
                'company_email' => 'billing@aviato.ir',
                'company_address' => 'تهران، خیابان نمونه، پلاک ۱',
                'company_postal_code' => '1234567890',
            ])
            ->assertRedirect('https://admin.localhost/settings/general')
            ->assertSessionHas('status');

        $profile = AppSetting::companyProfile();

        $this->assertSame('شرکت زیرساخت ابری آویاتو', $profile['name']);
        $this->assertSame('14001234567', $profile['national_id']);
        $this->assertSame('billing@aviato.ir', $profile['email']);
        $this->assertStringContainsString('/storage/company/', $profile['logo_url']);
        Storage::disk('public')->assertExists(AppSetting::getValue(AppSetting::COMPANY_LOGO_PATH));
    }

    public function test_payment_section_preserves_secret_when_password_is_blank(): void
    {
        $admin = User::factory()->create();
        AppSetting::setValue(AppSetting::MELLAT_PASSWORD, 'existing-secret', 'string', 'payment');

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/settings/payments', [
                'payments_enabled' => 1,
                'default_payment_gateway' => 'mellat',
                'mellat_payment_enabled' => 1,
                'mellat_payment_mode' => 'test',
                'mellat_terminal_id' => 1234,
                'mellat_username' => 'merchant',
                'mellat_password' => '',
                'hesabro_payment_enabled' => 0,
                'hesabro_client' => '',
                'hesabro_client_id' => '',
                'hesabro_client_secret' => '',
            ])
            ->assertRedirect('https://admin.localhost/settings/payments');

        $this->assertSame('existing-secret', AppSetting::mellatPassword());
    }

    public function test_admin_can_enable_zibal_and_select_it_as_default_gateway(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->patch('https://admin.localhost/settings/payments', [
                'payments_enabled' => 1,
                'default_payment_gateway' => 'zibal',
                'mellat_payment_enabled' => 0,
                'hesabro_payment_enabled' => 0,
                'zibal_payment_enabled' => 1,
                'zibal_merchant' => 'zibal-test-merchant',
            ])
            ->assertRedirect('https://admin.localhost/settings/payments');

        $this->assertTrue(AppSetting::zibalPaymentEnabled());
        $this->assertTrue(AppSetting::zibalPaymentConfigured());
        $this->assertSame('zibal', AppSetting::defaultPaymentGateway());
    }
}
