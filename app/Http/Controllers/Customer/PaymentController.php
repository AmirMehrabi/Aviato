<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
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
        abort_unless($this->canTopUpProjectWallet($activeProject, $customer), 404);

        $request->merge([
            'amount_toman' => $this->normalizeTomanAmount($request->input('amount_toman')),
        ]);

        $data = $request->validate([
            'amount_toman' => ['required', 'integer', 'min:100000', 'max:50000000'],
            'gateway' => ['required', 'string', 'in:'.implode(',', array_keys($this->gateways->available()))],
        ], [
            'amount_toman.required' => 'مبلغ شارژ را انتخاب یا وارد کنید.',
            'amount_toman.integer' => 'مبلغ شارژ باید یک عدد معتبر باشد.',
            'amount_toman.min' => 'حداقل مبلغ شارژ ۱۰۰٬۰۰۰ تومان است.',
            'amount_toman.max' => 'حداکثر مبلغ شارژ ۵۰٬۰۰۰٬۰۰۰ تومان است.',
            'gateway.required' => 'درگاه پرداخت را انتخاب کنید.',
            'gateway.in' => 'درگاه پرداخت انتخاب‌شده در دسترس نیست.',
        ]);

        $amount = (int) $data['amount_toman'] * 10;

        try {
            $billingCustomer = $activeProject->owner;
            $payment = $this->payments->createTopUp(
                $billingCustomer,
                $amount,
                'شارژ کیف پول فضای کاری',
                $data['gateway'],
            );
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return back()
                ->withErrors(['payment' => 'درگاه پرداخت در دسترس نیست. لطفاً دوباره تلاش کنید یا روش پرداخت دیگری را انتخاب کنید.'])
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
            'wallet' => $this->wallets->walletFor($payment->customer),
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

                return $this->walletRedirect($payment);
            }
        }

        return $this->walletRedirect($payment);
    }

    private function ensureOwnership(Request $request, Payment $payment): void
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);

        abort_unless(
            (int) $payment->customer_id === (int) $activeProject->owner_customer_id
            && $this->canTopUpProjectWallet($activeProject, $customer),
            404
        );
    }

    private function canTopUpProjectWallet(Project $project, Customer $customer): bool
    {
        return $this->projects->canViewBilling($project, $customer);
    }

    private function walletRedirect(Payment $payment): RedirectResponse
    {
        return redirect()->route('customer.wallet.show', [
            'payment_id' => $payment->id,
        ]);
    }

    private function normalizeTomanAmount(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $normalized = strtr((string) $amount, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);

        $normalized = preg_replace('/[\s,٬،]+/u', '', $normalized);

        return $normalized === '' ? null : $normalized;
    }
}
