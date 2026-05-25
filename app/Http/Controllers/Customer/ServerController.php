<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CloudImage;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\BillingService;
use App\Services\CloudVmProvisioningService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    private const MINIMUM_CREATE_BALANCE = 1000000;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly BillingService $billing,
        private readonly UsageBillingService $usageBilling,
        private readonly CloudVmProvisioningService $cloudProvisioning,
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
            ->with(['bundle', 'proxmoxServer', 'cloudImage'])
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
        $cloudImages = CloudImage::query()
            ->with('proxmoxServer')
            ->where('is_active', true)
            ->orderBy('os_family')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('customer.servers.create', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'minimumCreateBalance' => self::MINIMUM_CREATE_BALANCE,
            'canCreateVps' => $wallet->balance >= self::MINIMUM_CREATE_BALANCE,
            'bundles' => VmBundle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get(),
            'cloudImages' => $cloudImages,
            'osFamilies' => $this->osFamilies($cloudImages),
            'invoiceCount' => $customer->invoices()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);

        if ($wallet->balance < self::MINIMUM_CREATE_BALANCE) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'برای ساخت VPS حداقل موجودی کیف پول باید ۱,۰۰۰,۰۰۰ ریال باشد.');
        }

        $data = $request->validate([
            'cloud_image_id' => ['required', 'integer', 'exists:cloud_images,id,is_active,1'],
            'vm_bundle_id' => ['nullable', 'integer', 'exists:vm_bundles,id'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:64'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'ssh_public_key' => ['nullable', 'string', 'max:5000'],
            'cpu_cores' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_without:vm_bundle_id', 'integer', 'min:1', 'max:1048576'],
        ]);

        $data['start_after_create'] = true;
        $data['onboot'] = false;

        try {
            $result = $this->cloudProvisioning->create($customer, $data);

            return redirect()
                ->route('customer.servers.index')
                ->with('status', 'درخواست ساخت VPS ثبت شد. IP: '.$result['vm']->ip_address)
                ->with('provisioning_password', $result['password']);
        } catch (\Throwable $exception) {
            return back()
                ->withInput($request->except('login_password'))
                ->with('error', 'ساخت VPS ممکن نیست: '.$exception->getMessage());
        }
    }

    private function osFamilies($cloudImages): array
    {
        $labels = [
            'ubuntu' => 'Ubuntu',
            'debian' => 'Debian',
            'rocky' => 'Rocky Linux',
            'windows' => 'Windows Server',
        ];

        return $cloudImages
            ->groupBy('os_family')
            ->map(fn ($images, string $family): array => [
                'key' => $family,
                'label' => $labels[$family] ?? str($family)->headline()->toString(),
                'logo_key' => $images->first()->logo_key ?: $family,
                'count' => $images->count(),
            ])
            ->values()
            ->all();
    }
}
