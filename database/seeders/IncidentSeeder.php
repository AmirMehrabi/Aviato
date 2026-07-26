<?php

namespace Database\Seeders;

use App\Models\Incident;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        $startedAt = Carbon::create(2026, 7, 25, 9, 42, 0, 'Asia/Tehran');
        $endedAt = Carbon::create(2026, 7, 25, 11, 18, 0, 'Asia/Tehran');

        $incident = Incident::updateOrCreate(
            ['slug' => 'upstream-connectivity-2026-07-25'],
            [
                'title' => 'اختلال اتصال اپ‌استریم',
                'status' => Incident::STATUS_RESOLVED,
                'affected_service' => 'اتصال شبکه سرورهای ابری',
                'impact_summary' => 'برخی سرورهای ابری با افت بسته و تایم‌اوت اتصال در دسترسی به شبکه‌های خارجی مواجه شدند.',
                'summary' => 'در تاریخ ۲۵ تیرماه، مشکل اتصال در یکی از ارائه‌دهندگان اپ‌استریم بخشی از سرورهای ابری آویاتو را تحت تأثیر قرار داد. ترافیک ورودی و خروجی به برخی مقاصد به صورت متناوب با تأخیر یا افت مواجه شد. سرویس‌های آویاتو در دسترس باقی ماندند، اما مشتریان تحت تأثیر ممکن است اتصالات ناپایداری را تجربه کرده باشند.',
                'root_cause' => 'ارائه‌دهنده اپ‌استریم ناپایداری در مسیر ترانزیتی که یکی از مکان‌های شبکه ما را سرویس‌دهی می‌کرد، تجربه کرد. تیم شبکه مسیر آسیب‌دیده را جدا کرده و با ارائه‌دهنده برای بازگرداندن مسیر پایدار هماهنگی کرد.',
                'customer_impact' => 'مشتریانی که بارهای کاری آن‌ها روی مسیر تحت تأثیر قرار داشت، ممکن است حدود ۹۶ دقیقه افت بسته، افزایش تأخیر یا تایم‌اوت را تجربه کرده باشند. هیچ داده‌ای از مشتریان از بین نرفت و ذخیره‌سازی محلی ماشین‌های مجازی تحت تأثیر قرار نگرفت.',
                'resolution' => 'ترافیک از مسیر ترانزیتی ناپایدار جابجا شد. پس از تأیید پایداری مسیر در مکان تحت تأثیر، اتصال به حالت عادی بازگشت.',
                'next_steps' => 'در حال افزودن بررسی‌های سلامت مسیر اضافی، بازنگری آستانه‌های فیلورور با ارائه‌دهنده اپ‌استریم و مستندسازی راهنمای سریع‌تر جابجایی ترافیک برای مکان تحت تأثیر هستیم.',
                'final_status' => 'رفع شده',
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'is_published' => true,
                'published_at' => $endedAt,
                'meta_description' => 'گزارش رخداد اختلال اتصال اپ‌استریم در تاریخ ۲۵ تیرماه ۱۴۰۵ که برخی سرورهای ابری آویاتو را تحت تأثیر قرار داد.',
            ],
        );

        $events = [
            ['occurred_at' => $startedAt, 'title' => 'دریافت هشدارهای اتصال', 'description' => 'سیستم مانیتورینگ ما افزایش افت بسته و تایم‌اوت اتصال را در بخشی از مسیرهای خارجی شناسایی کرد.'],
            ['occurred_at' => $startedAt->copy()->addMinutes(16), 'title' => 'آغاز بررسی', 'description' => 'تیم شبکه تأیید کرد که مشکل به یک مسیر اپ‌استریم محدود است و هماهنگی با ارائه‌دهنده آغاز شد.'],
            ['occurred_at' => $startedAt->copy()->addMinutes(44), 'title' => 'شناسایی علت ریشه‌ای', 'description' => 'ارائه‌دهنده اپ‌استریم ناپایداری در مسیر ترانزیتی مکان تحت تأثیر را تأیید کرد.'],
            ['occurred_at' => $startedAt->copy()->addMinutes(69), 'title' => 'جابجایی ترافیک', 'description' => 'ترافیک به مسیر جایگزین منتقل شد و پایداری مسیر و دسترسی مشتریان رصد شد.'],
            ['occurred_at' => $endedAt, 'title' => 'رفع رخداد', 'description' => 'اتصال پایدار باقی ماند و مانیتورینگ به سطوح عادی بازگشت.'],
        ];

        foreach ($events as $sortOrder => $event) {
            $incident->timelineEvents()->updateOrCreate(
                ['occurred_at' => $event['occurred_at'], 'title' => $event['title']],
                $event + ['sort_order' => $sortOrder],
            );
        }
    }
}
