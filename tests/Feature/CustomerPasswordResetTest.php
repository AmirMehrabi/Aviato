<?php

namespace Tests\Feature;

use App\Jobs\ResetVirtualMachinePassword;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\VmActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_requires_reboot_consent_and_cloud_init(): void
    {
        Bus::fake();
        [$customer, $vm] = $this->server();
        $this->actingAs($customer, 'customer');
        $this->post($this->url($vm), [])->assertSessionHasErrors('confirm_reboot');
        Bus::assertNothingDispatched();
        $vm->cloudImage->update(['cloud_init_enabled' => false]);
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertNotFound();
        $this->get($this->url($vm, false))->assertOk()->assertDontSee('بازنشانی رمز عبور');
    }

    public function test_new_server_reset_password_is_shown_once_and_not_stored(): void
    {
        Bus::fake();
        [$customer, $vm] = $this->server(false);
        $this->actingAs($customer, 'customer');
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertRedirect($this->url($vm, false));
        $password = session('password_reset_password');
        $this->assertNotEmpty($password);
        $this->assertNull($vm->refresh()->login_password);
        $this->assertSame('pending', $vm->password_reset_status);
        $this->assertTrue($vm->isActionLocked());
        Bus::assertDispatched(ResetVirtualMachinePassword::class, function ($job) use ($password): bool {
            return $job->legacyPassword === null && ! str_contains(serialize($job), $password)
                && crypt($password, $job->passwordHash) === $job->passwordHash;
        });
        $this->get($this->url($vm, false))->assertOk()->assertSee($password)->assertSee('سرور راه‌اندازی مجدد خواهد شد');
        $this->get($this->url($vm, false))->assertOk()->assertDontSee($password);
    }

    public function test_pending_or_stopped_server_cannot_queue_another_reset(): void
    {
        Bus::fake();
        [$customer, $vm] = $this->server();
        $this->actingAs($customer, 'customer');
        $vm->update(['password_reset_status' => 'pending']);
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertSessionHas('error');
        $vm->update(['password_reset_status' => null, 'status' => 'stopped']);
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertSessionHas('error');
        Bus::assertNothingDispatched();
    }

    public function test_other_customers_and_viewers_cannot_reset_password(): void
    {
        Bus::fake();
        [$owner, $vm] = $this->server();
        $other = Customer::factory()->create();
        $this->actingAs($other, 'customer');
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertNotFound();
        ProjectMember::create(['project_id' => $vm->project_id, 'customer_id' => $other->id, 'role' => ProjectMember::ROLE_VIEWER]);
        $this->post($this->url($vm), ['confirm_reboot' => 1])->assertNotFound();
        Bus::assertNothingDispatched();
    }

    public function test_legacy_server_still_displays_its_existing_password(): void
    {
        [$customer, $vm] = $this->server();
        $this->actingAs($customer, 'customer');
        $this->get($this->url($vm, false))->assertOk()->assertViewHas('loginPassword', 'legacy-password');
        $this->assertTrue($vm->refresh()->retain_login_password);
    }

    public function test_job_resets_without_guest_agent_and_cold_boots_vm(): void
    {
        [$customer, $vm] = $this->server(false);
        $vm->update(['password_reset_status' => 'pending', 'remote_state' => ['password_reset_id' => 'reset-1']]);
        $hash = VirtualMachine::hashCloudInitPassword('new-password');
        $this->mock(ProxmoxService::class, function ($mock) use ($hash): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn(['ide2' => 'local-lvm:vm-101-cloudinit,media=cdrom', 'agent' => 0]);
            $mock->shouldReceive('vmStatus')->once()->ordered()->andReturn(['status' => 'running']);
            $mock->shouldReceive('setCloudInitPassword')->once()->ordered()->withArgs(fn ($server, $node, $vmid, $password): bool => $password === $hash)->andReturnNull();
            $mock->shouldReceive('shutdownVm')->once()->ordered()->withArgs(fn ($server, $node, $vmid, $fallback, $context, $force): bool => $fallback === false && $force === false)->andReturn(['task_id' => 'shutdown']);
            $mock->shouldReceive('waitForTask')->once()->ordered()->withArgs(fn ($server, $node, $task, $timeout): bool => $task === 'shutdown')->andReturn([]);
            $mock->shouldReceive('vmStatus')->once()->ordered()->andReturn(['status' => 'stopped']);
            $mock->shouldReceive('regenerateCloudInit')->once()->ordered()->andReturn(['task_id' => 'cloudinit']);
            $mock->shouldReceive('waitForTask')->once()->ordered()->withArgs(fn ($server, $node, $task, $timeout): bool => $task === 'cloudinit')->andReturn([]);
            $mock->shouldReceive('startVm')->once()->ordered()->andReturn(['task_id' => 'start']);
            $mock->shouldReceive('waitForTask')->once()->ordered()->withArgs(fn ($server, $node, $task, $timeout): bool => $task === 'start')->andReturn([]);
        });
        (new ResetVirtualMachinePassword($vm->id, 'reset-1', $hash))->handle(app(ProxmoxService::class), app(VmActivityRecorder::class));
        $this->assertSame('succeeded', $vm->refresh()->password_reset_status);
        $this->assertSame($hash, $vm->login_password_hash);
        $this->assertNull($vm->login_password);
        $this->assertFalse($vm->isActionLocked());
        $this->assertDatabaseHas('vm_activities', ['virtual_machine_id' => $vm->id, 'event' => 'password_reset', 'outcome' => 'succeeded']);
    }

    public function test_job_rejects_custom_metadata_without_changing_password_or_rebooting(): void
    {
        [, $vm] = $this->server();
        $vm->update(['password_reset_status' => 'pending', 'remote_state' => ['password_reset_id' => 'reset-1']]);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn(['ide2' => 'local:vm-cloudinit', 'cicustom' => 'meta=local:snippets/meta.yaml']);
            $mock->shouldNotReceive('setCloudInitPassword');
            $mock->shouldNotReceive('shutdownVm');
        });
        try {
            (new ResetVirtualMachinePassword($vm->id, 'reset-1', 'hash'))->handle(app(ProxmoxService::class), app(VmActivityRecorder::class));
            $this->fail('Expected unsupported metadata to fail.');
        } catch (RuntimeException) {
            $this->assertSame('failed', $vm->refresh()->password_reset_status);
            $this->assertSame('legacy-password', $vm->login_password);
        }
    }

    public function test_failure_after_config_update_preserves_new_legacy_password_and_unlocks_vm(): void
    {
        [, $vm] = $this->server();
        $vm->update(['password_reset_status' => 'pending', 'remote_state' => ['password_reset_id' => 'reset-1']]);
        $hash = VirtualMachine::hashCloudInitPassword('replacement-password');
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn(['ide2' => 'local:vm-cloudinit']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
            $mock->shouldReceive('setCloudInitPassword')->once()->andReturnNull();
            $mock->shouldReceive('shutdownVm')->once()->andThrow(new RuntimeException('shutdown failed'));
            $mock->shouldNotReceive('startVm');
        });
        try {
            (new ResetVirtualMachinePassword($vm->id, 'reset-1', $hash, Crypt::encryptString('replacement-password')))->handle(app(ProxmoxService::class), app(VmActivityRecorder::class));
            $this->fail('Expected shutdown failure.');
        } catch (RuntimeException) {
            $this->assertSame('failed', $vm->refresh()->password_reset_status);
            $this->assertSame('replacement-password', $vm->login_password);
            $this->assertFalse($vm->isActionLocked());
        }
    }

    public function test_pending_reset_blocks_deletion_and_status_polling_exposes_no_secret(): void
    {
        Bus::fake();
        [$customer, $vm] = $this->server(false);
        $vm->update(['password_reset_status' => 'pending', 'login_password_hash' => VirtualMachine::hashCloudInitPassword('not-visible')]);
        $this->actingAs($customer, 'customer');
        $this->delete($this->url($vm, false), ['delete_confirmation' => $vm->display_name])->assertSessionHas('error');
        Bus::assertNothingDispatched();
        $response = $this->get('https://cp.localhost/servers/statuses?ids[]='.$vm->uuid)->assertOk();
        $response->assertJsonPath('servers.0.password_reset_status', 'pending')->assertJsonPath('servers.0.action_pending', true);
        $response->assertDontSee($vm->login_password_hash)->assertDontSee('not-visible');
    }

    private function url(VirtualMachine $vm, bool $reset = true): string
    {
        return 'https://cp.localhost/servers/'.$vm->uuid.($reset ? '/reset-password' : '');
    }

    private function server(bool $legacy = true): array
    {
        $customer = Customer::factory()->create();
        $host = ProxmoxServer::create(['name' => 'PVE', 'host' => 'pve.local', 'port' => 8006, 'username' => 'root', 'realm' => 'pam', 'is_active' => true]);
        $image = CloudImage::create(['name' => 'Ubuntu', 'slug' => 'ubuntu', 'proxmox_server_id' => $host->id, 'node' => 'pve1', 'template_vmid' => 9000, 'storage' => 'local-lvm', 'os_family' => 'ubuntu', 'default_username' => 'ubuntu', 'cloud_init_enabled' => true, 'is_active' => true]);
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id, 'proxmox_server_id' => $host->id, 'cloud_image_id' => $image->id,
            'name' => 'test-server', 'node' => 'pve1', 'vmid' => 101, 'login_username' => 'ubuntu',
            'login_password' => $legacy ? 'legacy-password' : null, 'retain_login_password' => $legacy,
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'status' => 'running', 'provisioning_status' => 'ready',
        ]);

        return [$customer, $vm];
    }
}
