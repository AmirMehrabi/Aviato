<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function label(): string
    {
        return match ($this->action) {
            'start' => 'روشن کردن', 'shutdown' => 'خاموش کردن',
            'restart' => 'راه‌اندازی مجدد', 'force-stop' => 'خاموش کردن اجباری',
            'upgrade' => 'ارتقای منابع', default => 'تغییر سرور',
        };
    }

    public function progressLabel(): ?string
    {
        $steps = array_keys($this->details['steps'] ?? []);
        $step = end($steps);

        return match ($step) {
            'shutdown' => 'سرور خاموش شد؛ تغییر منابع در حال انجام است.',
            'hardware_submitted', 'hardware_applied' => 'در حال تنظیم پردازنده و حافظه',
            'disk_submitted', 'disk_applied' => 'در حال افزایش فضای ذخیره‌سازی',
            'start_submitted', 'started' => 'در حال روشن کردن سرور پس از ارتقا',
            default => null,
        };
    }

    public function customerStatus(): string
    {
        return match ($this->status) {
            'succeeded' => 'انجام شد', 'failed' => 'انجام نشد',
            'queued' => 'در صف انجام', 'checking' => 'در حال بررسی نتیجه',
            default => $this->created_at->lt(now()->subMinutes(3)) ? 'بیش از معمول طول کشیده است' : 'در حال انجام',
        };
    }
}
