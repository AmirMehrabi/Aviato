<?php

namespace Tests\Feature;

use App\Models\CloudImage;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Services\ProxmoxPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxmoxPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_selects_an_eligible_node_below_cluster_thresholds(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/nodes') => Http::response(['data' => [
                    ['node' => 'srv1', 'status' => 'online', 'cpu' => 0.90, 'maxcpu' => 16, 'mem' => 8_000_000_000, 'maxmem' => 32_000_000_000],
                    ['node' => 'srv2', 'status' => 'online', 'cpu' => 0.10, 'maxcpu' => 16, 'mem' => 4_000_000_000, 'maxmem' => 32_000_000_000],
                ]]),
                str_ends_with($path, '/nodes/srv1/qemu') => Http::response(['data' => [['vmid' => 9000, 'name' => 'ubuntu', 'template' => 1]]]),
                str_ends_with($path, '/nodes/srv2/qemu') => Http::response(['data' => [['vmid' => 9100, 'name' => 'ubuntu', 'template' => 1]]]),
                str_ends_with($path, '/nodes/srv1/storage'), str_ends_with($path, '/nodes/srv2/storage') => Http::response(['data' => [
                    ['storage' => 'local-lvm', 'content' => 'images', 'active' => 1, 'used' => 100_000_000_000, 'avail' => 900_000_000_000, 'total' => 1_000_000_000_000],
                ]]),
                str_ends_with($path, '/nodes/srv1/network'), str_ends_with($path, '/nodes/srv2/network') => Http::response(['data' => [
                    ['iface' => 'vmbr1', 'type' => 'bridge', 'active' => 1],
                ]]),
                default => Http::response(['data' => []]),
            };
        });

        $server = ProxmoxServer::create([
            'name' => 'Main cluster',
            'host' => 'srv1.local',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'is_active' => true,
            'cpu_threshold_percent' => 80,
            'ram_threshold_percent' => 85,
            'disk_threshold_percent' => 80,
        ]);
        $image = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'provider' => 'proxmox',
            'name' => 'Ubuntu',
            'slug' => 'ubuntu-placement',
            'os_family' => 'ubuntu',
            'os_version' => '24.04',
            'logo_key' => 'ubuntu',
            'node' => 'srv1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'storage' => 'local-lvm',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'is_active' => true,
        ]);
        $image->nodeMappings()->createMany([
            ['proxmox_server_id' => $server->id, 'node' => 'srv1', 'template_vmid' => 9000, 'storage' => 'local-lvm', 'network_bridge' => 'vmbr1', 'is_enabled' => true],
            ['proxmox_server_id' => $server->id, 'node' => 'srv2', 'template_vmid' => 9100, 'storage' => 'local-lvm', 'network_bridge' => 'vmbr1', 'is_enabled' => true],
        ]);

        foreach (['srv1', 'srv2'] as $index => $node) {
            IpPool::create([
                'proxmox_server_id' => $server->id,
                'name' => $node,
                'node' => $node,
                'network_bridge' => 'vmbr1',
                'gateway' => "192.168.{$index}.1",
                'prefix_length' => 24,
                'start_ip' => "192.168.{$index}.10",
                'end_ip' => "192.168.{$index}.10",
                'is_active' => true,
            ]);
        }

        $result = app(ProxmoxPlacementService::class)->select($image, [
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
        ]);

        $this->assertSame('srv2', $result['mapping']->node);
        $this->assertArrayHasKey('srv1', $result['snapshot']['rejected_nodes']);
        $this->assertStringContainsString('CPU threshold', implode(' ', $result['snapshot']['rejected_nodes']['srv1']));
    }
}
