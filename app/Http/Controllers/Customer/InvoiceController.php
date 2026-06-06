<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);
        $billingCustomer = $activeProject->owner;
        $wallet = $this->wallets->walletFor($billingCustomer);
        $invoices = $billingCustomer->invoices()
            ->whereHas('items', function ($query) use ($activeProject): void {
                $query->where('meta->project_id', $activeProject->id)
                    ->orWhereNull('meta->project_id');
            })
            ->paginate(12);

        return view('customer.invoices.index', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'invoices' => $invoices,
            'latestInvoice' => $billingCustomer->invoices()
                ->whereHas('items', function ($query) use ($activeProject): void {
                    $query->where('meta->project_id', $activeProject->id)
                        ->orWhereNull('meta->project_id');
                })
                ->latest('period_start')
                ->first(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $customer = $request->user('customer');
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
