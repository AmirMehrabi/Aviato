<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
