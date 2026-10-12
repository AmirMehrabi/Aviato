<?php

namespace App\Notifications;

use App\Models\VirtualMachine;
use App\Services\BillingService;
use App\Services\WalletService;
use App\Support\Jalali;
use App\Support\PersianDigits;
use Illuminate\Notifications\Notification;

class VmWalletRetentionNotification extends Notification
{
    public function __construct(private readonly VirtualMachine $vm, private readonly string $stage) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deadline = PersianDigits::convert(Jalali::format($this->vm->wallet_retention_expires_at));
        $price = app(WalletService::class)->format((int) ceil(app(BillingService::class)->persistentHourly($this->vm) * 24));
        $name = $this->vm->display_name;
        $title = match ($this->stage) {
            'stopped' => 'سرور به‌دلیل موجودی ناکافی خاموش شد',
            '48h' => 'کمتر از ۴۸ ساعت تا حذف دائمی سرور',
            '24h' => 'کمتر از ۲۴ ساعت تا حذف دائمی سرور',
            'deleted' => 'سرور پس از پایان مهلت نگهداری حذف شد',
            default => 'برای حفظ سرور، کیف پول را شارژ کنید',
        };
        $body = $this->stage === 'deleted'
            ? "سرور «{$name}» پس از پایان مهلت نگهداری حذف شد. داده‌های دیسک قابل بازیابی نیستند و بدهی قبلی باقی می‌ماند. بکاپ‌های این سرور نیز حذف می‌شوند."
            : "موجودی قابل استفاده کیف پول تمام شده است. برای جلوگیری از حذف دائمی «{$name}»، پیش از {$deadline} به وقت تهران کیف پول مالک را شارژ کنید تا موجودی قابل استفاده مثبت شود. هزینه نگهداری در حالت خاموش {$price} در روز است؛ دیسک اضافه و بکاپ جداگانه محاسبه می‌شوند. پس از پایان مهلت، سرور، دیسک‌ها و بکاپ‌های آن حذف می‌شوند. شارژ ناقص مهلت را تمدید نمی‌کند.";

        return [
            'event' => 'vm_wallet_retention',
            'project_id' => $this->vm->project_id,
            'virtual_machine_id' => $this->vm->id,
            'title' => $title,
            'body' => $body,
            'url' => $this->vm->project
                ? route('customer.projects.enter', [$this->vm->project, 'wallet_topup' => 1], false)
                : route('customer.wallet.show', ['topup' => 1], false),
            'deadline' => $this->vm->wallet_retention_expires_at?->toISOString(),
            'stage' => $this->stage,
        ];
    }
}
