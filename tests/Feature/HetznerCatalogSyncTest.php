<?php

namespace Tests\Feature;

use App\Models\CloudImage;
use App\Models\HetznerAccount;
use App\Models\HetznerImage;
use App\Models\ProxmoxServer;
use App\Services\HetznerCatalogSyncService;
use App\Services\HetznerCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HetznerCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_reuses_existing_hetzner_cloud_image_and_deletes_duplicate_rows(): void
    {
        $account = HetznerAccount::create([
            'name' => 'Hetzner Primary',
            'api_token' => 'secret-token',
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
        $server = ProxmoxServer::create([
            'name' => 'Template Node',
            'cluster_name' => 'cluster-1',
            'datacenter' => 'dc1',
            'environment' => 'production',
            'host' => 'pve1.example.test',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'password' => 'secret',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
            'tags' => [],
            'desired_state' => [],
            'remote_inventory' => [],
            'connection_status' => ProxmoxServer::CONNECTION_ONLINE,
            'sync_status' => ProxmoxServer::SYNC_SYNCED,
            'sync_error' => null,
            'synced_at' => now(),
            'last_seen_at' => now(),
            'last_status' => [],
        ]);

        $canonicalSlug = 'hetzner-'.$account->id.'-161547269';

        $canonical = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'provider' => 'hetzner',
            'name' => 'Old Debian',
            'slug' => $canonicalSlug,
            'description' => 'Old Debian image',
            'os_family' => 'debian',
            'os_version' => '10',
            'logo_key' => 'debian',
            'node' => 'hetzner',
            'template_vmid' => 0,
            'remote_image_id' => null,
            'default_username' => 'root',
            'storage' => null,
            'disk_device' => 'local',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => true,
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => false,
            'sort_order' => 100,
        ]);

        $duplicate = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'provider' => 'hetzner',
            'name' => 'Duplicate Debian',
            'slug' => 'hetzner-'.$account->id.'-duplicate',
            'description' => 'Duplicate image row',
            'os_family' => 'debian',
            'os_version' => '10',
            'logo_key' => 'debian',
            'node' => 'hetzner',
            'template_vmid' => 1,
            'remote_image_id' => '161547269',
            'default_username' => 'root',
            'storage' => null,
            'disk_device' => 'local',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => true,
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $hetznerImage = HetznerImage::create([
            'hetzner_account_id' => $account->id,
            'cloud_image_id' => $duplicate->id,
            'remote_id' => 161547269,
            'name' => 'Old Debian',
            'description' => 'Old Debian image',
            'type' => 'system',
            'architecture' => 'x86',
            'os_flavor' => 'debian',
            'os_version' => '10',
            'deprecated' => false,
            'is_active' => true,
            'raw' => [],
            'last_synced_at' => now()->subDay(),
        ]);

        $mock = Mockery::mock(HetznerCloudService::class);
        $mock->shouldReceive('locations')->once()->with($account)->andReturn([]);
        $mock->shouldReceive('images')->once()->with($account)->andReturn([
            [
                'id' => 161547269,
                'name' => 'debian-11',
                'description' => 'Debian 11',
                'type' => 'system',
                'architecture' => 'x86',
                'os_flavor' => 'debian',
                'os_version' => '11',
                'deprecated' => false,
            ],
        ]);
        $mock->shouldReceive('serverTypes')->once()->with($account)->andReturn([]);
        $this->app->instance(HetznerCloudService::class, $mock);

        app(HetznerCatalogSyncService::class)->sync($account);

        $this->assertDatabaseCount('cloud_images', 1);
        $this->assertDatabaseHas('cloud_images', [
            'id' => $canonical->id,
            'provider' => 'hetzner',
            'slug' => $canonicalSlug,
            'remote_image_id' => '161547269',
            'name' => 'Debian 11',
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('cloud_images', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('hetzner_images', [
            'id' => $hetznerImage->id,
            'cloud_image_id' => $canonical->id,
            'remote_id' => 161547269,
            'name' => 'debian-11',
        ]);

        $account->refresh();
        $this->assertSame(HetznerAccount::CONNECTION_ONLINE, $account->connection_status);
        $this->assertSame(HetznerAccount::SYNC_SYNCED, $account->sync_status);
        $this->assertNotNull($account->synced_at);
    }
}
