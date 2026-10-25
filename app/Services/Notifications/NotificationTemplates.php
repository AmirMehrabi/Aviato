<?php

namespace App\Services\Notifications;

use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class NotificationTemplates
{
    public function catalog(): array
    {
        $items = [
            'domain_order' => ['سفارش دامنه', 'وضعیت سفارش دامنه | آویاتو', "{{name}} عزیز،\n{{body}}", false],
            'domain_expiry' => ['تمدید و انقضای دامنه', 'تمدید دامنه | آویاتو', "{{name}} عزیز،\n{{body}}", false],
            'password_reset' => ['بازیابی رمز عبور', 'کد بازیابی رمز عبور آویاتو', "{{name}} عزیز،\nبرای انتخاب رمز عبور جدید، کد زیر را در پنل آویاتو وارد کنید.\nاین کد تا ۱۰ دقیقه معتبر است. اگر شما این درخواست را ثبت نکرده‌اید، این ایمیل را نادیده بگیرید. کد را با کسی به اشتراک نگذارید.", true],
            'login_code' => ['کد ورود', 'کد ورود به آویاتو', "{{name}} عزیز،\nبرای ورود امن به فضای ابری خود، کد زیر را وارد کنید.\nاین کد تا ۱۰ دقیقه معتبر است. کد ورود را در اختیار دیگران قرار ندهید.", true],
            'email_verification' => ['تأیید ایمیل', 'یک قدم تا شروع با آویاتو', "{{name}} عزیز، به آویاتو خوش آمدید!\nبرای تأیید ایمیل و شروع مسیر ابری خود، کد زیر را وارد کنید.\nاین کد تا ۱۰ دقیقه معتبر است.", true],
            'signed_in' => ['ورود موفق', 'ورود جدید به حساب آویاتو', "{{name}} عزیز،\nورود جدیدی به حساب شما در {{time}} ثبت شد.\nنشانی IP: {{ip}}\nاگر این ورود متعلق به شما نیست، رمز عبور خود را تغییر دهید.", false],
            'password_changed' => ['تغییر رمز عبور', 'رمز عبور آویاتو تغییر کرد', "{{name}} عزیز،\nرمز عبور حساب شما با موفقیت تغییر کرد.\nاگر این تغییر را شما انجام نداده‌اید، با پشتیبانی آویاتو تماس بگیرید.", false],
            'workspace_added' => ['دعوت به فضای کاری', 'فضای کاری تازه در آویاتو', "{{name}} عزیز،\n{{body}}\nهمراه تیم خود، مسیر ابری تازه‌ای بسازید.", false],
            'workspace_wallet_balance' => ['موجودی کیف پول', 'موجودی کیف پول آویاتو', "{{name}} عزیز،\n{{body}}\nبا شارژ به‌موقع کیف پول، سرویس‌های خود را بدون وقفه نگه دارید.", false],
            'vm_wallet_retention' => ['نگهداری و حذف سرور', '{{title}} | آویاتو', "{{name}} عزیز،\n{{body}}\nبرای بررسی وضعیت سرویس و پرداخت، به پنل خود مراجعه کنید.", false],
            'server_ready' => ['آماده شدن سرور', 'سرور شما در آویاتو آماده است', "{{name}} عزیز،\nسرور «{{server_name}}» آماده استفاده است.\nنشانی IP: {{server_ip}}\nنام کاربری: {{username}}\nلطفاً برای امنیت حساب سرور، پس از اولین ورود رمز عبور را تغییر دهید.", false],
            'payment_received' => ['پرداخت موفق', 'پرداخت شما در آویاتو ثبت شد', "{{name}} عزیز،\nپرداخت شما به مبلغ {{amount}} ثبت شد.\nشماره رسید: {{receipt}}\nاز همراهی شما با آویاتو سپاسگزاریم.", false],
            'wallet_charged' => ['شارژ کیف پول و بازگشت سرویس', 'کیف پول شما در آویاتو شارژ شد', "{{name}} عزیز،\nکیف پول شما به مبلغ {{amount}} شارژ شد. از همراهی شما سپاسگزاریم.\nسرویس‌های متوقف‌شده شما در حال بازگشت هستند و پس از تکمیل فرایند در دسترس خواهند بود.", false],
            'routeros_vm_created' => ['ساخت ماشین RouterOS', 'ماشین RouterOS شما آماده پیگیری است', "{{name}} عزیز،\nماشین RouterOS «{{server_name}}» ساخته شد. اطلاعات ورود شما حداکثر تا یک ساعت آینده از طریق تیکت برایتان ارسال می‌شود.", false],
            'vm_node_move_finished' => ['انتقال Node (مدیر)', 'انتقال ماشین‌ها پایان یافت | آویاتو', "{{name}} عزیز،\n{{body}}", false],
        ];
        foreach ([
            'ticket_created' => 'تیکت جدید (مدیر)',
            'ticket_created_for_customer' => 'تیکت ثبت‌شده برای مشتری',
            'ticket_customer_reply' => 'پاسخ مشتری (مدیر)',
            'ticket_admin_reply' => 'پاسخ پشتیبانی',
            'ticket_assigned' => 'واگذاری تیکت (مدیر)',
            'ticket_status_changed' => 'تغییر وضعیت تیکت',
        ] as $event => $label) {
            $items[$event] = [$label, '{{title}} | آویاتو', "{{name}} عزیز،\n{{body}}\nشماره تیکت: {{ticket_number}}\nتیم آویاتو در کنار شماست. جزئیات را در پنل ببینید.", false];
        }

        return collect($items)->map(fn ($item, $event) => [
            'label' => $item[0], 'subject' => $item[1], 'body' => $item[2], 'required' => $item[3],
            'email_enabled' => $item[3] || str_starts_with($event, 'ticket_') || str_starts_with($event, 'domain_'),
            'in_app_enabled' => true,
        ])->all();
    }

    public function all(): array
    {
        $saved = AppSetting::getValue('notifications.templates', []);

        return collect($this->catalog())->map(fn ($default, $key) => array_replace($default, $saved[$key] ?? []))->all();
    }

    public function customerEvents(): array
    {
        return array_filter($this->all(), fn ($item, $key) => ! $item['required'] && ! in_array($key, ['vm_node_move_finished', 'ticket_created', 'ticket_customer_reply', 'ticket_assigned']), ARRAY_FILTER_USE_BOTH);
    }

    public function enabled(string $event, string $channel, ?object $recipient = null): bool
    {
        $template = $this->all()[$event] ?? null;
        if (! $template) {
            return false;
        }
        if ($template['required']) {
            return $channel === 'email';
        }
        if (! $template[$channel.'_enabled']) {
            return false;
        }
        if ($channel === 'email' && str_starts_with($event, 'ticket_') && ! AppSetting::ticketEmailNotificationsEnabled()) {
            return false;
        }
        if ($recipient instanceof Customer) {
            return (bool) (DB::table('customer_notification_preferences')->where('customer_id', $recipient->id)->where('event', $event)->value($channel.'_enabled') ?? true);
        }

        return true;
    }

    public function render(string $event, array $variables = []): array
    {
        $template = $this->all()[$event];
        $variables += ['name' => 'همراه آویاتو', 'title' => $template['label'], 'body' => '', 'ticket_number' => '', 'server_name' => '', 'server_ip' => '', 'username' => '', 'amount' => '', 'receipt' => '', 'time' => '', 'ip' => ''];
        $replace = collect($variables)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => is_scalar($value) ? (string) $value : ''])->all();

        return ['subject' => strtr($template['subject'], $replace), 'body' => strtr($template['body'], $replace)];
    }

    public function branding(): array
    {
        return [
            'website' => AppSetting::getValue('notifications.website', 'https://aviato.ir'),
            'logo' => AppSetting::getValue('notifications.logo', rtrim(config('app.url'), '/').'/assets/images/aviato_logo_full_color.png'),
            'tagline' => AppSetting::getValue('notifications.tagline', 'آویاتو؛ همراه مسیر ابری شما'),
        ];
    }
}
