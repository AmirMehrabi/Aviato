<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\BillingService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,suspended'],
            'verification' => ['nullable', 'in:verified,unverified'],
            'sort' => ['nullable', 'in:latest,oldest,name'],
        ]);

        $customers = Customer::query()
            ->with('wallet')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['verification'] ?? null) === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when(($filters['verification'] ?? null) === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->when(($filters['sort'] ?? 'latest') === 'oldest', fn ($query) => $query->oldest())
            ->when(($filters['sort'] ?? 'latest') === 'name', fn ($query) => $query->orderBy('name'))
            ->when(! in_array($filters['sort'] ?? 'latest', ['oldest', 'name'], true), fn ($query) => $query->latest())
            ->paginate(12)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'stats' => [
                'total' => Customer::count(),
                'active' => Customer::where('status', Customer::STATUS_ACTIVE)->count(),
                'suspended' => Customer::where('status', Customer::STATUS_SUSPENDED)->count(),
                'verified' => Customer::whereNotNull('email_verified_at')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.create', [
            'customer' => new Customer(['status' => Customer::STATUS_ACTIVE, 'sms_notifications_enabled' => true]),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($this->normalizedInput($request->validated()));

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'مشتری با موفقیت ایجاد شد.');
    }

    public function show(Customer $customer): View
    {
        $wallet = $this->wallets->walletFor($customer);
        $customer->load(['virtualMachines.bundle', 'virtualMachines.proxmoxServer']);
        $transactions = $wallet->transactions()->with('createdBy')->limit(10)->get();
        $summary = $this->billing->customerSummary($customer->id);

        return view('admin.customers.show', [
            'customer' => $customer,
            'financial' => [
                'balance' => $wallet->balance,
                'monthly_spend' => $summary['monthly_spend'],
                'unpaid_total' => $summary['unbilled_accrued'],
                'status' => $summary['unbilled_accrued'] > 0 ? 'در حال مصرف' : 'بدون مصرف',
            ],
            'virtualMachines' => $customer->virtualMachines,
            'invoices' => $this->dummyInvoices($customer),
            'billing' => $this->billing,
            'wallet' => $wallet,
            'walletTransactions' => $transactions,
            'wallets' => $this->wallets,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->fill($this->normalizedInput($request->validated(), true))->save();

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'اطلاعات مشتری به‌روزرسانی شد.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('status', 'مشتری حذف شد.');
    }

    public function suspend(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['suspension_reason' => ['nullable', 'string', 'max:255']]);
        $customer->suspend($data['suspension_reason'] ?? null);

        return back()->with('status', 'مشتری تعلیق شد.');
    }

    public function activate(Customer $customer): RedirectResponse
    {
        $customer->activate();

        return back()->with('status', 'مشتری فعال شد.');
    }

    public function updateSmsNotifications(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'sms_notifications_enabled' => ['required', 'boolean'],
        ]);

        $customer->forceFill([
            'sms_notifications_enabled' => (bool) $data['sms_notifications_enabled'],
        ])->save();

        return back()->with('status', (bool) $data['sms_notifications_enabled'] ? 'اعلان پیامکی مشتری فعال شد.' : 'اعلان پیامکی مشتری غیرفعال شد.');
    }

    public function impersonate(Request $request, Customer $customer): RedirectResponse
    {
        Auth::guard('customer')->login($customer);

        $request->session()->put('impersonated_by_admin_id', $request->user('admin')?->id);
        $request->session()->put('impersonated_customer_id', $customer->id);

        return redirect()->route('dashboard')
            ->with('status', 'شما اکنون به عنوان '.$customer->name.' وارد پورتال مشتری شده‌اید.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedInput(array $data, bool $isUpdate = false): array
    {
        if ($isUpdate && blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['email_verified_at'] = ($data['email_verified'] ?? false) ? now() : null;
        unset($data['email_verified']);

        if (($data['status'] ?? Customer::STATUS_ACTIVE) === Customer::STATUS_SUSPENDED) {
            $data['suspended_at'] = now();
        } else {
            $data['suspended_at'] = null;
            $data['suspension_reason'] = null;
        }

        $data['sms_notifications_enabled'] = (bool) ($data['sms_notifications_enabled'] ?? false);

        return $data;
    }

    /** @return array<string, mixed> */
    private function dummyFinancialStatus(Customer $customer): array
    {
        $balance = (($customer->id % 5) - 2) * 375000;

        return [
            'balance' => $balance,
            'monthly_spend' => 1490000 + ($customer->id * 83000),
            'unpaid_total' => max(0, -$balance) + (($customer->id % 3) * 420000),
            'status' => $balance < 0 ? 'بدهکار' : 'تسویه',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dummyVirtualMachines(Customer $customer): array
    {
        return collect(['web', 'db', 'cache'])->map(fn (string $role, int $index): array => [
            'name' => "{$customer->id}-{$role}-vm",
            'node' => 'pve-0'.(($customer->id + $index) % 3 + 1),
            'status' => $index === 2 && $customer->isSuspended() ? 'stopped' : 'running',
            'cpu' => [2, 4, 1][$index],
            'ram' => [4, 8, 2][$index].' GB',
            'disk' => [80, 160, 40][$index].' GB NVMe',
            'monthly_price' => [490000, 890000, 290000][$index],
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function dummyInvoices(Customer $customer): array
    {
        return collect(range(0, 3))->map(fn (int $index): array => [
            'number' => 'INV-'.now()->subMonths($index)->format('Ym').'-'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT),
            'date' => now()->subMonths($index)->startOfMonth(),
            'amount' => 890000 + ($index * 210000),
            'status' => $index === 0 && $customer->id % 2 === 0 ? 'پرداخت نشده' : 'پرداخت شده',
        ])->all();
    }
}
