<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackupPolicy;
use App\Services\ProjectAccessService;
use App\Services\ProxmoxService;
use App\Services\VmBackupService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly VmBackupService $backups,
        private readonly ProxmoxService $proxmox,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewVms($activeProject, $customer), 404);
        $wallet = $this->wallets->walletFor($customer);
        $vms = $activeProject->virtualMachines()
            ->notDeleted()
            ->with(['proxmoxServer', 'backupPolicy', 'backups' => fn ($query) => $query->latest()->limit(10)])
            ->latest()
            ->get();
        $backupRate = ResourceRate::query()->where('resource', ResourceRate::BACKUP)->where('is_active', true)->first();

        $storageOptions = [];
        foreach ($vms as $vm) {
            if (! $vm->proxmoxServer || ! $vm->node) {
                continue;
            }

            try {
                $storageOptions[$vm->id] = $this->proxmox->backupStorages($vm->proxmoxServer, $vm->node);
            } catch (\Throwable) {
                $storageOptions[$vm->id] = [];
            }
        }

        return view('customer.backups.index', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'vms' => $vms,
            'storageOptions' => $storageOptions,
            'backupRate' => $backupRate,
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function storeManual(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);
        abort_if($virtualMachine->isActionLocked(), 404);

        try {
            $this->backups->queueManualBackup($virtualMachine);

            return back()->with('status', 'Backup queued.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Backup could not be queued: '.$exception->getMessage());
        }
    }

    public function updatePolicy(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $virtualMachine = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);
        abort_if($virtualMachine->isActionLocked(), 404);

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'frequency' => ['required', Rule::in([VmBackupPolicy::FREQUENCY_DAILY, VmBackupPolicy::FREQUENCY_WEEKLY])],
            'preferred_time' => ['required', 'date_format:H:i'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:30'],
            'backup_storage' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_enabled'] = $request->boolean('is_enabled');

        $this->backups->updatePolicy($virtualMachine, $data);

        return back()->with('status', 'Backup policy updated.');
    }
}
