<?php

namespace App\Services\Corporate;

use App\Models\BillingIdentity;
use App\Models\Customer;
use Carbon\CarbonInterface;

class BillingIdentityService
{
    public function individual(Customer $customer): array
    {
        return ['type' => 'individual', 'name' => $customer->name, 'first_name' => $customer->first_name,
            'last_name' => $customer->last_name, 'national_code' => $customer->national_code,
            'phone' => $customer->phone, 'email' => $customer->email, 'source' => 'profile_snapshot'];
    }

    public function at(Customer $customer, CarbonInterface $at): array
    {
        return BillingIdentity::where('customer_id', $customer->id)->where('effective_at', '<=', $at->copy()->setTimezone(config('app.timezone')))
            ->orderByDesc('effective_at')->orderByDesc('id')->first()?->identity ?? $this->individual($customer);
    }

    public function ensureBaseline(Customer $customer): void
    {
        if (! BillingIdentity::where('customer_id', $customer->id)->exists()) {
            BillingIdentity::create(['customer_id' => $customer->id, 'effective_at' => '1970-01-01 00:00:00', 'identity' => $this->individual($customer)]);
        }
    }
}
