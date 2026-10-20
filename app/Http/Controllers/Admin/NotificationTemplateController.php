<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);
    }

    public function update(Request $request, NotificationTemplates $templates)
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(array_keys($templates->catalog()))],
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:10000'],
            'email_enabled' => ['required', 'boolean'], 'in_app_enabled' => ['required', 'boolean'],
            'restore' => ['nullable', 'boolean'],
        ]);
        foreach (['subject', 'body'] as $field) {
            preg_match_all('/\{\{(.*?)\}\}/', $data[$field], $matches);
            if (array_diff($matches[1], ['name', 'title', 'body', 'ticket_number', 'server_name', 'server_ip', 'username', 'amount', 'receipt', 'time', 'ip'])) {
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
        return ['name' => 'همراه آویاتو', 'title' => 'یک خبر از آویاتو', 'body' => 'این یک پیام نمونه برای بررسی قالب است.', 'ticket_number' => 'T-1001', 'server_name' => 'my-cloud', 'server_ip' => '192.0.2.10', 'username' => 'ubuntu', 'initial_password' => 'Example-Only-Change-Me!', 'amount' => '۱۰۰٬۰۰۰ ریال', 'receipt' => 'RCP-00000001', 'time' => now('Asia/Tehran')->format('Y-m-d H:i'), 'ip' => '192.0.2.1'];
    }
}
