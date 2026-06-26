<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RequestWithdrawalRequest;
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
        $customer = $request->user('customer');
        $customer->load('wallet');
        $stats = $this->resellers->resellerStats($customer);
        $recentCommissions = $customer->resellerCommissions()
            ->with('customer')
            ->latest()
            ->limit(5)
            ->get();

        $monthlyCommissions = $customer->resellerCommissions()
            ->selectRaw('service_date, commission_amount')
            ->orderByDesc('service_date')
            ->limit(180)
            ->get()
            ->groupBy(fn ($c) => $c->service_date->format('Y-m'))
            ->map(fn ($group) => ['month' => $group->first()->service_date->format('Y-m'), 'total' => $group->sum('commission_amount')])
            ->values()
            ->take(6)
            ->reverse();

        return view('customer.reseller.dashboard', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'stats' => $stats,
            'recentCommissions' => $recentCommissions,
            'monthlyCommissions' => $monthlyCommissions,
        ]);
    }

    public function customers(Request $request): View
    {
        $customer = $request->user('customer');
        $customer->load('wallet');
        $assignments = $customer->activeResellerAssignments()
            ->with('customer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('customer.reseller.customers', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'assignments' => $assignments,
        ]);
    }

    public function commissions(Request $request): View
    {
        $customer = $request->user('customer');
        $customer->load('wallet');
        $commissions = $customer->resellerCommissions()
            ->with('customer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('customer.reseller.commissions', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'commissions' => $commissions,
        ]);
    }

    public function referralLink(Request $request): View
    {
        $customer = $request->user('customer');
        $customer->load('wallet');
        $referralUrl = route('customer.register', ['ref' => $customer->reseller_code], false);

        return view('customer.reseller.referral', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'referralUrl' => $referralUrl,
        ]);
    }

    public function withdrawals(Request $request): View
    {
        $customer = $request->user('customer');
        $customer->load('wallet');
        $withdrawals = $customer->withdrawalRequests()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customer.reseller.withdrawals', [
            'customer' => $customer,
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function storeWithdrawal(RequestWithdrawalRequest $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $this->resellers->requestWithdrawal(
            $customer,
            $request->validated('amount'),
        );

        return back()->with('status', 'درخواست برداشت ثبت شد و در انتظار بررسی است.');
    }
}
