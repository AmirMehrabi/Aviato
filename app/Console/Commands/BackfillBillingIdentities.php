<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Corporate\BillingIdentityService;
use Illuminate\Console\Command;

class BackfillBillingIdentities extends Command
{
    protected $signature = 'billing:backfill-identities';

    protected $description = 'Freeze available legacy recipient identities without changing financial amounts';

    public function handle(BillingIdentityService $billing): int
    {
        Customer::query()->eachById(fn ($customer) => $billing->ensureBaseline($customer));
        foreach ([Invoice::class, Payment::class, DomainOrder::class] as $model) {
            $model::whereNull('billing_identity')->eachById(function ($document) {
                if ($document->customer && $document->canSnapshotBillingIdentity()) {
                    $identity = $document->resolveBillingIdentity();
                    $identity['source'] = 'legacy_backfill';
                    $document->forceFill(['billing_identity' => $identity])->saveQuietly();
                }
            });
        }
        $this->info('Legacy identities frozen from available records. Historical edits cannot be reconstructed.');

        return self::SUCCESS;
    }
}
