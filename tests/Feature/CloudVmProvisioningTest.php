<?php

namespace Tests\Feature;

use App\Jobs\ProvisionCloudVirtualMachine;
use App\Jobs\RebuildCloudVirtualMachine;
use App\Models\AppSetting;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudVmProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_queue_cloud_vps_and_reserve_ip(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();
        $image->update(['network_bridge' => 'vmbr0']);
        IpPool::query()->update(['network_bridge' => 'vmbr0']);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'login_username' => 'ubuntu',
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey customer@example.com',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertSame($customer->id, $vm->customer_id);
        $this->assertSame($image->id, $vm->cloud_image_id);
        $this->assertNotSame('customer-vps-101', $vm->name);
        $this->assertMatchesRegularExpression('/^vps-[a-z0-9-]+-starter-[a-z0-9]{6}$/', $vm->name);
        $this->assertSame($vm->name, $vm->hostname);
        $this->assertSame('192.168.10.50', $vm->ip_address);
        $this->assertSame('vmbr1', $vm->network_bridge);
        $this->assertNotNull($vm->login_password);
        $this->assertSame(VirtualMachine::PROVISION_PENDING, $vm->provisioning_status);

        $this->assertDatabaseHas('ip_addresses', [
            'address' => '192.168.10.50',
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_RESERVED,
        ]);

        Bus::assertDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_customer_create_ignores_tampered_name_and_hostname(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create(['name' => 'Ali Customer']);
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'user-controlled-name',
            'hostname' => 'user-controlled-hostname',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers')
            ->assertSessionHas('provisioning_password');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertStringStartsWith('vps-ali-customer-starter-', $vm->name);
        $this->assertSame($vm->name, $vm->hostname);
        $this->assertNotSame('user-controlled-name', $vm->name);
        $this->assertNotSame('user-controlled-hostname', $vm->hostname);
    }

    public function test_customer_create_requires_password_confirmation_when_password_is_entered(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'login_username' => 'ubuntu',
                'login_password' => 'secret-password',
                'login_password_confirmation' => 'different-password',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHasErrors('login_password');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_customer_create_rejects_invalid_ssh_public_key(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'login_username' => 'ubuntu',
                'ssh_public_key' => 'not-a-public-key',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHasErrors('ssh_public_key');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_create_uses_local_ip_pool_as_source_of_truth_when_reserving(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog('192.168.10.51');

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'customer-vps-102',
            'hostname' => 'customer-vps-102',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertSame('192.168.10.50', $vm->ip_address);
        $this->assertDatabaseHas('ip_addresses', [
            'address' => '192.168.10.50',
            'virtual_machine_id' => $vm->id,
            'status' => IpAddress::STATUS_RESERVED,
        ]);
    }

    public function test_customer_can_queue_cloud_vps_without_available_ip(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();
        $pool = IpPool::query()->firstOrFail();
        IpAddress::create([
            'ip_pool_id' => $pool->id,
            'address' => '192.168.10.50',
            'status' => IpAddress::STATUS_RESERVED,
        ]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'no-ip-vps',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertNull($vm->ip_address_id);
        $this->assertNull($vm->ip_address);
        $this->assertSame(0, $vm->ip_count);
        Bus::assertDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_generated_customer_vm_names_are_unique_for_same_customer_and_bundle(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create(['name' => 'Repeat Customer']);
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog('192.168.10.51');

        $this->actingAs($customer, 'customer');

        for ($i = 0; $i < 2; $i++) {
            $this->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'login_username' => 'ubuntu',
            ])->assertRedirect($this->customerBaseUrl.'/servers');
        }

        $names = VirtualMachine::query()->pluck('name')->all();
        $this->assertCount(2, $names);
        $this->assertCount(2, array_unique($names));
        $this->assertSame($names, VirtualMachine::query()->pluck('hostname')->all());
    }

    public function test_proxmox_cloud_init_encodes_sshkeys_for_api_payload(): void
    {
        [$image] = $this->catalog();
        $server = $image->proxmoxServer;
        $capturedPayload = null;

        Http::fake(function (HttpRequest $request) use (&$capturedPayload) {
            if (str_ends_with($request->url(), '/nodes/pve1/qemu/101/config')) {
                $capturedPayload = $request->data();

                return Http::response(['data' => null]);
            }

            return Http::response(['data' => null]);
        });

        $result = app(ProxmoxService::class)->configureCloudInit($server, [
            'node' => 'pve1',
            'vmid' => 101,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'login_username' => 'ubuntu',
            'login_password' => 'secret-password',
            'ssh_public_key' => "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey customer@example.com\n",
            'network_bridge' => '',
        ]);

        $this->assertSame('ssh-ed25519%20AAAAC3NzaC1lZDI1NTE5AAAAITestKey%20customer%40example.com', $capturedPayload['sshkeys'] ?? null);
        $this->assertArrayNotHasKey('sshkeys', $result['payload']);
        $this->assertArrayNotHasKey('cipassword', $result['payload']);
    }

    public function test_disabled_cloud_init_image_ignores_guest_access_fields(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $bundle] = $this->catalog();
        $image->update(['cloud_init_enabled' => false]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'plain-template-vps',
            'hostname' => 'should-not-apply',
            'login_username' => 'should-not-apply',
            'login_password' => 'should-not-apply',
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestKey customer@example.com',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();
        $this->assertNull($vm->hostname);
        $this->assertNull($vm->login_username);
        $this->assertNull($vm->login_password);
        $this->assertNull($vm->ssh_public_key);
    }

    public function test_provisioning_skips_cloud_init_payload_when_image_disables_it(): void
    {
        [$image, $bundle] = $this->catalog();
        $image->update(['cloud_init_enabled' => false]);
        $customer = Customer::factory()->create();
        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'template_vmid' => $image->template_vmid,
            'name' => 'plain-template-vps',
            'node' => $image->node,
            'storage' => $image->storage,
            'network_bridge' => $image->network_bridge,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 0,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_PENDING,
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('nextVmid')->once()->andReturn(['vmid' => 101]);
            $mock->shouldReceive('assignedGuestVmids')->once()->andReturn([]);
            $mock->shouldReceive('cloneCloudTemplate')->once()->andReturn(['task_id' => null]);
            $mock->shouldReceive('configureCloudInit')->once()->withArgs(function ($server, array $options): bool {
                return $options['login_username'] === null
                    && $options['login_password'] === null
                    && $options['ssh_public_key'] === null
                    && $options['ipconfig0'] === null
                    && $options['nameserver'] === null
                    && $options['cicustom'] === null
                    && $options['network_bridge'] === 'vmbr1';
            })->andReturn(['task_id' => null, 'payload' => []]);
            $mock->shouldReceive('resizeDisk')->once()->andReturn(['task_id' => null]);
            $mock->shouldReceive('regenerateCloudInit')->never();
            $mock->shouldReceive('startVm')->never();
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
        });

        (new ProvisionCloudVirtualMachine($vm->id, ['start_after_create' => false]))->handle(
            app(ProxmoxService::class),
            app(IpPoolService::class),
        );

        $this->assertSame(VirtualMachine::PROVISION_READY, $vm->refresh()->provisioning_status);
        $this->assertNull($vm->ip_address);
    }

    public function test_cloud_image_minimum_resources_are_enforced(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'name' => 'too-small',
            'login_username' => 'ubuntu',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
        ])->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_customer_below_wallet_threshold_cannot_queue_cloud_vps(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 4799999]);
        [$image, $bundle] = $this->catalog();
        $bundle->update(['monthly_price' => 9600000]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'blocked-vps',
            'hostname' => 'ubuntu-blocked-vps',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 0);
        $this->assertDatabaseCount('ip_addresses', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_enabled_vm_creation_charge_is_collected_from_wallet(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_PERCENTAGE, 10, 'float', 'billing');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 4800000]);
        [$image, $bundle] = $this->catalog();
        $bundle->update(['monthly_price' => 9600000]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'charged-vps',
            'hostname' => 'ubuntu-charged-vps',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $vm = VirtualMachine::query()->firstOrFail();

        $this->assertSame(3840000, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'charge',
            'amount' => -960000,
            'reference_type' => $vm->getMorphClass(),
            'reference_id' => $vm->id,
        ]);

        $transaction = $customer->walletTransactions()->firstOrFail();
        $this->assertSame('vm_creation_fee', $transaction->metadata['category']);
        $this->assertSame(10, $transaction->metadata['percentage']);

        Bus::assertDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_unverified_customer_cannot_create_more_than_configured_vm_slots(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 2, 'integer', 'customer');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog('192.168.10.52');

        $this->actingAs($customer, 'customer');

        foreach (['first-vps', 'second-vps'] as $name) {
            $this->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'name' => $name,
                'login_username' => 'ubuntu',
            ])->assertRedirect($this->customerBaseUrl.'/servers');
        }

        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'name' => 'third-vps',
                'login_username' => 'ubuntu',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 2);
    }

    public function test_unverified_customer_limit_zero_requires_national_code_before_first_vm(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 0, 'integer', 'customer');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'name' => 'blocked-before-verification',
                'login_username' => 'ubuntu',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error', 'برای ساخت VPS بیشتر، کد ملی‌تان را در پروفایل تایید کنید.');

        $this->get($this->customerBaseUrl.'/servers/create')
            ->assertOk()
            ->assertSee('برای ساخت VPS بیشتر، کد ملی‌تان را در پروفایل تایید کنید.', false)
            ->assertSee('تایید کد ملی در پروفایل', false);

        $this->get($this->customerBaseUrl.'/profile')
            ->assertOk()
            ->assertSee('نیازمند تایید کد ملی', false)
            ->assertSee('نیازمند تایید', false)
            ->assertDontSee('0 / بدون سقف');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_deleted_vm_keeps_unverified_quota_slot_during_cooldown(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 2, 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_DELETED_VM_COOLDOWN_DAYS, 30, 'integer', 'customer');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        foreach (['deleted-a', 'deleted-b'] as $name) {
            VirtualMachine::create([
                'customer_id' => $customer->id,
                'proxmox_server_id' => $image->proxmox_server_id,
                'vm_bundle_id' => $bundle->id,
                'cloud_image_id' => $image->id,
                'name' => $name,
                'node' => $image->node,
                'cpu_cores' => 2,
                'ram_gb' => 4,
                'disk_gb' => 40,
                'ip_count' => 1,
                'status' => VirtualMachine::STATUS_DELETED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'deleted_at' => now()->subDays(3),
            ]);
        }

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'name' => 'blocked-by-cooldown',
                'login_username' => 'ubuntu',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 2);
    }

    public function test_deleted_vm_no_longer_counts_after_cooldown(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 2, 'integer', 'customer');
        AppSetting::setValue(AppSetting::CUSTOMER_DELETED_VM_COOLDOWN_DAYS, 30, 'integer', 'customer');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'old-deleted',
            'node' => $image->node,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_DELETED,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'deleted_at' => now()->subDays(31),
        ]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'allowed-after-cooldown',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers');

        $this->assertDatabaseCount('virtual_machines', 2);
    }

    public function test_verified_customer_uses_configured_verified_vm_limit(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::CUSTOMER_VERIFIED_VM_LIMIT, 1, 'integer', 'customer');

        $customer = Customer::factory()->create([
            'national_code' => '0100000002',
            'national_code_hash' => hash('sha256', '0100000002'),
            'national_code_verified_at' => now(),
        ]);
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'existing-vps',
            'node' => $image->node,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')
            ->post($this->customerBaseUrl.'/servers', [
                'cloud_image_id' => $image->id,
                'vm_bundle_id' => $bundle->id,
                'name' => 'blocked-verified-vps',
                'login_username' => 'ubuntu',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('virtual_machines', 1);
    }

    public function test_customer_create_rejects_bundle_not_whitelisted_for_image(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000000]);
        [$image, $allowedBundle] = $this->catalog();
        $disallowedBundle = VmBundle::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 1490000,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/create')->post($this->customerBaseUrl.'/servers', [
            'cloud_image_id' => $image->id,
            'vm_bundle_id' => $disallowedBundle->id,
            'name' => 'blocked-by-whitelist',
            'login_username' => 'ubuntu',
        ])->assertRedirect($this->customerBaseUrl.'/servers/create')
            ->assertSessionHasErrors('vm_bundle_id');

        $this->assertDatabaseCount('virtual_machines', 0);
        Bus::assertNotDispatched(ProvisionCloudVirtualMachine::class);
    }

    public function test_create_page_exposes_os_family_and_version_options(): void
    {
        $customer = Customer::factory()->create();
        [$image, $bundle] = $this->catalog();

        $this->actingAs($customer, 'customer');
        $response = $this->get($this->customerBaseUrl.'/servers/create');

        $response->assertOk();
        $this->assertSame('ubuntu', $response->viewData('osFamilies')[0]['key']);
        $this->assertSame('Ubuntu', $response->viewData('osFamilies')[0]['label']);
        $this->assertTrue($response->viewData('cloudImages')->contains($image));
        $viewImage = $response->viewData('cloudImages')->firstWhere('id', $image->id);
        $this->assertSame([$bundle->id], $viewImage->allowedBundles->pluck('id')->all());
        $response->assertDontSee('quota.limit > 0', false);
        $response->assertDontSee('حساب هنوز با کد ملی تایید نشده است.');
        $response->assertDontSee('حساب با کد ملی تایید شده است.');
        $response->assertDontSee('هزینه اولیه ساخت');
        $response->assertDontSee('creation_charge_label');
        $response->assertDontSee('vmCreationChargeEnabled');
    }

    public function test_create_page_shows_profile_verification_message_when_unverified_customer_is_quota_blocked(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 1, 'integer', 'customer');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'existing-unverified-vps',
            'node' => $image->node,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/servers/create')
            ->assertOk()
            ->assertSee('برای ساخت VPS بیشتر، کد ملی‌تان را در پروفایل تایید کنید.', false)
            ->assertSee('تایید کد ملی در پروفایل', false)
            ->assertDontSee('quota.limit > 0', false)
            ->assertDontSee('حساب هنوز با کد ملی تایید نشده است.')
            ->assertDontSee('هزینه اولیه ساخت');
    }

    public function test_create_page_shows_limited_availability_message_when_verified_customer_is_quota_blocked(): void
    {
        AppSetting::setValue(AppSetting::CUSTOMER_VERIFIED_VM_LIMIT, 1, 'integer', 'customer');

        $customer = Customer::factory()->create([
            'national_code' => '0100000002',
            'national_code_hash' => hash('sha256', '0100000002'),
            'national_code_verified_at' => now(),
        ]);
        $customer->wallet()->update(['balance' => 10000000]);
        [$image, $bundle] = $this->catalog();

        VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'existing-verified-vps',
            'node' => $image->node,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/servers/create')
            ->assertOk()
            ->assertSee('در حال حاضر ظرفیت ساخت VPS برای این حساب محدود است و امکان ساخت ماشین جدید وجود ندارد.', false)
            ->assertDontSee('تایید کد ملی در پروفایل')
            ->assertDontSee('quota.limit > 0', false)
            ->assertDontSee('حساب با کد ملی تایید شده است.')
            ->assertDontSee('هزینه اولیه ساخت');
    }

    public function test_create_page_shows_wallet_top_up_message_only_as_a_blocker(): void
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 1000]);
        [, $bundle] = $this->catalog();
        $bundle->update(['monthly_price' => 9600000]);

        $this->actingAs($customer, 'customer');
        $this->get($this->customerBaseUrl.'/servers/create')
            ->assertOk()
            ->assertSee('کیف پول کافی نیست', false)
            ->assertSee('برای ساخت این VPS موجودی کیف پول باید حداقل', false)
            ->assertSee('افزایش موجودی کیف پول', false)
            ->assertDontSee('هزینه اولیه ساخت');
    }

    public function test_customer_can_poll_owned_server_statuses(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        [$image, $bundle] = $this->catalog();

        $owned = VirtualMachine::create([
            'customer_id' => $owner->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'poll-owned',
            'hostname' => 'poll-owned',
            'node' => $image->node,
            'ip_address' => '192.168.10.50',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_PENDING,
        ]);

        $foreign = VirtualMachine::create([
            'customer_id' => $other->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'name' => 'poll-foreign',
            'hostname' => 'poll-foreign',
            'node' => $image->node,
            'ip_address' => '192.168.10.51',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($owner, 'customer');
        $this->getJson($this->customerBaseUrl.'/servers/statuses?ids[]='.$owned->uuid.'&ids[]='.$foreign->uuid)
            ->assertOk()
            ->assertJsonCount(1, 'servers')
            ->assertJsonPath('servers.0.id', $owned->uuid)
            ->assertJsonPath('servers.0.status_label', 'خاموش')
            ->assertJsonPath('servers.0.provisioning_pending', true);
    }

    public function test_customer_can_queue_vm_rebuild_with_strong_confirmation(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        [$image, $bundle] = $this->catalog();

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'vmid' => 108,
            'template_vmid' => $image->template_vmid,
            'name' => 'rebuild-me',
            'hostname' => 'old-host',
            'node' => $image->node,
            'storage' => $image->storage,
            'os_template' => $image->name,
            'network_bridge' => $image->network_bridge,
            'login_username' => 'ubuntu',
            'login_password' => 'old-password',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/rebuild', [
            'rebuild_confirmation' => 'rebuild-me',
            'hostname' => 'new-host',
            'login_username' => 'admin',
            'login_password' => 'new-password',
        ])->assertRedirect($this->customerBaseUrl.'/servers/'.$vm->uuid);

        $vm->refresh();
        $this->assertSame(VirtualMachine::PROVISION_PENDING, $vm->provisioning_status);
        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertSame('new-host', $vm->hostname);
        $this->assertSame('admin', $vm->login_username);
        $this->assertSame('new-password', $vm->login_password);
        $this->assertNotNull(data_get($vm->remote_state, 'rebuild_started_at'));

        Bus::assertDispatched(RebuildCloudVirtualMachine::class);
    }

    public function test_customer_rebuild_charges_configured_rebuild_fee(): void
    {
        Bus::fake();

        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_ENABLED, true, 'boolean', 'billing');
        AppSetting::setValue(AppSetting::VM_CREATION_CHARGE_PERCENTAGE, 10, 'float', 'billing');
        AppSetting::setValue(AppSetting::VM_REBUILD_FEE_MULTIPLIER_PERCENTAGE, 50, 'float', 'billing');

        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 500000]);
        [$image, $bundle] = $this->catalog();
        $bundle->update(['monthly_price' => 1000000]);

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'vmid' => 108,
            'template_vmid' => $image->template_vmid,
            'name' => 'rebuild-fee',
            'hostname' => 'rebuild-fee',
            'node' => $image->node,
            'storage' => $image->storage,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ]);

        $this->actingAs($customer, 'customer');
        $this->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/rebuild', [
            'rebuild_confirmation' => 'rebuild-fee',
        ])->assertRedirect($this->customerBaseUrl.'/servers/'.$vm->uuid);

        $this->assertSame(450000, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'customer_id' => $customer->id,
            'type' => 'charge',
            'amount' => -50000,
            'reference_type' => $vm->getMorphClass(),
            'reference_id' => $vm->id,
        ]);
        $this->assertSame('vm_rebuild_fee', $customer->walletTransactions()->firstOrFail()->metadata['category']);
        Bus::assertDispatched(RebuildCloudVirtualMachine::class);
    }

    public function test_customer_rebuild_requires_exact_server_name_confirmation(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create();
        [$image, $bundle] = $this->catalog();

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'vmid' => 108,
            'template_vmid' => $image->template_vmid,
            'name' => 'needs-confirmation',
            'hostname' => 'needs-confirmation',
            'node' => $image->node,
            'storage' => $image->storage,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer');
        $this->from($this->customerBaseUrl.'/servers/'.$vm->uuid)
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/rebuild', [
                'rebuild_confirmation' => 'wrong-name',
            ])
            ->assertRedirect($this->customerBaseUrl.'/servers/'.$vm->uuid)
            ->assertSessionHasErrors('rebuild_confirmation');

        $this->assertSame(VirtualMachine::PROVISION_READY, $vm->refresh()->provisioning_status);
        Bus::assertNotDispatched(RebuildCloudVirtualMachine::class);
    }

    public function test_rebuild_job_refuses_to_delete_reused_remote_vmid(): void
    {
        [$image, $bundle] = $this->catalog();
        $customer = Customer::factory()->create();

        $vm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $image->proxmox_server_id,
            'vm_bundle_id' => $bundle->id,
            'cloud_image_id' => $image->id,
            'vmid' => 108,
            'template_vmid' => $image->template_vmid,
            'name' => 'panel-owned-vm',
            'hostname' => 'panel-owned-vm',
            'node' => $image->node,
            'storage' => $image->storage,
            'login_username' => 'ubuntu',
            'login_password' => 'secret-password',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_PENDING,
            'remote_state' => ['rebuild_started_at' => now()->toISOString()],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfigOrNull')->once()->andReturn(['name' => 'someone-elses-vm']);
            $mock->shouldReceive('deleteVm')->never();
            $mock->shouldReceive('cloneCloudTemplate')->never();
        });

        (new RebuildCloudVirtualMachine($vm->id))->handle(
            app(ProxmoxService::class),
            app(IpPoolService::class),
        );

        $vm->refresh();
        $this->assertSame(VirtualMachine::PROVISION_FAILED, $vm->provisioning_status);
        $this->assertStringContainsString('does not belong', data_get($vm->remote_state, 'rebuild_error'));
    }

    /**
     * @return array{CloudImage, VmBundle}
     */
    private function catalog(string $poolEnd = '192.168.10.50'): array
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

        $image = CloudImage::create([
            'proxmox_server_id' => $server->id,
            'name' => 'Ubuntu 24.04',
            'slug' => 'ubuntu-2404',
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
            'min_cpu_cores' => 2,
            'min_ram_gb' => 4,
            'min_disk_gb' => 40,
            'is_active' => true,
        ]);

        IpPool::create([
            'proxmox_server_id' => $server->id,
            'name' => 'THR public',
            'node' => 'pve1',
            'network_bridge' => 'vmbr1',
            'gateway' => '192.168.10.1',
            'prefix_length' => 24,
            'nameservers' => '1.1.1.1',
            'start_ip' => '192.168.10.50',
            'end_ip' => $poolEnd,
            'is_active' => true,
        ]);

        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
        ]);

        $image->allowedBundles()->sync([$bundle->id]);

        return [$image, $bundle];
    }
}
