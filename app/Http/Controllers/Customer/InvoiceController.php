<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Domains\DomainWorkspaceService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        if ($request->query('tab') === 'domains') {
            $activeProject = $this->projects->activeProject($request, $customer);
            abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);

            return view('customer.domains.invoices', ['customer' => $customer, 'activeProject' => $activeProject, 'wallets' => $this->wallets, 'invoices' => Invoice::whereHas('domainOrder', fn ($query) => $query->where('project_id', $activeProject->id))->where('kind', 'domain')->latest('issued_at')->paginate(12)->withQueryString()]);
        }
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);
        $billingCustomer = $activeProject->owner;
        $wallet = $this->wallets->walletFor($billingCustomer);
        $activeTab = $request->query('tab') === 'receipts' ? 'receipts' : 'usage';
        $invoiceQuery = $billingCustomer->invoices()->where('kind', 'usage')
            ->whereHas('items', function ($query) use ($activeProject): void {
                $query->where('meta->project_id', $activeProject->id)
                    ->orWhereNull('meta->project_id');
            });
        $receiptQuery = $billingCustomer->payments()->when($customer->id !== $billingCustomer->id, fn ($query) => $query->whereNull('domain_order_id'))
            ->where('type', Payment::TYPE_TOP_UP)
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->reorder('paid_at', 'desc');
        $invoices = $activeTab === 'usage'
            ? (clone $invoiceQuery)->paginate(12)->withQueryString()
            : null;
        $receipts = $activeTab === 'receipts'
            ? (clone $receiptQuery)->paginate(12)->withQueryString()
            : null;

        return view('customer.invoices.index', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'invoices' => $invoices,
            'receipts' => $receipts,
            'activeTab' => $activeTab,
            'invoiceTotal' => (clone $invoiceQuery)->count(),
            'receiptTotal' => (clone $receiptQuery)->count(),
            'latestInvoice' => (clone $invoiceQuery)->latest('period_start')->first(),
            'latestReceipt' => (clone $receiptQuery)->first(),
            'gatewayLabels' => $this->gateways->labels(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $customer = $request->user('customer');
        if ($invoice->kind === 'domain') {
            $order = DomainOrder::findOrFail($invoice->domain_order_id);
            $activeProject = app(DomainWorkspaceService::class)->order($request, $order, invoice: true);

            return view('customer.domains.invoice', ['customer' => $customer, 'activeProject' => $activeProject, 'order' => $order, 'invoice' => $invoice->load('items'), 'wallets' => $this->wallets]);
        }
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);
        abort_unless($invoice->customer_id === $activeProject->owner_customer_id, 404);
        $wallet = $this->wallets->walletFor($activeProject->owner);
        $invoice->load(['items', 'customer']);
        abort_unless($invoice->items->contains(fn ($item): bool => ! isset($item->meta['project_id']) || (int) $item->meta['project_id'] === (int) $activeProject->id), 404);

        return view('customer.invoices.show', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'invoice' => $invoice,
        ]);
    }
}
