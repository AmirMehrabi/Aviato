<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VirtualMachine;
use App\Notifications\VmNodeMoveNotification;
use App\Services\VirtualMachineNodeMoveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MoveVirtualMachinesToNodeJob implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'provisioning';

    /**
     * @param  array<int, int>  $vmIds
     */
    public function __construct(
        private readonly array $vmIds,
        private readonly string $targetNode,
        private readonly string $mode,
        private readonly bool $online,
        private readonly ?int $adminId,
    ) {}

    public function handle(VirtualMachineNodeMoveService $moves): void
    {
        $migrated = 0;
        $reconciled = 0;
        $skipped = [];

        $vms = VirtualMachine::query()
            ->whereIn('id', $this->vmIds)
            ->with(['proxmoxServer', 'cloudImageNodeMapping'])
            ->get();

        foreach ($vms as $vm) {
            try {
                $result = $moves->move($vm, $this->targetNode, $this->mode, $this->online, $this->adminId);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'reconciled') {
                    $reconciled++;
                }
            } catch (Throwable $exception) {
                $skipped[] = '#'.$vm->id.' '.$vm->name.': '.$exception->getMessage();
            }
        }

        if ($this->adminId && ($admin = User::query()->find($this->adminId))) {
            $admin->notify(new VmNodeMoveNotification($migrated, $reconciled, $skipped));
        }
    }
}
