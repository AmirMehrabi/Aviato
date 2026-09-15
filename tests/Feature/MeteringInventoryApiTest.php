<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MeteringInventoryAssignment;
use App\Models\VirtualMachine;
use App\Services\MeteringInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeteringInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipdr_can_bootstrap_and_follow_canonical_vm_assignments(): void
    {
        config(['services.ipdr.inventory_token' => 'inventory-secret']);
        $customer = Customer::factory()->create();
        $vm = VirtualMachine::query()->create([
            'customer_id' => $customer->id,
            'name' => 'metered-vm',
            'provider' => 'proxmox',
            'proxmox_server_id' => null,
            'vmid' => 101,
            'node' => 'pve1',
            'ip_address' => '192.0.2.10',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->getJson('/api/internal/v1/metering/inventory/snapshot')->assertUnauthorized();
        $snapshot = $this->withToken('inventory-secret')->getJson('/api/internal/v1/metering/inventory/snapshot')->assertOk();
        $snapshot->assertJsonPath('items.0.vm_uuid', $vm->uuid)->assertJsonPath('items.0.ip_address', '192.0.2.10');
        $assignmentId = $snapshot->json('items.0.assignment_id');

        $vm->update(['ip_address' => '192.0.2.11']);
        app(MeteringInventoryService::class)->syncVm($vm->fresh());

        $this->assertDatabaseHas('metering_inventory_assignments', ['assignment_id' => $assignmentId, 'active' => false]);
        $this->assertSame(2, MeteringInventoryAssignment::query()->count());
        $this->withToken('inventory-secret')->getJson('/api/internal/v1/metering/inventory?cursor=0')
            ->assertOk()->assertJsonStructure(['items' => [['event_id', 'action', 'assignment_id', 'vm_uuid']], 'next_cursor', 'has_more']);
    }
}
