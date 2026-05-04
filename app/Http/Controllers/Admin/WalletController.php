<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function storeTransaction(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'allow_negative' => ['nullable', 'boolean'],
        ]);

        match ($data['type']) {
            'credit' => $this->wallets->credit($customer, (int) $data['amount'], $data['description'], $request->user('admin')),
            'debit' => $this->wallets->debit($customer, (int) $data['amount'], $data['description'], $request->user('admin'), allowNegative: $request->boolean('allow_negative')),
        };

        return back()->with('status', 'تراکنش کیف پول ثبت شد.');
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
