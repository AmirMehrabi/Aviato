<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly BillingService $billing,
        private readonly UsageBillingService $usageBilling,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                VirtualMachine::STATUS_RUNNING,
                VirtualMachine::STATUS_STOPPED,
                VirtualMachine::STATUS_SUSPENDED,
            ])],
        ]);

        $servers = $customer->virtualMachines()
            ->with(['bundle', 'proxmoxServer'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('node', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $summarySource = $customer->virtualMachines()->with('bundle')->get();
        $pendingUsage = $summarySource->sum(fn (VirtualMachine $vm): int => $this->usageBilling->estimateVmUsage($vm)['amount']);

        return view('customer.servers.index', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'servers' => $servers,
            'filters' => $filters,
            'billing' => $this->billing,
            'summary' => [
                'total' => $summarySource->count(),
                'running' => $summarySource->where('status', VirtualMachine::STATUS_RUNNING)->count(),
                'stopped' => $summarySource->where('status', VirtualMachine::STATUS_STOPPED)->count(),
                'pending_usage' => $pendingUsage,
            ],
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);

        return view('customer.servers.create', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'bundles' => VmBundle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get(),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }
}
