<?php

namespace Tests\Feature;

use App\Jobs\ProcessVmOperation;
use App\Models\Customer;
use App\Models\HetznerAccount;
use App\Models\InfrastructureLocation;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use App\Services\HetznerCloudService;
use App\Services\ProxmoxService;
use App\Services\UsageBillingService;
use App\Services\VmOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FundsCustomerWallet;
use Tests\TestCase;

class CustomerServerPowerActionsTest extends TestCase
{
    use FundsCustomerWallet, RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_can_start_stopped_server_after_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_STOPPED]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('startVm')->once()->andReturn(['task_id' => 'UPID:start']);

        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/start')
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_RUNNING, $vm->status);
        $this->assertSame('customer_start', data_get($vm->desired_state, 'power_intent_source'));
        $this->assertDatabaseHas('vm_activities', ['virtual_machine_id' => $vm->id, 'event' => 'power', 'outcome' => 'succeeded']);
    }

    public function test_suspended_customer_cannot_start_server(): void
    {
        $customer = Customer::factory()->create();
        $customer->suspend('test');
        $vm = $this->vm($customer, ['status' => VirtualMachine::STATUS_STOPPED]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('startVm');
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/start')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->fresh()->status);
    }

    public function test_customer_can_stop_running_server_after_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, [
            'desired_state' => ['status' => VirtualMachine::STATUS_RUNNING, 'power_generation' => 2],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldNotReceive('stopVm');
            $mock->shouldReceive('shutdownVm')->once()->withArgs(function ($server, $node, $vmid, $fallback, $context, $forceOnTimeout): bool {
                return ! $fallback && ! $forceOnTimeout;
            })->andReturn(['task_id' => 'UPID:shutdown']);

            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'stopped']);
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/stop', ['power_generation' => 2])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_STOPPED, $vm->status);
        $this->assertSame(3, data_get($vm->desired_state, 'power_generation'));
        $this->assertSame('customer_stop', data_get($vm->desired_state, 'power_intent_source'));
        $this->assertDatabaseHas('vm_activities', ['virtual_machine_id' => $vm->id, 'event' => 'power', 'outcome' => 'succeeded']);
    }

    public function test_customer_can_restart_running_server_after_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, [
            'desired_state' => ['status' => VirtualMachine::STATUS_RUNNING, 'power_generation' => 2],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
            $mock->shouldReceive('rebootVm')->once()->andReturn(['task_id' => 'UPID:reboot']);

        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/restart', ['power_generation' => 2])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vm->refresh();

        $this->assertSame(VirtualMachine::STATUS_RUNNING, $vm->status);
        $this->assertSame(3, data_get($vm->desired_state, 'power_generation'));
        $this->assertSame('customer_restart', data_get($vm->desired_state, 'power_intent_source'));
        $this->assertDatabaseHas('vm_activities', ['virtual_machine_id' => $vm->id, 'event' => 'power', 'outcome' => 'succeeded']);
    }

    public function test_stale_restart_request_does_not_reach_provider(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, [
            'desired_state' => ['status' => VirtualMachine::STATUS_RUNNING, 'power_generation' => 4],
        ]);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('rebootVm');
        });

        $this->actingAs($customer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/restart', ['power_generation' => 3])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(4, data_get($vm->fresh()->desired_state, 'power_generation'));
    }

    public function test_customer_cannot_restart_another_customers_server(): void
    {
        $owner = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $vm = $this->vm($owner);

        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('rebootVm');
        });

        $this->actingAs($otherCustomer, 'customer')
            ->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/restart', ['power_generation' => 0])
            ->assertNotFound();
    }

    public function test_power_request_is_persisted_and_duplicate_is_blocked_before_worker_runs(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $this->actingAs($customer, 'customer')->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/restart', ['power_generation' => 0])->assertRedirect();
        $this->assertDatabaseHas('vm_operations', ['virtual_machine_id' => $vm->id, 'action' => 'restart', 'status' => 'queued', 'actor_id' => $customer->id]);
        $this->actingAs($customer, 'customer')->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/stop', ['power_generation' => 0])->assertSessionHas('error');
        $this->assertSame(1, $vm->operations()->count());
        Queue::assertPushed(ProcessVmOperation::class, 1);
    }

    public function test_running_reboot_task_is_not_completed_just_because_vm_is_running(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'restart', 'customer', $customer->id, 0);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
            $mock->shouldReceive('rebootVm')->once()->andReturn(['task_id' => 'reboot-task']);
            $mock->shouldReceive('taskStatus')->twice()->andReturn(['status' => 'running']);
            $mock->shouldNotReceive('vmStatus');
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('running', $operation->fresh()->status);
        $this->assertTrue($vm->fresh()->isActionLocked());
        $this->assertSame('reboot-task', $operation->fresh()->task_id);
    }

    public function test_submission_timeout_stays_unknown_and_is_never_submitted_twice(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'restart', 'customer', $customer->id);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
            $mock->shouldReceive('rebootVm')->once()->andThrow(new \RuntimeException('Connection timed out'));
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('checking', $operation->fresh()->status);
        $this->assertNotNull($operation->fresh()->active_vm_id);
    }

    public function test_force_stop_requires_confirmation_and_is_linked_to_failed_restart(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $parent = $vm->operations()->create(['action' => 'restart', 'status' => 'failed', 'actor_type' => 'customer', 'actor_id' => $customer->id]);
        $url = $this->customerBaseUrl.'/servers/'.$vm->uuid.'/force-stop';
        $this->actingAs($customer, 'customer')->post($url, ['power_generation' => 0])->assertSessionHasErrors('confirm_force_stop');
        $this->actingAs($customer, 'customer')->post($url, ['power_generation' => 0, 'confirm_force_stop' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('vm_operations', ['action' => 'force-stop', 'parent_id' => $parent->id, 'status' => 'queued']);
    }

    public function test_confirmed_force_stop_uses_proxmox_stop_without_graceful_shutdown(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldNotReceive('shutdownVm');
            $mock->shouldReceive('stopVm')->once()->andReturn(['task_id' => 'stop-task']);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'stopped']);
        });
        $this->actingAs($customer, 'customer')->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/force-stop', [
            'power_generation' => 0, 'confirm_force_stop' => 1,
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('stopped', $vm->fresh()->status);
        $this->assertDatabaseHas('vm_operations', ['virtual_machine_id' => $vm->id, 'action' => 'force-stop', 'status' => 'succeeded']);
    }

    public function test_normal_shutdown_failure_does_not_automatically_force_stop(): void
    {
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
            $mock->shouldReceive('shutdownVm')->once()->withArgs(function ($server, $node, $vmid, $fallback, $context, $forceOnTimeout): bool {
                return ! $fallback && ! $forceOnTimeout;
            })->andReturn(['task_id' => 'shutdown-task']);
            $mock->shouldNotReceive('stopVm');
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'VM quit/powerdown failed - got timeout']);
        });
        $this->actingAs($customer, 'customer')->post($this->customerBaseUrl.'/servers/'.$vm->uuid.'/stop', [
            'power_generation' => 0,
        ])->assertRedirect();
        $this->assertSame('running', $vm->fresh()->status);
        $this->assertFalse($vm->fresh()->isActionLocked());
        $this->assertDatabaseHas('vm_operations', ['virtual_machine_id' => $vm->id, 'action' => 'shutdown', 'status' => 'failed']);
    }

    public function test_stuck_power_operation_does_not_suspend_usage_billing(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['last_billed_at' => now()->subHour()]);
        $before = app(UsageBillingService::class)->estimateVmUsage($vm);
        app(VmOperationService::class)->request($vm, 'restart', 'customer', $customer->id);
        $after = app(UsageBillingService::class)->estimateVmUsage($vm->fresh());
        $this->assertTrue($after['is_running']);
        $this->assertSame($before['hourly_rate'], $after['hourly_rate']);
        $this->assertEqualsWithDelta($before['hours'], $after['hours'], 0.01);
    }

    public function test_successful_task_with_wrong_power_state_remains_under_review(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'shutdown', 'customer', $customer->id);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('shutdownVm')->once()->withArgs(function ($server, $node, $vmid, $fallback, $context, $forceOnTimeout): bool {
                return ! $fallback && ! $forceOnTimeout;
            })->andReturn(['task_id' => 'shutdown-task']);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('checking', $operation->fresh()->status);
        $this->assertSame('running', $vm->fresh()->status);
    }

    public function test_start_warning_is_successful_only_after_running_state_is_verified(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => 'stopped']);
        $operation = app(VmOperationService::class)->request($vm, 'start', 'customer', $customer->id);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('startVm')->once()->andReturn(['task_id' => 'start-task']);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'WARNINGS: 1']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertNull($operation->fresh()->active_vm_id);
        $this->assertSame('running', $vm->fresh()->status);
    }

    public function test_failed_hetzner_task_releases_operation_without_claiming_success(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $account = HetznerAccount::create(['name' => 'cloud', 'api_token' => 'test-token', 'is_active' => true]);
        $location = InfrastructureLocation::create(['provider' => 'hetzner', 'name' => 'Germany', 'hetzner_account_id' => $account->id]);
        $vm = $this->vm($customer, ['provider' => 'hetzner', 'infrastructure_location_id' => $location->id, 'remote_id' => '123']);
        $operation = app(VmOperationService::class)->request($vm, 'shutdown', 'customer', $customer->id);
        $this->mock(HetznerCloudService::class, function ($mock): void {
            $mock->shouldReceive('shutdown')->once()->andReturn(['action' => ['id' => 456]]);
            $mock->shouldReceive('actionStatus')->once()->andReturn(['status' => 'error']);
            $mock->shouldNotReceive('server');
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertNull($operation->fresh()->active_vm_id);
        $this->assertSame('running', $vm->fresh()->status);
    }

    public function test_already_running_start_without_task_id_is_reconciled(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => 'stopped']);
        $operation = app(VmOperationService::class)->request($vm, 'start', 'customer', $customer->id);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('startVm')->once()->andReturn(['task_id' => null, 'already_running' => true]);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
            $mock->shouldNotReceive('taskStatus');
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertNull($operation->fresh()->active_vm_id);
        $this->assertSame('running', $vm->fresh()->status);
    }

    public function test_existing_unknown_start_is_reconciled_without_resubmitting(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => 'stopped']);
        $operation = app(VmOperationService::class)->request($vm, 'start', 'customer', $customer->id);
        $operation->update(['status' => 'checking', 'started_at' => now()->subMinutes(15)]);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('startVm');
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertFalse($vm->fresh()->isActionLocked());
    }

    public function test_unknown_start_with_provider_lock_remains_pending(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer, ['status' => 'stopped']);
        $operation = app(VmOperationService::class)->request($vm, 'start', 'customer', $customer->id);
        $operation->update(['status' => 'checking']);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('startVm');
            $mock->shouldReceive('vmConfig')->once()->andReturn(['lock' => 'backup']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'running']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('checking', $operation->fresh()->status);
        $this->assertTrue($vm->fresh()->isActionLocked());
    }

    public function test_unknown_force_stop_is_confirmed_from_stopped_state_without_resubmitting(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'force-stop', 'customer', $customer->id);
        $operation->update(['status' => 'checking']);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldNotReceive('stopVm');
            $mock->shouldReceive('vmConfig')->once()->andReturn([]);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'stopped']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertSame('stopped', $vm->fresh()->status);
    }

    public function test_preflight_failure_can_retry_before_power_request_is_submitted(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'force-stop', 'customer', $customer->id);
        $this->mock(ProxmoxService::class, function ($mock): void {
            $mock->shouldReceive('vmConfig')->once()->andThrow(new \RuntimeException('Connection timed out'));
            $mock->shouldReceive('vmConfig')->twice()->andReturn([]);
            $mock->shouldReceive('stopVm')->once()->andReturn(['task_id' => 'stop-task']);
            $mock->shouldReceive('taskStatus')->once()->andReturn(['status' => 'stopped', 'exitstatus' => 'OK']);
            $mock->shouldReceive('vmStatus')->once()->andReturn(['status' => 'stopped']);
        });
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('queued', $operation->fresh()->status);
        app()->call([new ProcessVmOperation($operation->id), 'handle']);
        $this->assertSame('succeeded', $operation->fresh()->status);
    }

    public function test_status_poll_exposes_operation_completion_even_when_power_state_is_unchanged(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $vm = $this->vm($customer);
        $operation = app(VmOperationService::class)->request($vm, 'restart', 'customer', $customer->id);
        $url = $this->customerBaseUrl.'/servers/statuses?ids[]='.$vm->uuid;
        $this->actingAs($customer, 'customer')->getJson($url)
            ->assertOk()
            ->assertJsonPath('servers.0.status', 'running')
            ->assertJsonPath('servers.0.operation_id', $operation->id)
            ->assertJsonPath('servers.0.action_pending', true);
        app(VmOperationService::class)->finish($operation, false);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('servers.0.status', 'running')
            ->assertJsonPath('servers.0.operation_id', null)
            ->assertJsonPath('servers.0.action_pending', false);
    }

    /** @param array<string, mixed> $overrides */
    private function vm(Customer $customer, array $overrides = []): VirtualMachine
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
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return VirtualMachine::create(array_merge([
            'customer_id' => $customer->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 101,
            'name' => 'customer-vps-101',
            'hostname' => 'customer-vps-101',
            'node' => 'pve1',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ], $overrides));
    }
}
