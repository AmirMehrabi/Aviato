<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $wallet = $this->wallets->walletFor($customer);
        $invoices = $customer->invoices()->paginate(12);

        return view('customer.invoices.index', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'invoices' => $invoices,
            'latestInvoice' => $customer->invoices()->latest('period_start')->first(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $customer = $request->user('customer');
        abort_unless($invoice->customer_id === $customer->id, 404);
        $wallet = $this->wallets->walletFor($customer);
        $invoice->load(['items', 'customer']);

        return view('customer.invoices.show', [
            'customer' => $customer,
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'invoice' => $invoice,
        ]);
    }
}
