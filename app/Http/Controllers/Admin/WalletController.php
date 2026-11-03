<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\WalletService;
use App\Support\PersianDigits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function storeTransaction(Request $request, Customer $customer): RedirectResponse
    {
        $multiplier = AppSetting::currency() === 'IRR' ? 10 : 1;
        $rawAmount = $request->input('amount_toman');
        if (is_string($rawAmount)) {
            $request->merge(['amount_toman' => str_replace([',', '٬'], '', strtr($rawAmount, array_combine(
                preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
                str_split('01234567890123456789'),
            )))]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:'.intdiv(PHP_INT_MAX, $multiplier)],
            'description' => ['required', 'string', 'max:255'],
            'allow_negative' => ['nullable', 'boolean'],
        ], [], ['amount_toman' => 'مبلغ (تومان)']);

        $amount = (int) $data['amount_toman'] * $multiplier;

        match ($data['type']) {
            'credit' => $this->wallets->credit($customer, $amount, $data['description'], $request->user('admin')),
            'debit' => $this->wallets->debit($customer, $amount, $data['description'], $request->user('admin'), allowNegative: $request->boolean('allow_negative')),
        };

        return back()->with('status', PersianDigits::format((int) $data['amount_toman']).' تومان '.($data['type'] === 'credit' ? 'به کیف پول اضافه شد.' : 'از کیف پول کسر شد.'));
    }

    public function updateLock(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'is_locked' => ['required', 'boolean'],
            'lock_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $wallet = $this->wallets->walletFor($customer);
        $wallet->forceFill([
            'is_locked' => (bool) $data['is_locked'],
            'lock_reason' => $data['is_locked'] ? ($data['lock_reason'] ?? null) : null,
        ])->save();

        return back()->with('status', (bool) $data['is_locked'] ? 'کیف پول قفل شد.' : 'کیف پول باز شد.');
    }
}
