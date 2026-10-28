<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverCorporateNotification;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CorporateSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.corporate.settings', ['reviewers' => User::where('is_active', true)->get()->filter(fn ($user) => $user->allows('corporate.read') && $user->allows('corporate.review')), 'templates' => DeliverCorporateNotification::smsTemplates()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'email_enabled' => ['required', 'boolean'], 'sms_enabled' => ['required', 'boolean'],
            'in_app_enabled' => ['required', 'boolean'], 'submission_sms_enabled' => ['required', 'boolean'], 'reviewer_ids' => ['nullable', 'array'],
            'reviewer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
            'templates' => ['required', 'array'], 'templates.*' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9-]{0,99}$/']]);
        $ids = $data['reviewer_ids'] ?? [];
        foreach (User::whereIn('id', $ids)->get() as $user) {
            if (! $user->allows('corporate.read') || ! $user->allows('corporate.review')) {
                return back()->withErrors(['reviewer_ids' => 'گیرندگان باید مجوز بررسی درخواست حقوقی داشته باشند.']);
            }
        }
        if ($data['enabled'] && (empty($ids) || AppSetting::nationalCodeVerificationToken() === '')) {
            return back()->withErrors(['enabled' => 'ابتدا توکن زحل و حداقل یک بررسی‌کننده را تنظیم کنید.']);
        }
        foreach (['enabled', 'email_enabled', 'sms_enabled', 'in_app_enabled', 'submission_sms_enabled'] as $key) {
            AppSetting::setValue('corporate.'.$key, (bool) $data[$key], 'boolean', 'corporate');
        }
        AppSetting::setValue('corporate.reviewer_ids', $ids, 'json', 'corporate');
        foreach (DeliverCorporateNotification::smsTemplates() as $event => $template) {
            if (isset($data['templates'][$event])) {
                AppSetting::setValue('corporate.template.'.$event, $data['templates'][$event], 'string', 'corporate');
            }
        }

        return back()->with('status', 'تنظیمات ذخیره شد.');
    }
}
