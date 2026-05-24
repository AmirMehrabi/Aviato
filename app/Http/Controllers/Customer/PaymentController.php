<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly WalletService $wallets,
    ) {}

    public function storeTopUp(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate([
            'amount' => ['nullable', 'required_without:custom_amount', 'integer', 'min:10000', 'max:500000000'],
            'custom_amount' => ['nullable', 'required_without:amount', 'integer', 'min:10000', 'max:500000000'],
        ]);

        $amount = (int) ($data['custom_amount'] ?: $data['amount'] ?: 0);

        $payment = $this->payments->createTopUp($customer, $amount, 'شارژ کیف پول توسط مشتری');

        return redirect()->route('customer.wallet.payments.gateway.show', $payment);
    }

    public function showGateway(Request $request, Payment $payment): View
    {
        $this->ensureOwnership($request, $payment);

        return view('customer.payments.dummy-gateway', [
            'customer' => $request->user('customer'),
            'payment' => $payment,
            'wallet' => $this->wallets->walletFor($request->user('customer')),
            'wallets' => $this->wallets,
        ]);
    }

    public function submitGateway(Request $request, Payment $payment): RedirectResponse
    {
        $this->ensureOwnership($request, $payment);
        $this->payments->completeTopUp($payment, [
            'submitted_via' => 'dummy_gateway',
            'customer_ip' => $request->ip(),
        ]);

        return redirect()->route('customer.wallet.show')
            ->with('status', 'پرداخت آزمایشی با موفقیت انجام شد و کیف پول شما شارژ شد.');
    }

    public function callback(Request $request, Payment $payment): RedirectResponse
    {
        $this->ensureOwnership($request, $payment);

        if ($payment->isPending()) {
            $this->payments->completeTopUp($payment, ['callback' => true]);
        }

        return redirect()->route('customer.wallet.show')
            ->with('status', 'وضعیت پرداخت به روز شد.');
    }

    private function ensureOwnership(Request $request, Payment $payment): void
    {
        abort_unless($payment->customer_id === $request->user('customer')->id, 404);
    }
}
