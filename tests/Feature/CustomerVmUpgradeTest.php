<?php

namespace Tests\Feature;

use App\Jobs\ApplyVmUpgradeJob;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use App\Services\ProxmoxService;
use App\Services\UsageBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CustomerVmUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_request_bundle_upgrade_and_checkpoint_old_usage(): void
    {
        CarbonImmutable::setTestNow('2026-06-20 12:00:00');
        Bus::fake();

        [$customer, $vm, $targetBundle] = $this->upgradeCatalog();
        $customer->wallet()->update(['balance' => 50000]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/'.$vm->id)->post($this->customerBaseUrl.'/servers/'.$vm->id.'/upgrades/bundle', [
            'vm_bundle_id' => $targetBundle->id,
        ])->assertRedirect($this->customerBaseUrl.'/servers/'.$vm->id);

        $this->assertDatabaseHas('vm_upgrade_orders', [
            'customer_id' => $customer->id,
            'virtual_machine_id' => $vm->id,
            'to_bundle_id' => $targetBundle->id,
            'type' => VmUpgradeOrder::TYPE_BUNDLE,
            'status' => VmUpgradeOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'amount' => -200,
        ]);
        $this->assertTrue($vm->refresh()->last_billed_at->equalTo(now()));
        Bus::assertDispatched(ApplyVmUpgradeJob::class);

        CarbonImmutable::setTestNow();
    }

    public function test_customer_cannot_downgrade_bundle_through_upgrade_flow(): void
    {
        Bus::fake();

        [$customer, $vm] = $this->upgradeCatalog();
        $smallBundle = VmBundle::create([
            'name' => 'Small',
            'slug' => 'small',
            'cpu_cores' => 1,
            'ram_gb' => 2,
            'disk_gb' => 20,
            'ip_count' => 1,
            'monthly_price' => 36500,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/'.$vm->id)->post($this->customerBaseUrl.'/servers/'.$vm->id.'/upgrades/bundle', [
            'vm_bundle_id' => $smallBundle->id,
        ])->assertRedirect($this->customerBaseUrl.'/servers/'.$vm->id)
            ->assertSessionHasErrors('bundle');

        $this->assertDatabaseCount('vm_upgrade_orders', 0);
        Bus::assertNotDispatched(ApplyVmUpgradeJob::class);
    }

    public function test_apply_bundle_upgrade_job_updates_proxmox_and_local_resources(): void
    {
        [$customer, $vm, $targetBundle] = $this->upgradeCatalog();
        $order = VmUpgradeOrder::create([
            'customer_id' => $customer->id,
            'virtual_machine_id' => $vm->id,
            'from_bundle_id' => $vm->vm_bundle_id,
            'to_bundle_id' => $targetBundle->id,
            'type' => VmUpgradeOrder::TYPE_BUNDLE,
            'status' => VmUpgradeOrder::STATUS_PENDING,
            'before_snapshot' => $vm->desiredStateSnapshot(),
            'after_snapshot' => [
                'vm_bundle_id' => $targetBundle->id,
                'bundle_name' => $targetBundle->name,
                'cpu_cores' => $targetBundle->cpu_cores,
                'ram_gb' => $targetBundle->ram_gb,
                'disk_gb' => $targetBundle->disk_gb,
                'ip_count' => $targetBundle->ip_count,
            ],
            'estimated_monthly_delta' => 73000,
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('updateVmHardware')->once()->andReturn(['task_id' => 'UPID:hardware']);
            $mock->shouldReceive('resizeDisk')->once()->withAnyArgs()->andReturn(['task_id' => 'UPID:resize']);
            $mock->shouldReceive('waitForTask')->twice()->withAnyArgs()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmConfig')->once()->andReturn(['cores' => 4, 'memory' => 8192, 'scsi0' => 'local-lvm:80']);
        });

        app(ApplyVmUpgradeJob::class, ['orderId' => $order->id])->handle(app(ProxmoxService::class));

        $vm->refresh();
        $order->refresh();

        $this->assertSame($targetBundle->id, $vm->vm_bundle_id);
        $this->assertSame(4, $vm->cpu_cores);
        $this->assertSame(8, $vm->ram_gb);
        $this->assertSame(80, $vm->disk_gb);
        $this->assertSame(VmUpgradeOrder::STATUS_SUCCEEDED, $order->status);
    }

    public function test_apply_extra_disk_job_attaches_next_scsi_disk_and_bills_storage(): void
    {
        CarbonImmutable::setTestNow('2026-06-20 12:00:00');

        [$customer, $vm] = $this->upgradeCatalog();
        $order = VmUpgradeOrder::create([
            'customer_id' => $customer->id,
            'virtual_machine_id' => $vm->id,
            'type' => VmUpgradeOrder::TYPE_EXTRA_DISK,
            'status' => VmUpgradeOrder::STATUS_PENDING,
            'before_snapshot' => $vm->desiredStateSnapshot(),
            'after_snapshot' => ['size_gb' => 10, 'storage' => 'local-lvm'],
            'estimated_monthly_delta' => 10000,
        ]);
        $disk = VmDisk::create([
            'virtual_machine_id' => $vm->id,
            'vm_upgrade_order_id' => $order->id,
            'disk_device' => 'pending-'.$order->id,
            'storage' => 'local-lvm',
            'size_gb' => 10,
            'status' => VmDisk::STATUS_PENDING,
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn(['scsi0' => 'local-lvm:40']);
            $mock->shouldReceive('attachDisk')->once()->withAnyArgs()->andReturn(['task_id' => 'UPID:disk']);
            $mock->shouldReceive('waitForTask')->once()->withAnyArgs()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmConfig')->once()->andReturn(['scsi0' => 'local-lvm:40', 'scsi1' => 'local-lvm:10']);
        });

        app(ApplyVmUpgradeJob::class, ['orderId' => $order->id])->handle(app(ProxmoxService::class));

        $this->assertDatabaseHas('vm_disks', [
            'id' => $disk->id,
            'disk_device' => 'scsi1',
            'status' => VmDisk::STATUS_READY,
        ]);
        $this->assertSame(VmUpgradeOrder::STATUS_SUCCEEDED, $order->refresh()->status);

        CarbonImmutable::setTestNow('2026-06-20 14:00:00');
        $transaction = app(UsageBillingService::class)->chargeExtraDisk($disk->refresh());
        $this->assertSame('extra_disk_storage', $transaction->metadata['category']);
        $this->assertSame(-20, $transaction->amount);

        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{Customer, VirtualMachine, VmBundle}
     */
    private function upgradeCatalog(): array
    {
        ResourceRate::create([
            'resource' => ResourceRate::DISK,
            'label' => 'SSD Disk',
            'unit' => 'GB',
            'hourly_price' => 1,
            'monthly_price' => 730,
            'billing_policy' => ResourceRate::POLICY_ALWAYS,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create();
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
        $currentBundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 73000,
            'is_active' => true,
        ]);
        $targetBundle = VmBundle::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 146000,
            'is_active' => true,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vm_bundle_id' => $currentBundle->id,
            'vmid' => 101,
            'name' => 'upgrade-vm',
            'hostname' => 'upgrade-vm',
            'node' => 'pve1',
            'storage' => 'local-lvm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now()->subHours(2),
        ]);

        return [$customer, $vm, $targetBundle];
    }
}
