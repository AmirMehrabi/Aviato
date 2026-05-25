<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Services\ProxmoxService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProxmoxService $proxmox,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $servers = $customer->virtualMachines()
            ->with([
                'proxmoxServer',
                'backupPolicy',
                'backups' => fn ($query) => $query->latest()->limit(10),
            ])
            ->latest()
            ->get();

        $selected = $servers->firstWhere('id', (int) $request->query('server'))
            ?? $servers->firstWhere('status', VirtualMachine::STATUS_RUNNING)
            ?? $servers->first();

        return view('customer.monitoring.index', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'servers' => $servers,
            'selected' => $selected,
            'serverOptions' => $servers->map(fn (VirtualMachine $vm): array => [
                'id' => $vm->id,
                'name' => $vm->name,
                'hostname' => $vm->hostname,
                'ip_address' => $vm->ip_address,
                'status' => $vm->status,
                'provisioning_status' => $vm->provisioning_status,
                'node' => $vm->node,
                'vmid' => $vm->vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'disk_gb' => $vm->disk_gb,
                'metrics_url' => route('customer.monitoring.metrics', $vm, false),
                'backup_health' => $this->backupHealth($vm),
            ])->values()->all(),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function metrics(Request $request, VirtualMachine $virtualMachine): JsonResponse
    {
        $this->authorizeCustomerVm($request, $virtualMachine);

        $data = $request->validate([
            'timeframe' => ['nullable', Rule::in(['hour', 'day', 'week', 'month', 'year'])],
        ]);
        $timeframe = $data['timeframe'] ?? 'hour';

        try {
            if (! $virtualMachine->proxmoxServer || ! $virtualMachine->node || ! $virtualMachine->vmid) {
                throw new RuntimeException('This VM is not connected to Proxmox monitoring yet.');
            }

            $metrics = $this->proxmox->qemuPerformance(
                $virtualMachine->proxmoxServer,
                $virtualMachine->node,
                (int) $virtualMachine->vmid,
                $timeframe,
            );

            return response()->json([
                'data' => array_merge($metrics, [
                    'server' => [
                        'id' => $virtualMachine->id,
                        'name' => $virtualMachine->name,
                        'hostname' => $virtualMachine->hostname,
                        'ip_address' => $virtualMachine->ip_address,
                        'status' => $virtualMachine->status,
                        'provisioning_status' => $virtualMachine->provisioning_status,
                        'node' => $virtualMachine->node,
                        'vmid' => $virtualMachine->vmid,
                        'cpu_cores' => $virtualMachine->cpu_cores,
                        'ram_gb' => $virtualMachine->ram_gb,
                        'disk_gb' => $virtualMachine->disk_gb,
                    ],
                    'backup_health' => $this->backupHealth($virtualMachine->loadMissing(['backupPolicy', 'backups'])),
                ]),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to load VPS monitoring data.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    private function authorizeCustomerVm(Request $request, VirtualMachine $vm): void
    {
        abort_unless($vm->customer_id === $request->user('customer')->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function backupHealth(VirtualMachine $vm): array
    {
        $policy = $vm->backupPolicy;
        $backups = $vm->relationLoaded('backups')
            ? $vm->backups
            : $vm->backups()->latest()->limit(10)->get();
        $lastBackup = $backups->first();
        $lastReady = $backups->firstWhere('status', VmBackup::STATUS_READY);
        $lastFailed = $backups->firstWhere('status', VmBackup::STATUS_FAILED);

        return [
            'policy_enabled' => (bool) $policy?->is_enabled,
            'frequency' => $policy?->frequency,
            'retention_count' => $policy?->retention_count,
            'next_run_at' => $policy?->next_run_at?->toISOString(),
            'ready_count' => $backups->where('status', VmBackup::STATUS_READY)->count(),
            'last_status' => $lastBackup?->status,
            'last_at' => $lastBackup?->created_at?->toISOString(),
            'last_ready_at' => $lastReady?->finished_at?->toISOString(),
            'last_failed_at' => $lastFailed?->created_at?->toISOString(),
            'last_error' => $lastFailed?->error,
        ];
    }
}
