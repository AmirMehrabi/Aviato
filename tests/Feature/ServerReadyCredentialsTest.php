<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmail;
use App\Mail\BrandedNotificationMail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerReadyCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_ready_emails_initial_credentials_without_persisting_them_in_notifications(): void
    {
        $queueManager = app('queue');
        Queue::fake();
        AppSetting::setValue('notifications.templates', ['server_ready' => ['email_enabled' => true]]);
        $customer = Customer::factory()->create();
        $password = 'Initial-Password<&123';
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id, 'name' => 'cloud-one', 'display_name' => 'My server',
            'ip_address' => '192.0.2.10', 'login_username' => 'ubuntu',
            'retain_login_password' => false, 'login_password_hash' => VirtualMachine::hashCloudInitPassword($password),
            'status' => 'stopped', 'provisioning_status' => 'pending', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40,
        ]);
        $vm->notificationPasswordEncrypted = Crypt::encryptString($password);
        $vm->update(['provisioning_status' => 'ready']);
        $this->assertNull($vm->fresh()->login_password);
        $this->assertStringNotContainsString($password, json_encode($customer->notifications()->first()->data));
        $this->assertStringNotContainsString($password, json_encode(DB::table('notification_deliveries')->get()));
        Queue::assertPushed(SendNotificationEmail::class, function ($job) use ($password, $queueManager): bool {
            $this->assertSame($password, $job->variables['initial_password']);
            $this->assertSame('192.0.2.10', $job->variables['server_ip']);
            $this->assertSame('ubuntu', $job->variables['username']);
            $html = (new BrandedNotificationMail('server_ready', $job->variables))->render();
            $this->assertStringContainsString(e($password), $html);
            $this->assertStringContainsString('پس از اولین ورود', $html);
            $this->assertStringContainsString('My server', $html);
            $id = $queueManager->connection('database')->push($job, '', 'mail');
            $this->assertStringNotContainsString($password, DB::table('jobs')->where('id', $id)->value('payload'));

            return true;
        });
    }

    public function test_credentials_and_change_password_instruction_survive_a_custom_message(): void
    {
        AppSetting::setValue('notifications.templates', ['server_ready' => ['body' => 'متن سفارشی']]);
        $html = (new BrandedNotificationMail('server_ready', ['server_name' => 'cloud-one', 'server_ip' => '192.0.2.10', 'username' => 'ubuntu', 'initial_password' => 'new-secret']))->render();
        foreach (['متن سفارشی', '192.0.2.10', 'ubuntu', 'new-secret', 'پس از اولین ورود'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }
}
