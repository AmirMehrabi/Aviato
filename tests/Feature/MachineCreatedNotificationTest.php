<?php

namespace Tests\Feature;

use App\Jobs\SendMachineCreatedSms;
use App\Jobs\SendNotificationEmail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MachineCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_notifies_creator_and_authorized_active_admins_only_after_commit(): void
    {
        Queue::fake();
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar');
        $owner = Customer::factory()->create();
        $creator = Customer::factory()->create();
        $admin = User::factory()->create(['phone' => '09121234568']);
        $unauthorized = User::factory()->create(['role' => 'custom', 'permissions' => ['tickets.read']]);
        $inactive = User::factory()->create(['is_active' => false]);
        DB::beginTransaction();
        $vm = VirtualMachine::create(['customer_id' => $owner->id, 'created_by_customer_id' => $creator->id, 'name' => 'new-machine', 'vmid' => 123, 'node' => 'node1', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40]);
        $this->assertSame(0, $creator->notifications()->count());
        Queue::assertNothingPushed();
        DB::commit();
        $this->assertSame('vm_created_customer', $creator->notifications()->first()->data['event']);
        $this->assertSame('vm_created_admin', $admin->notifications()->first()->data['event']);
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $unauthorized->notifications()->count());
        Queue::assertPushed(SendNotificationEmail::class, 2);
        Queue::assertPushed(SendMachineCreatedSms::class, 2);
        $vm->update(['name' => 'renamed']);
        Queue::assertPushed(SendMachineCreatedSms::class, 2);
    }

    public function test_rollback_does_not_notify_and_admin_event_is_not_a_customer_preference(): void
    {
        Queue::fake();
        $creator = Customer::factory()->create();
        DB::beginTransaction();
        VirtualMachine::create(['customer_id' => $creator->id, 'name' => 'rolled-back', 'vmid' => 124, 'node' => 'node1', 'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40]);
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertSame(0, $creator->notifications()->count());
        $this->assertArrayNotHasKey('vm_created_admin', app(NotificationTemplates::class)->customerEvents());
    }

    public function test_admin_can_configure_machine_sms_and_restore_defaults(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $url = 'https://admin.localhost/settings/notifications/templates';
        $data = ['event' => 'vm_created_admin', 'subject' => 'Machine {{machine_id}}', 'body' => '{{creator_name}} created {{server_name}}', 'email_enabled' => 1, 'in_app_enabled' => 1, 'sms_enabled' => 0, 'sms_template' => 'custom-vm-admin'];
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $templates = app(NotificationTemplates::class);
        $this->assertFalse($templates->enabled('vm_created_admin', 'sms'));
        $this->assertSame('custom-vm-admin', $templates->all()['vm_created_admin']['sms_template']);
        $this->patch($url, $data + ['restore' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($templates->enabled('vm_created_admin', 'sms'));
        $this->get('https://admin.localhost/settings/notifications')->assertOk()->assertSee('aviato-vm-created-admin');
    }

    public function test_sms_job_uses_configured_template_and_respects_disable(): void
    {
        $customer = Customer::factory()->create(['phone' => '09121234567']);
        AppSetting::setValue(AppSetting::SMS_GATEWAY, 'kavenegar');
        $sms = $this->mock(KavenegarLookupClient::class);
        $sms->shouldReceive('sendLookup')->once()->with($customer->phone, 'aviato-vm-created-customer', '123');
        $job = new SendMachineCreatedSms($customer->getMorphClass(), $customer->id, 'vm_created_customer', '123');
        $job->handle(app(NotificationTemplates::class), $sms);
        AppSetting::setValue('notifications.templates', ['vm_created_customer' => ['sms_enabled' => false]]);
        $job->handle(app(NotificationTemplates::class), $sms);
    }
}
