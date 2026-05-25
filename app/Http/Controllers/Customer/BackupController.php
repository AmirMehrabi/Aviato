<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackupPolicy;
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
        private readonly VmBackupService $backups,
        private readonly ProxmoxService $proxmox,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $vms = $customer->virtualMachines()
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
        $this->authorizeCustomerVm($request, $virtualMachine);

        try {
            $this->backups->queueManualBackup($virtualMachine);

            return back()->with('status', 'Backup queued.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Backup could not be queued: '.$exception->getMessage());
        }
    }

    public function updatePolicy(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $this->authorizeCustomerVm($request, $virtualMachine);

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

    private function authorizeCustomerVm(Request $request, VirtualMachine $vm): void
    {
        abort_unless($vm->customer_id === $request->user('customer')->id, 404);
    }
}
