<?php

namespace Tests\Concerns;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

trait FundsCustomerWallet
{
    protected function setUp(): void
    {
        parent::setUp();

        Customer::all();
        Customer::created(function (Customer $customer): void {
            DB::table('wallets')->where('customer_id', $customer->id)->update(['balance' => 10_000_000]);
        });
    }
}
