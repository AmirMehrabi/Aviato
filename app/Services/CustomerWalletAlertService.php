<?php

namespace App\Services;

use App\Jobs\ReconcileWalletRestrictions;
use App\Models\Customer;

class CustomerWalletAlertService
{
    public function __construct(private readonly WorkspaceWalletAlertService $workspaceAlerts) {}

    public function handleWalletBalanceChange(Customer $customer): void
    {
        ReconcileWalletRestrictions::dispatch($customer->id)->afterCommit();
        $this->workspaceAlerts->checkOwner($customer);
    }
}
