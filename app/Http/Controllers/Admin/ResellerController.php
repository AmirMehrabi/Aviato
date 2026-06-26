<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCustomerRequest;
use App\Http\Requests\Admin\StoreResellerRequest;
use App\Http\Requests\Admin\UpdateResellerRequest;
use App\Models\Customer;
use App\Models\ResellerWithdrawalRequest;
use App\Models\UsageSettlement;
use App\Services\ResellerService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function __construct(
        private readonly ResellerService $resellers,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,suspended'],
            'sort' => ['nullable', 'in:latest,oldest,name'],
        ]);

        $resellers = Customer::query()
            ->where('is_reseller', true)
            ->withCount(['activeResellerAssignments as active_customers_count'])
            ->withSum('resellerCommissions', 'commission_amount')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('reseller_code', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('reseller_status', 'active'))
            ->when(($filters['status'] ?? null) === 'suspended', fn ($query) => $query->where('reseller_status', 'suspended'))
            ->when(($filters['sort'] ?? 'latest') === 'oldest', fn ($query) => $query->oldest())
            ->when(($filters['sort'] ?? 'latest') === 'name', fn ($query) => $query->orderBy('name'))
            ->when(! in_array($filters['sort'] ?? 'latest', ['oldest', 'name'], true), fn ($query) => $query->latest())
            ->paginate(12)
            ->withQueryString();

        return view('admin.resellers.index', [
            'resellers' => $resellers,
            'filters' => $filters,
            'stats' => [
                'total' => Customer::where('is_reseller', true)->count(),
                'active' => Customer::where('is_reseller', true)->where('reseller_status', 'active')->count(),
                'suspended' => Customer::where('is_reseller', true)->where('reseller_status', 'suspended')->count(),
                'pending_withdrawals' => ResellerWithdrawalRequest::where('status', ResellerWithdrawalRequest::STATUS_PENDING)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.resellers.create', [
            'customers' => Customer::where('is_reseller', false)->where('status', Customer::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreResellerRequest $request): RedirectResponse
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));

        $this->resellers->enableReseller(
            $customer,
            $request->validated('commission_pct'),
            $request->validated('payout_method'),
        );

        return redirect()->route('admin.resellers.show', $customer)
            ->with('status', 'مشتری به عنوان فروشنده فعال شد.');
    }

    public function show(Customer $customer): View
    {
        $assignments = $customer->activeResellerAssignments()
            ->with('customer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customerIds = $assignments->pluck('customer_id')->all();
        $spendByCustomer = $customerIds ? UsageSettlement::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COALESCE(SUM(amount), 0) as total_spend')
            ->groupBy('customer_id')
            ->pluck('total_spend', 'customer_id')
            ->all() : [];

        $commissions = $customer->resellerCommissions()
            ->with('customer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $withdrawals = $customer->withdrawalRequests()
            ->with('processedBy')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = $this->resellers->resellerStats($customer);

        return view('admin.resellers.show', [
            'customer' => $customer,
            'stats' => $stats,
            'assignments' => $assignments,
            'spendByCustomer' => $spendByCustomer,
            'commissions' => $commissions,
            'withdrawals' => $withdrawals,
            'wallets' => $this->wallets,
        ]);
    }

    public function update(UpdateResellerRequest $request, Customer $customer): RedirectResponse
    {
        $this->resellers->updateReseller($customer, $request->validated());

        return back()->with('status', 'تنظیمات فروشنده به‌روزرسانی شد.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->resellers->disableReseller($customer);

        return redirect()->route('admin.resellers.index')
            ->with('status', 'فروشنده غیرفعال شد.');
    }

    public function suspend(Customer $customer): RedirectResponse
    {
        $this->resellers->suspendReseller($customer);

        return back()->with('status', 'فروشنده تعلیق شد.');
    }

    public function activate(Customer $customer): RedirectResponse
    {
        $this->resellers->activateReseller($customer);

        return back()->with('status', 'فروشنده فعال شد.');
    }

    public function assignCustomer(AssignCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $targetCustomer = Customer::findOrFail($request->validated('customer_id'));

        $this->resellers->assignCustomer(
            $customer,
            $targetCustomer,
            'admin',
            $request->user('admin'),
        );

        return back()->with('status', 'مشتری به فروشنده اختصاص داده شد.');
    }

    public function unassignCustomer(Customer $customer, Customer $targetCustomer): RedirectResponse
    {
        $this->resellers->unassignCustomer($customer, $targetCustomer);

        return back()->with('status', 'مشتری از فروشنده جدا شد.');
    }

    public function withdrawals(): View
    {
        $withdrawals = ResellerWithdrawalRequest::query()
            ->with(['reseller', 'processedBy'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.resellers.withdrawals', [
            'withdrawals' => $withdrawals,
            'stats' => [
                'pending' => ResellerWithdrawalRequest::where('status', ResellerWithdrawalRequest::STATUS_PENDING)->count(),
                'approved' => ResellerWithdrawalRequest::where('status', ResellerWithdrawalRequest::STATUS_APPROVED)->count(),
                'rejected' => ResellerWithdrawalRequest::where('status', ResellerWithdrawalRequest::STATUS_REJECTED)->count(),
                'paid' => ResellerWithdrawalRequest::where('status', ResellerWithdrawalRequest::STATUS_PAID)->count(),
            ],
        ]);
    }

    public function approveWithdrawal(ResellerWithdrawalRequest $request, ResellerWithdrawalRequest $withdrawal): RedirectResponse
    {
        $this->resellers->approveWithdrawal(
            $withdrawal,
            $request->user('admin'),
            $request->validated('admin_note') ?? null,
        );

        return back()->with('status', 'درخواست برداشت تایید شد.');
    }

    public function rejectWithdrawal(Request $request, ResellerWithdrawalRequest $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->resellers->rejectWithdrawal(
            $withdrawal,
            $request->user('admin'),
            $data['admin_note'] ?? null,
        );

        return back()->with('status', 'درخواست برداشت رد شد.');
    }

    public function markWithdrawalPaid(Request $request, ResellerWithdrawalRequest $withdrawal): RedirectResponse
    {
        $this->resellers->markWithdrawalPaid(
            $withdrawal,
            $request->user('admin'),
        );

        return back()->with('status', 'درخواست برداشت به عنوان پرداخت شده علامت‌گذاری شد.');
    }
}
