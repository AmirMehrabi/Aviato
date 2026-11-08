<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\VmHardwareService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ObserveVmHardware implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $vmId) {}

    public function uniqueId(): string
    {
        return (string) $this->vmId;
    }

    public function handle(VmHardwareService $hardware): void
    {
        $vm = VirtualMachine::query()->notDeleted()->find($this->vmId);
        if (! $vm || $vm->isActionLocked() || $vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            return;
        }
        // An observation only records reality; it never accepts resources or changes a price.
        $hardware->observe($vm);
    }
}
