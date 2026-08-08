<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function show(Request $request, Payment $payment): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);

        abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);
        abort_unless(
            (int) $payment->customer_id === (int) $activeProject->owner_customer_id
            && $payment->type === Payment::TYPE_TOP_UP
            && $payment->isSuccessful(),
            404,
        );

        $payment->load('customer');

        return view('customer.payments.receipt', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $this->wallets->walletFor($activeProject->owner),
            'wallets' => $this->wallets,
            'payment' => $payment,
            'gatewayLabel' => $this->gateways->labelFor($payment->provider),
            'company' => AppSetting::companyProfile(),
        ]);
    }
}
