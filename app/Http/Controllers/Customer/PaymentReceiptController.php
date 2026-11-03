<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DomainOrder;
use App\Models\Payment;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
    ) {}

    public function show(Request $request, Payment $payment): View|RedirectResponse
    {
        $customer = $request->user('customer');
        if ($payment->type === Payment::TYPE_DOMAIN) {
            abort_unless($payment->isSuccessful() && (int) $payment->customer_id === (int) $customer->id, 404);

            return redirect()->to(route('customer.domains.orders.show', DomainOrder::findOrFail($payment->domain_order_id), false));
        }
        if ($payment->invoice_id) {
            abort_unless($payment->isSuccessful() && $this->projects->canViewBilling($payment->invoice->colocationService->project, $customer), 404);

            return redirect()->to(route('customer.colocation.invoices.show', $payment->invoice_id, false));
        }
        abort_if($payment->domain_order_id && $payment->customer_id !== $customer->id, 404);
        $activeProject = $payment->domain_order_id ? $customer->ensureDefaultProject() : $this->projects->activeProject($request, $customer);

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
            'company' => AppSetting::companyProfile(),
        ]);
    }
}
