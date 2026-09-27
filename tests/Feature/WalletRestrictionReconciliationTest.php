<?php

namespace Tests\Feature;

use App\Jobs\ReconcileWalletRestrictions;
use App\Models\Customer;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProxmoxService;
use App\Services\WalletNetworkRestrictionService;
use App\Services\WalletRestrictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WalletRestrictionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_freeze_is_not_recorded_as_complete_and_debt_shutdown_still_runs(): void
    {
        $owner = Customer::factory()->create();
        $server = ProxmoxServer::create([
            'name' => 'PVE', 'datacenter' => 'dc', 'host' => 'pve.local',
            'port' => 8006, 'realm' => 'pam', 'username' => 'root',
            'api_token_id' => 'root@pam!panel', 'api_token_secret' => 'secret',
            'is_active' => true, 'maintenance_mode' => false,
        ]);
        $bundle = VmBundle::create([
            'name' => 'Wallet test', 'slug' => 'wallet-test',
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1,
            'monthly_price' => 730000, 'is_active' => true,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id, 'proxmox_server_id' => $server->id,
            'vm_bundle_id' => $bundle->id, 'node' => 'pve1', 'vmid' => 101,
            'name' => 'failed-freeze-vm', 'cpu_cores' => 2, 'ram_gb' => 4,
            'disk_gb' => 40, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ]);
        $networks = $this->mock(WalletNetworkRestrictionService::class);
        $networks->shouldReceive('freeze')->twice()->andThrow(new RuntimeException('network API unavailable'));
        $proxmox = $this->mock(ProxmoxService::class);
        $proxmox->shouldReceive('shutdownVm')->once()->andReturn(['task_id' => 'UPID:stop']);
        $proxmox->shouldReceive('waitForTask')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
        $proxmox->shouldReceive('waitForVmStopped')->once()->andReturn(['status' => 'stopped']);
        $restrictions = app(WalletRestrictionService::class);

        $restrictions->reconcile($owner);
        $this->assertSame(VirtualMachine::STATUS_RUNNING, $vm->fresh()->status);
        $this->assertNull(data_get($vm->fresh()->wallet_restriction, 'network_frozen_at'));
        $this->assertSame('network API unavailable', data_get($vm->fresh()->wallet_restriction, 'last_error'));

        $owner->wallet()->update(['balance' => -73000]);
        $restrictions->reconcile($owner);
        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->fresh()->status);
        $this->assertTrue((bool) data_get($vm->fresh()->wallet_restriction, 'wallet_stopped'));
    }

    public function test_scheduled_reconciliation_queues_wallet_owners_with_vms(): void
    {
        Queue::fake();
        $owner = Customer::factory()->create();
        VirtualMachine::create([
            'customer_id' => $owner->id,
            'name' => 'scheduled-vm',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->artisan('billing:reconcile-wallet-restrictions')->assertSuccessful();

        Queue::assertPushed(ReconcileWalletRestrictions::class, fn (ReconcileWalletRestrictions $job): bool => $job->customerId === $owner->id);
    }
}
