<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationTemplates;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, NotificationTemplates $templates)
    {
        return view('customer.notification-preferences', [
            'customer' => $request->user('customer'),
            'wallets' => app(WalletService::class),
            'templates' => $templates->customerEvents(),
            'preferences' => DB::table('customer_notification_preferences')->where('customer_id', $request->user('customer')->id)->get()->keyBy('event'),
        ]);
    }

    public function update(Request $request, NotificationTemplates $templates)
    {
        $rules = [];
        foreach ($templates->customerEvents() as $event => $template) {
            $rules['preferences.'.$event.'.email_enabled'] = ['required', 'boolean'];
            $rules['preferences.'.$event.'.in_app_enabled'] = ['required', 'boolean'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data, $request, $templates): void {
            foreach (array_keys($templates->customerEvents()) as $event) {
                DB::table('customer_notification_preferences')->updateOrInsert(
                    ['customer_id' => $request->user('customer')->id, 'event' => $event],
                    $data['preferences'][$event] + ['updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return back()->with('status', 'تنظیمات اعلان‌های شما ذخیره شد.');
    }
}
