<?php

namespace Tests\Feature;

use App\Models\CloudImage;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VmBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudImageBundleWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    public function test_admin_cloud_image_form_loads_with_bundle_whitelist_section(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->get($this->adminBaseUrl.'/cloud-images/create')
            ->assertOk()
            ->assertSee('Whitelist پلن‌ها');
    }

    public function test_admin_can_assign_allowed_bundles_to_cloud_image(): void
    {
        $admin = User::factory()->create();
        $server = $this->server();
        $allowed = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
        ]);
        $ignored = VmBundle::create([
            'name' => 'Business',
            'slug' => 'business',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 1490000,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        $response = $this->post($this->adminBaseUrl.'/cloud-images', [
            'proxmox_server_id' => $server->id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404',
            'description' => 'Ubuntu image',
            'os_family' => 'ubuntu',
            'os_version' => '24.04 LTS',
            'logo_key' => 'ubuntu',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'storage' => 'local-lvm',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => 1,
            'min_cpu_cores' => 2,
            'min_ram_gb' => 4,
            'min_disk_gb' => 40,
            'sort_order' => 10,
            'is_active' => 1,
            'bundle_ids' => [$allowed->id],
        ]);

        $response->assertRedirect($this->adminBaseUrl.'/cloud-images');

        $imageId = CloudImage::query()->value('id');

        $this->assertDatabaseHas('cloud_image_vm_bundle', [
            'cloud_image_id' => $imageId,
            'vm_bundle_id' => $allowed->id,
        ]);
        $this->assertDatabaseMissing('cloud_image_vm_bundle', [
            'cloud_image_id' => $imageId,
            'vm_bundle_id' => $ignored->id,
        ]);
    }

    public function test_admin_cloud_image_index_has_delete_option(): void
    {
        $admin = User::factory()->create();
        $image = $this->image();

        $this->actingAs($admin, 'admin');
        $this->get($this->adminBaseUrl.'/cloud-images')
            ->assertOk()
            ->assertSee($this->adminBaseUrl.'/cloud-images/'.$image->id, false)
            ->assertSee('حذف');
    }

    public function test_admin_can_delete_cloud_image(): void
    {
        $admin = User::factory()->create();
        $image = $this->image();

        $this->actingAs($admin, 'admin');
        $this->delete($this->adminBaseUrl.'/cloud-images/'.$image->id)
            ->assertRedirect($this->adminBaseUrl.'/cloud-images')
            ->assertSessionHas('status', 'Cloud image deleted.');

        $this->assertDatabaseMissing('cloud_images', [
            'id' => $image->id,
        ]);
    }

    private function image(): CloudImage
    {
        return CloudImage::create([
            'proxmox_server_id' => $this->server()->id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404',
            'os_family' => 'ubuntu',
            'os_version' => '24.04 LTS',
            'logo_key' => 'ubuntu',
            'node' => 'pve1',
            'template_vmid' => 9000,
            'default_username' => 'ubuntu',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => true,
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => true,
        ]);
    }

    private function server(): ProxmoxServer
    {
        return ProxmoxServer::create([
            'name' => 'THR Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
    }
}
