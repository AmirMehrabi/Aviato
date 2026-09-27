<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HetznerAccount;
use App\Models\InfrastructureLocation;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use App\Services\WalletNetworkRestrictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class WalletNetworkRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeze_preserves_existing_disabled_interfaces_and_restores_only_managed_links(): void
    {
        $owner = Customer::factory()->create();
        $server = ProxmoxServer::create([
            'name' => 'PVE', 'datacenter' => 'dc', 'host' => 'pve.local',
            'port' => 8006, 'realm' => 'pam', 'username' => 'root',
            'api_token_id' => 'root@pam!panel', 'api_token_secret' => 'secret',
            'is_active' => true, 'maintenance_mode' => false,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id, 'proxmox_server_id' => $server->id,
            'node' => 'pve1', 'vmid' => 101, 'name' => 'net-test',
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ])->load('proxmoxServer');
        $networkUp = ['net0' => 'virtio,bridge=vmbr1,firewall=1', 'net1' => 'virtio,bridge=vmbr2,link_down=1'];
        $networkDown = ['net0' => 'virtio,bridge=vmbr1,firewall=1,link_down=1', 'net1' => 'virtio,bridge=vmbr2,link_down=1'];
        $proxmox = $this->mock(ProxmoxService::class);
        $proxmox->shouldReceive('vmConfig')->times(4)->andReturn($networkUp, $networkDown, $networkDown, $networkUp);
        $proxmox->shouldReceive('setVmNetworkLinkState')->twice()
            ->with(Mockery::type(ProxmoxServer::class), 'pve1', 101, Mockery::type('bool'), 'net0')
            ->andReturn(['task_id' => null]);

        $networks = app(WalletNetworkRestrictionService::class);
        $networks->freeze($vm, ['resume_on_funding' => true]);

        $state = $vm->fresh()->wallet_restriction;
        $this->assertSame(['net0'], $state['network_interfaces']);
        $this->assertNotNull($state['network_frozen_at']);

        $networks->restore($vm, $state);
    }

    public function test_hetzner_public_network_is_frozen_and_restored_through_dedicated_firewall(): void
    {
        $owner = Customer::factory()->create();
        $account = HetznerAccount::create(['name' => 'cloud', 'api_token' => 'test-token', 'is_active' => true]);
        $location = InfrastructureLocation::create([
            'provider' => InfrastructureLocation::PROVIDER_HETZNER,
            'hetzner_account_id' => $account->id,
            'name' => 'Germany',
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'infrastructure_location_id' => $location->id,
            'provider' => VirtualMachine::PROVIDER_HETZNER,
            'remote_id' => '123',
            'name' => 'hetzner-wallet-vm',
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ])->load('infrastructureLocation.hetznerAccount');

        $applied = false;
        Http::fake(function ($request) use (&$applied) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/v1/servers/123') {
                return Http::response(['server' => ['id' => 123, 'public_net' => ['ipv4' => ['id' => 1]], 'private_net' => []]]);
            }
            if ($request->method() === 'GET' && $path === '/v1/firewalls') {
                return Http::response(['firewalls' => [], 'meta' => ['pagination' => ['last_page' => 1]]]);
            }
            if ($request->method() === 'POST' && $path === '/v1/firewalls') {
                return Http::response(['firewall' => ['id' => 33]]);
            }
            if ($request->method() === 'GET' && $path === '/v1/firewalls/33') {
                return Http::response(['firewall' => ['applied_to' => $applied ? [['type' => 'server', 'server' => ['id' => 123]]] : []]]);
            }
            if ($request->method() === 'POST' && $path === '/v1/firewalls/33/actions/apply_to_resources') {
                $applied = true;

                return Http::response(['actions' => [['id' => 77]]]);
            }
            if ($request->method() === 'POST' && $path === '/v1/firewalls/33/actions/remove_from_resources') {
                $applied = false;

                return Http::response(['actions' => [['id' => 78]]]);
            }
            if ($request->method() === 'GET' && str_starts_with($path, '/v1/actions/')) {
                return Http::response(['action' => ['status' => 'success']]);
            }

            return Http::response(['error' => 'Unexpected request'], 500);
        });

        $networks = app(WalletNetworkRestrictionService::class);
        $networks->freeze($vm, ['resume_on_funding' => true]);
        $this->assertSame(33, data_get($vm->fresh()->wallet_restriction, 'hetzner_firewall_id'));
        $this->assertTrue($applied);

        $networks->restore($vm, $vm->fresh()->wallet_restriction);
        $this->assertFalse($applied);
    }

    public function test_lxc_uses_container_network_api(): void
    {
        $owner = Customer::factory()->create();
        $server = ProxmoxServer::create([
            'name' => 'PVE LXC', 'datacenter' => 'dc', 'host' => 'pve.local',
            'port' => 8006, 'realm' => 'pam', 'username' => 'root',
            'api_token_id' => 'root@pam!panel', 'api_token_secret' => 'secret',
            'is_active' => true, 'maintenance_mode' => false,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id, 'proxmox_server_id' => $server->id,
            'node' => 'pve1', 'vmid' => 102, 'name' => 'lxc-net-test',
            'provider_metadata' => ['guest_type' => 'lxc'],
            'cpu_cores' => 2, 'ram_gb' => 4, 'disk_gb' => 40, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ])->load('proxmoxServer');
        $proxmox = $this->mock(ProxmoxService::class);
        $proxmox->shouldReceive('lxcConfig')->twice()->andReturn(
            ['net0' => 'name=eth0,bridge=vmbr0'],
            ['net0' => 'name=eth0,bridge=vmbr0,link_down=1'],
        );
        $proxmox->shouldReceive('setLxcNetworkLinkState')->once()->with(
            Mockery::type(ProxmoxServer::class), 'pve1', 102, false, 'net0',
        )->andReturn(['task_id' => null]);
        $proxmox->shouldNotReceive('vmConfig');

        app(WalletNetworkRestrictionService::class)->freeze($vm, ['resume_on_funding' => true]);

        $this->assertNotNull(data_get($vm->fresh()->wallet_restriction, 'network_frozen_at'));
    }
}
