<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\DomainSearchRequest;
use App\Services\Domains\DomainWorkspaceService;

class CheckDomainRequest extends DomainSearchRequest
{
    public function authorize(): bool
    {
        $customer = $this->user('customer');
        if (! $customer || ! $customer->canPurchaseDomains()) {
            return false;
        }
        $workspaces = app(DomainWorkspaceService::class);

        return $workspaces->canPurchase($workspaces->active($this), $customer);
    }
}
