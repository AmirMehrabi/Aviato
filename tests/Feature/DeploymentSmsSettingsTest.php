<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\DeploymentSmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentSmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/deployment-sms-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        config(['deployment-sms.path' => $this->directory, 'portals.admin.domain' => 'admin.localhost']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_admin_can_save_recipients_and_switches_without_exposing_api_key(): void
    {
        AppSetting::setValue(AppSetting::KAVENEGAR_API_KEY, 'secret');
        $this->actingAs(User::factory()->create(), 'admin')->patch('https://admin.localhost/settings/deployment-sms', [
            'enabled' => 0, 'recipients' => '09135512366, 09135512366', 'max_attempts' => 3,
            'events' => ['success' => ['enabled' => 1, 'template' => 'aviato-deploy-success-v1']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $settings = app(DeploymentSmsSettings::class)->read();
        $this->assertSame(['09135512366'], $settings['recipients']);
        $this->assertFalse($settings['enabled']);
        $this->assertTrue($settings['events']['success']['enabled']);
        $this->assertArrayNotHasKey('api_key', $settings);
        $this->assertSame('secret', json_decode(file_get_contents($this->directory.'/config.json'), true)['api_key']);
    }

    public function test_admin_can_view_history_cancel_and_retry_failed_delivery(): void
    {
        $db = new \PDO('sqlite:'.$this->directory.'/deliveries.sqlite');
        $db->exec('CREATE TABLE deliveries (id INTEGER PRIMARY KEY, event TEXT, revision TEXT, release TEXT, phone TEXT, template TEXT, status TEXT, attempts INTEGER, next_attempt REAL, created_at REAL, sent_at REAL, provider_id TEXT, error TEXT)');
        $db->exec("INSERT INTO deliveries VALUES(1,'success','abcdef123','147','09135512366','approved','failed',5,0,1000,NULL,NULL,'failed')");
        $this->actingAs(User::factory()->create(), 'admin')->get('https://admin.localhost/settings/deployment-sms')->assertOk()->assertSee('09135512366');
        $this->post('https://admin.localhost/settings/deployment-sms/actions', ['action' => 'retry', 'id' => 1])->assertSessionHasNoErrors();
        $this->assertSame('pending', $db->query('SELECT status FROM deliveries')->fetchColumn());
        $this->post('https://admin.localhost/settings/deployment-sms/actions', ['action' => 'cancel', 'id' => 1])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $db->query('SELECT status FROM deliveries')->fetchColumn());
    }

    public function test_invalid_phone_and_template_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')->patch('https://admin.localhost/settings/deployment-sms', [
            'recipients' => '123', 'max_attempts' => 3, 'events' => ['success' => ['template' => 'valid']],
        ])->assertSessionHasErrors('recipients');
        $this->patch('https://admin.localhost/settings/deployment-sms', [
            'recipients' => '09135512366', 'max_attempts' => 3, 'events' => ['success' => ['template' => '../secret']],
        ])->assertSessionHasErrors('events.success.template');
    }

    public function test_guest_cannot_change_deployment_sms(): void
    {
        $this->patch('https://admin.localhost/settings/deployment-sms', [])->assertRedirect();
        $this->assertFileDoesNotExist($this->directory.'/config.json');
    }
}
