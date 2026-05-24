<?php

use App\Services\InvoiceService;
use App\Services\UsageBillingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('billing:charge-usage', function (UsageBillingService $billing) {
    $transactions = $billing->chargeAllDueUsage();

    $this->info(sprintf('Created %d usage charge transaction(s).', $transactions->count()));
})->purpose('Charge accrued PAYG usage to customer wallets');

Artisan::command('billing:generate-monthly-invoices', function (InvoiceService $invoices) {
    $generated = $invoices->generateMonthlyInvoices();

    $this->info(sprintf('Generated %d monthly invoice(s).', $generated->count()));
})->purpose('Generate monthly customer usage invoices');

Artisan::command('inspire', function () {
    $this->comment('Keep shipping.');
})->purpose('Display an inspiring quote');

Schedule::command('billing:charge-usage')->hourly();
Schedule::command('billing:generate-monthly-invoices')->monthlyOn(1, '00:15');
