<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayManager $gateways,
        private readonly ProjectAccessService $projects,
        private readonly WalletService $wallets,
    ) {}

    public function storeTopUp(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless((int) $activeProject->owner_customer_id === (int) $customer->id, 404);
        $data = $request->validate([
            'amount' => ['nullable', 'required_without:custom_amount', 'integer', 'min:1000000', 'max:500000000'],
            'custom_amount' => ['nullable', 'required_without:amount', 'integer', 'min:1000000', 'max:500000000'],
            'gateway' => ['required', 'string', 'in:'.implode(',', array_keys($this->gateways->available()))],
        ]);

        $amount = (int) ($data['custom_amount'] ?: $data['amount'] ?: 0);

        try {
            $payment = $this->payments->createTopUp(
                $customer,
                $amount,
                'شارژ کیف پول توسط مشتری',
                $data['gateway'],
            );
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return back()
                ->withErrors(['payment' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()->route('customer.wallet.payments.gateway.show', $payment);
    }

    public function showGateway(Request $request, Payment $payment): View|RedirectResponse
    {
        $this->ensureOwnership($request, $payment);

        if (! $payment->isPending()) {
            return redirect()->route('customer.wallet.show')
                ->with('status', 'وضعیت این پرداخت قبلا ثبت شده است.');
        }

        $view = $payment->provider === 'mellat'
            ? 'customer.payments.mellat-redirect'
            : 'customer.payments.gateway-redirect';

        return view($view, [
            'customer' => $request->user('customer'),
            'payment' => $payment,
            'gatewayLabel' => $this->gateways->gateway($payment->provider)->label(),
            'wallet' => $this->wallets->walletFor($request->user('customer')),
            'wallets' => $this->wallets,
        ]);
    }

    public function submitGateway(Request $request, Payment $payment): RedirectResponse
    {
        abort(404);
    }

    public function callback(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->isPending()) {
            try {
                $this->payments->completeTopUp($payment, $request->all() + [
                    'callback_received_at' => now()->toIso8601String(),
                    'customer_ip' => $request->ip(),
                ]);
            } catch (PaymentGatewayException $exception) {
                report($exception);

                if ($exception->shouldFailPayment) {
                    $this->payments->failTopUp($payment, [
                        'callback' => $request->all(),
                        'failed_reason' => $exception->getMessage(),
                        'res_code' => $exception->responseCode,
                    ]);
                } else {
                    $this->payments->recordGatewayPayload($payment, [
                        'callback' => $request->all(),
                        'callback_received_at' => now()->toIso8601String(),
                        'verification_error' => $exception->getMessage(),
                    ]);
                }

                return redirect()->route('customer.wallet.show')
                    ->with('error', $exception->getMessage());
            }
        }

        return redirect()->route('customer.wallet.show')
            ->with('status', 'پرداخت با موفقیت تایید شد و کیف پول شما شارژ شد.');
    }

    private function ensureOwnership(Request $request, Payment $payment): void
    {
        abort_unless($payment->customer_id === $request->user('customer')->id, 404);
    }
}
