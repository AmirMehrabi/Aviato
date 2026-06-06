<?php

namespace Tests\Feature;

use App\Jobs\RunVmBackupJob;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBackupPolicy;
use App\Services\UsageBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CustomerBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_enable_backup_policy(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/backups')->patch($this->customerBaseUrl.'/backups/servers/'.$vm->uuid.'/policy', [
            'is_enabled' => '1',
            'frequency' => VmBackupPolicy::FREQUENCY_DAILY,
            'preferred_time' => '03:30',
            'retention_count' => 4,
            'backup_storage' => 'local',
        ])->assertRedirect($this->customerBaseUrl.'/backups')
            ->assertSessionHas('status');

        $policy = $vm->fresh()->backupPolicy()->firstOrFail();

        $this->assertTrue($policy->is_enabled);
        $this->assertSame(VmBackupPolicy::FREQUENCY_DAILY, $policy->frequency);
        $this->assertSame(4, $policy->retention_count);
        $this->assertSame('local', $policy->backup_storage);
        $this->assertNotNull($policy->next_run_at);
    }

    public function test_customer_can_queue_manual_backup(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $vm = $this->readyVm($customer);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/backups')->post($this->customerBaseUrl.'/backups/servers/'.$vm->uuid)
            ->assertRedirect($this->customerBaseUrl.'/backups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('vm_backups', [
            'virtual_machine_id' => $vm->id,
            'source' => VmBackup::SOURCE_MANUAL,
            'status' => VmBackup::STATUS_QUEUED,
        ]);

        Bus::assertDispatched(RunVmBackupJob::class);
    }

    public function test_customer_cannot_queue_backup_for_another_customer_vm(): void
    {
        Bus::fake();

        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->readyVm($owner);

        $this->actingAs($other, 'customer');
        $this->post($this->customerBaseUrl.'/backups/servers/'.$vm->uuid)->assertNotFound();

        $this->assertDatabaseCount('vm_backups', 0);
        Bus::assertNotDispatched(RunVmBackupJob::class);
    }

    public function test_ready_backup_storage_is_charged_to_wallet(): void
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 500000]);
        $vm = $this->readyVm($customer);

        ResourceRate::create([
            'resource' => ResourceRate::BACKUP,
            'label' => 'Backup Storage',
            'unit' => 'GB',
            'hourly_price' => 100,
            'monthly_price' => 73000,
            'is_active' => true,
        ]);

        $backup = VmBackup::create([
            'virtual_machine_id' => $vm->id,
            'source' => VmBackup::SOURCE_MANUAL,
            'status' => VmBackup::STATUS_READY,
            'node' => 'pve1',
            'storage' => 'local',
            'volid' => 'local:backup/vzdump-qemu-101-2026_05_25-030000.vma.zst',
            'filename' => 'vzdump-qemu-101-2026_05_25-030000.vma.zst',
            'size_bytes' => 10 * 1024 * 1024 * 1024,
            'finished_at' => now()->subHours(2),
            'last_billed_at' => now()->subHour(),
        ]);

        app(UsageBillingService::class)->chargeBackup($backup, now());

        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'amount' => -1000,
        ]);
        $this->assertSame(499000, $customer->wallet()->firstOrFail()->balance);
        $this->assertNotNull($backup->fresh()->last_billed_at);
    }

    private function readyVm(Customer $customer): VirtualMachine
    {
        $server = ProxmoxServer::create([
            'name' => 'THR Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 101,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'node' => 'pve1',
            'storage' => 'local-lvm',
            'network_bridge' => 'vmbr1',
            'ip_address' => '192.168.10.50',
            'login_username' => 'ubuntu',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
    }
}
