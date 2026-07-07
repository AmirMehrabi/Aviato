<?php

namespace Tests\Feature;

use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\ProxmoxSerialConsoleService;
use App\Services\RouterOsPostInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class RouterOsPostInstallationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_interpolates_network_variables_and_runs_routeros_commands_in_order(): void
    {
        $console = $this->mock(ProxmoxSerialConsoleService::class);

        $console->shouldReceive('executeBatch')->once()->with(
            \Mockery::type(ProxmoxServer::class),
            'pve1',
            901,
            [
                'ip address add address=5.202.19.121/27 interface=ether2',
                'user set password=123 numbers=admin',
                'ip route add gateway=5.202.19.97',
            ]
        )->andReturn(['output' => '', 'commands_executed' => 3]);

        $server = ProxmoxServer::create([
            'name' => 'Test Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.test',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!test',
            'api_token_secret' => 'secret',
            'is_active' => true,
        ]);
        $image = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'name' => 'RouterOS',
            'slug' => 'routeros',
            'os_family' => 'router_os',
            'os_version' => '7',
            'logo_key' => 'router_os',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'admin',
            'post_installation_script' => <<<'SCRIPT'
ip address add address={{ip_address_with_prefix}} interface=ether2
user set password=123 numbers=admin
ip route add gateway=${gateway}
SCRIPT,
            'disk_device' => 'scsi0',
            'storage' => 'local-lvm',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => false,
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 1,
            'is_active' => true,
        ]);
        $pool = IpPool::create([
            'proxmox_server_id' => $server->id,
            'name' => 'Public',
            'gateway' => '5.202.19.97',
            'prefix_length' => 27,
            'start_ip' => '5.202.19.121',
            'end_ip' => '5.202.19.121',
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create();
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'cloud_image_id' => $image->id,
            'name' => 'router-1',
            'vmid' => 901,
            'node' => 'pve1',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 1,
        ]);
        $address = IpAddress::create([
            'ip_pool_id' => $pool->id,
            'virtual_machine_id' => $vm->id,
            'address' => '5.202.19.121',
            'status' => IpAddress::STATUS_RESERVED,
        ]);
        $vm->forceFill(['ip_address_id' => $address->id])->save();

        $result = app(RouterOsPostInstallationService::class)->execute($vm);

        $this->assertSame(['commands_executed' => 3], $result);
    }
}
