<?php

namespace App\Services;

use App\Jobs\ProcessVmOperation;
use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\VirtualMachine;
use App\Models\VmOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VmOperationService
{
    public function request(VirtualMachine $vm, string $action, string $actorType, ?int $actorId, ?int $generation = null): VmOperation
    {
        $operation = DB::transaction(function () use ($vm, $action, $actorType, $actorId, $generation) {
            $vm = VirtualMachine::whereKey($vm->id)->lockForUpdate()->firstOrFail();
            if ($vm->isActionLocked() || $vm->pendingUpgradeOrders()->exists()) {
                throw ValidationException::withMessages(['server' => 'یک درخواست دیگر برای این سرور در حال انجام است. نتیجه را در همین صفحه دنبال کنید.']);
            }
            if ($generation !== null && $generation !== (int) data_get($vm->desired_state, 'power_generation', 0)) {
                throw ValidationException::withMessages(['server' => 'وضعیت سرور تغییر کرده است. صفحه را تازه‌سازی کنید.']);
            }
            $parent = $action === 'force-stop' ? $vm->operations()->whereIn('action', ['restart', 'shutdown'])->where('status', 'failed')->latest('id')->value('id') : null;

            return $vm->operations()->create([
                'active_vm_id' => $vm->id, 'action' => $action, 'actor_type' => $actorType,
                'actor_id' => $actorId, 'parent_id' => $parent,
                'details' => ['node' => $vm->node, 'vmid' => $vm->vmid, 'source' => $actorType.'_'.($action === 'shutdown' ? 'stop' : $action)],
            ]);
        });
        // The scheduler also picks up queued records if dispatch is interrupted.
        try {
            ProcessVmOperation::dispatch($operation->id)->onQueue(ProvisionCloudVirtualMachine::QUEUE);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $operation;
    }

    public function finish(VmOperation $operation, bool $success): void
    {
        DB::transaction(function () use ($operation, $success) {
            $vm = VirtualMachine::whereKey($operation->virtual_machine_id)->lockForUpdate()->firstOrFail();
            $operation = VmOperation::whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if (! $operation->active_vm_id) {
                return;
            }
            if ($success) {
                $running = in_array($operation->action, ['start', 'restart'], true);
                app(UsageBillingService::class)->accrueVm($vm);
                $vm->refresh();
                $vm->forceFill([
                    'status' => $running ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                    $running ? 'last_started_at' : 'last_stopped_at' => now(),
                    'last_billed_at' => now(), 'unbilled_amount' => 0,
                    'desired_state' => array_merge($vm->desired_state ?? [], [
                        'status' => $running ? 'running' : 'stopped',
                        'power_generation' => (int) data_get($vm->desired_state, 'power_generation', 0) + 1,
                        'power_intent_at' => now()->toISOString(),
                        'power_intent_source' => $operation->details['source'],
                    ]),
                ])->save();
            }
            $operation->update(['status' => $success ? 'succeeded' : 'failed', 'active_vm_id' => null, 'finished_at' => now()]);
            app(VmActivityRecorder::class)->record($vm, 'power', $success ? 'succeeded' : 'failed', $operation->label().' · '.$operation->customerStatus(), metadata: ['operation_id' => $operation->id]);
        });
    }
}
