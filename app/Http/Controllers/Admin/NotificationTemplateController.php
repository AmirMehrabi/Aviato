<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverCorporateNotification;
use App\Mail\BrandedNotificationMail;
use App\Models\AppSetting;
use App\Services\Notifications\NotificationTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function index(NotificationTemplates $templates)
    {
        return view('admin.settings.notification-templates', [
            'templates' => $templates->all(), 'defaults' => $templates->catalog(), 'branding' => $templates->branding(),
            'deliveries' => DB::table('notification_deliveries')->latest('id')->paginate(20),
            'smsNotifications' => $this->smsNotifications(),
        ]);
    }

    private function smsNotifications(): array
    {
        $machineTemplates = AppSetting::getValue('notifications.templates', []);
        $ticketEvents = [
            ['name' => 'تیکت جدید برای مشتری', 'key' => AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE],
            ['name' => 'تیکت جدید برای مدیر', 'key' => AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE],
            ['name' => 'پاسخ مشتری برای مدیر', 'key' => AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE],
            ['name' => 'پاسخ مدیر برای مشتری', 'key' => AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE],
            ['name' => 'واگذاری تیکت', 'key' => AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE],
        ];
        $rows = [[
            'name' => 'کد ورود / تأیید شماره', 'template' => AppSetting::getValue(AppSetting::KAVENEGAR_TEMPLATE, ''),
            'active' => true, 'note' => 'ضروری؛ در صورت انتخاب Kavenegar و تنظیم نام قالب.',
        ]];
        foreach ($ticketEvents as $event) {
            $rows[] = ['name' => $event['name'], 'template' => AppSetting::getValue($event['key'], ''),
                'active' => AppSetting::ticketSmsNotificationsEnabled(), 'note' => 'کنترل گروهی پیامک تیکت'];
        }
        foreach (DeliverCorporateNotification::smsTemplates() as $event => $definition) {
            $key = 'corporate.template.'.$event;
            $rows[] = ['name' => $event, 'template' => AppSetting::getValue($key, $definition['name']),
                'active' => (bool) AppSetting::getValue('corporate.sms_enabled', true)
                    && ($event !== 'corporate_submitted' || (bool) AppSetting::getValue('corporate.submission_sms_enabled', true)),
                'note' => 'کنترل تنظیمات پیامک حقوقی'];
        }
        $rows[] = ['name' => 'هشدار موجودی منفی فضای کاری', 'template' => AppSetting::customerWalletNegativeSmsTemplate(),
            'active' => AppSetting::customerWalletNegativeSmsEnabled(), 'note' => 'وابسته به تنظیم پیامک مشتری فضای کاری.'];
        foreach (['vm_created_customer' => 'ساخت ماشین برای مشتری', 'vm_created_admin' => 'ساخت ماشین برای مدیر'] as $event => $label) {
            $defaults = app(NotificationTemplates::class)->catalog()[$event];
            $saved = $machineTemplates[$event] ?? [];
            $rows[] = ['name' => $label, 'template' => $saved['sms_template'] ?? $defaults['sms_template'],
                'active' => (bool) ($saved['sms_enabled'] ?? $defaults['sms_enabled']), 'note' => 'Kavenegar Lookup'];
        }

        return $rows;
    }

    public function update(Request $request, NotificationTemplates $templates)
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(array_keys($templates->catalog()))],
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:10000'],
            'email_enabled' => ['required', 'boolean'], 'in_app_enabled' => ['required', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'sms_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'restore' => ['nullable', 'boolean'],
        ]);
        foreach (['subject', 'body'] as $field) {
            preg_match_all('/\{\{(.*?)\}\}/', $data[$field], $matches);
            if (array_diff($matches[1], ['machine_id', 'creator_name', 'name', 'title', 'body', 'ticket_number', 'server_name', 'server_ip', 'username', 'amount', 'receipt', 'time', 'ip'])) {
                return back()->withErrors([$field => 'متغیر ناشناخته است. از متغیرهای معرفی‌شده استفاده کنید.'])->withInput();
            }
        }
        $saved = AppSetting::getValue('notifications.templates', []);
        $event = $data['event'];
        if ($request->boolean('restore')) {
            unset($saved[$event]);
        } else {
            $saved[$event] = [
                'subject' => $data['subject'], 'body' => $data['body'],
                'email_enabled' => $templates->catalog()[$event]['required'] || $request->boolean('email_enabled'),
                'in_app_enabled' => $request->boolean('in_app_enabled'),
            ];
        }
        if (! $request->boolean('restore') && str_starts_with($event, 'vm_created_')) {
            $saved[$event]['sms_enabled'] = $request->boolean('sms_enabled');
            $saved[$event]['sms_template'] = $data['sms_template'] ?? $templates->catalog()[$event]['sms_template'];
        }
        AppSetting::setValue('notifications.templates', $saved, 'json', 'notifications');

        return back()->with('status', 'قالب و تنظیمات اعلان ذخیره شد.');
    }

    public function branding(Request $request)
    {
        $data = $request->validate([
            'website' => ['required', 'url:http,https', 'max:500'],
            'logo' => ['required', 'url:http,https', 'max:1000'],
            'tagline' => ['required', 'string', 'max:200'],
        ]);
        foreach ($data as $key => $value) {
            AppSetting::setValue('notifications.'.$key, $value, 'string', 'notifications');
        }

        return back()->with('status', 'هویت ایمیل ذخیره شد.');
    }

    public function preview(Request $request, string $event, NotificationTemplates $templates)
    {
        abort_unless(isset($templates->catalog()[$event]), 404);
        $mail = new BrandedNotificationMail($event, $this->samples(), route('dashboard'), $templates->catalog()[$event]['required'] ? '123456' : null);

        return response($mail->render())->header('Content-Security-Policy', "default-src 'none'; img-src https: http:; font-src https: http:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'");
    }

    public function test(Request $request, string $event, NotificationTemplates $templates)
    {
        abort_unless(isset($templates->catalog()[$event]), 404);
        $data = $request->validate(['test_email' => ['required', 'email', 'max:255']]);
        try {
            Mail::mailer('settings_smtp')->to($data['test_email'])->send(new BrandedNotificationMail($event, $this->samples(), route('dashboard'), $templates->catalog()[$event]['required'] ? '123456' : null));
        } catch (\Throwable) {
            return back()->withErrors(['test_email' => 'ارسال ناموفق بود. تنظیمات SMTP را بررسی کنید.']);
        }

        return back()->with('status', 'نمونه قالب به سرور ایمیل تحویل داده شد. کد نمونه برای ورود یا بازیابی معتبر نیست.');
    }

    private function samples(): array
    {
        return ['machine_id' => '123', 'creator_name' => 'مشتری نمونه', 'name' => 'همراه آویاتو', 'title' => 'یک خبر از آویاتو', 'body' => 'این یک پیام نمونه برای بررسی قالب است.', 'ticket_number' => 'T-1001', 'server_name' => 'my-cloud', 'server_ip' => '192.0.2.10', 'username' => 'ubuntu', 'initial_password' => 'Example-Only-Change-Me!', 'amount' => '۱۰۰٬۰۰۰ ریال', 'receipt' => 'RCP-00000001', 'time' => now('Asia/Tehran')->format('Y-m-d H:i'), 'ip' => '192.0.2.1'];
    }
}
