<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CustomerImpersonationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $impersonation = Cache::pull('customer-impersonation:'.hash('sha256', $token));

        abort_unless(
            is_array($impersonation)
            && isset($impersonation['admin_id'], $impersonation['customer_id']),
            403,
            'This impersonation link is invalid or has expired.',
        );

        $customer = Customer::query()->findOrFail($impersonation['customer_id']);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $request->session()->put([
            'impersonated_by_admin_id' => $impersonation['admin_id'],
            'impersonated_customer_id' => $customer->getKey(),
        ]);

        return redirect()->route('dashboard')
            ->with('status', 'شما اکنون به عنوان '.$customer->name.' وارد پورتال مشتری شده‌اید.');
    }
}
