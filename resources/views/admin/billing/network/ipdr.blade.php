@extends('layouts.admin')
@section('title', 'همگام‌سازی شبکه')
@section('content')
@php
    $time = fn ($value) => $value ? \Carbon\CarbonImmutable::parse($value, 'UTC')->setTimezone('Asia/Tehran')->format('Y/m/d H:i') : 'هنوز دریافت نشده';
    $fresh = $healthAt && $healthAt->gt(now()->subMinutes(5)) && empty($health['unavailable']);
    $inventoryReady = $fresh && !empty($health['inventory']['last_succeeded_at']) && empty($health['inventory']['has_error']) && \Carbon\CarbonImmutable::parse($health['inventory']['last_succeeded_at'])->gt(now()->subMinutes(5));
    $collectorReady = $fresh && empty($health['collector']['stale_exporters']) && !empty($health['collector']['last_seen_at']) && \Carbon\CarbonImmutable::parse($health['collector']['last_seen_at'])->gt(now()->subMinutes(5));
    $labels = ['queued'=>'در صف', 'running'=>'در حال پردازش', 'completed'=>'انجام شد', 'failed'=>'ناموفق'];
    $kinds = ['sync'=>'همگام‌سازی', 'backfill'=>'دریافت تاریخچه', 'delivery'=>'دریافت خودکار از IPDR', 'check'=>'بررسی اتصال'];
@endphp
<div class="px-4 py-6 md:px-8 lg:px-10">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div><p class="text-sm font-black text-[#0069FF]">اندازه‌گیری و مصرف شبکه</p><h1 class="mt-1 text-2xl font-black text-slate-950">همگام‌سازی شبکه</h1><p class="mt-2 text-sm text-slate-500">ماشین‌های جدید به‌صورت خودکار ثبت می‌شوند و مصرف پردازش‌شده به اویاتو می‌رسد.</p></div>
        @adminRoute('admin.billing.network.sync')
        <form method="POST" action="{{ route('admin.billing.network.sync') }}">@csrf<button id="integration-sync-button" @disabled(!$configured || $activeRun) class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50">{{ $activeRun ? 'همگام‌سازی در حال انجام است' : 'همگام‌سازی اکنون' }}</button></form>
        @endadminRoute
    </div>
    @include('admin.billing.network._nav')
    @if(session('status'))<div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if(session('error'))<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">بازه تاریخ معتبر وارد کنید؛ هر دو تاریخ لازم است و تاریخ پایان باید بعد از شروع باشد.</div>@endif
    <div id="integration-progress" data-active="{{ $activeRun ? '1' : '0' }}" aria-live="polite">
        @if(!$configured)
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><b>اتصال هنوز تنظیم نشده است</b><p class="mt-2 text-sm">آدرس و توکن IPDR را در تنظیمات سرور وارد کنید، سپس اتصال را بررسی کنید.</p></div>
        @elseif($activeRun)
            <div class="mb-5 rounded-2xl border border-[#B8D6FF] bg-[#EBF3FF] p-5 text-[#0054CC]"><b>{{ $labels[$activeRun->status] }}</b><p class="mt-2 text-sm">این صفحه وضعیت را خودکار به‌روز می‌کند. می‌توانید از صفحه خارج شوید؛ پردازش ادامه خواهد داشت.</p>
            @if($activeRun->status === 'queued' && $activeRun->created_at->lt(now()->subMinutes(2)))<p class="mt-2 text-sm">پردازش هنوز شروع نشده است. اجرای صف و زمان‌بندی سرور را بررسی کنید.</p>@endif</div>
        @elseif($latestRun?->status === 'failed')
            <div role="alert" class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-800"><b>آخرین همگام‌سازی انجام نشد</b><p class="mt-2 text-sm">اتصال را بررسی کنید و دوباره «همگام‌سازی اکنون» را بزنید.</p><details class="mt-3 text-xs"><summary class="cursor-pointer">جزئیات خطا</summary><p class="mt-2 break-words" dir="ltr">{{ $latestRun->error }}</p></details></div>
        @endif
        @if(!$deliveryEnabled || ($fresh && empty($health['delivery']['enabled'])))<div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">دریافت دوره‌ای فعال است. برای ارسال مستقیم مصرف و ثبت سریع ماشین‌ها، توکن مشترک یکپارچه‌سازی را در هر دو سرور تنظیم کنید.</div>@endif
        <div class="grid gap-4 md:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-black text-slate-500">۱ · ثبت ماشین‌ها</p><h2 class="mt-3 font-black {{ $inventoryReady ? 'text-emerald-700' : 'text-amber-700' }}">{{ $inventoryReady ? 'فهرست ماشین‌ها به‌روز است' : ($fresh ? 'ثبت ماشین‌ها نیازمند بررسی است' : 'در انتظار بررسی IPDR') }}</h2><p class="mt-2 text-xs text-slate-500">آخرین دریافت فهرست: <span dir="ltr">{{ $time($health['inventory']['last_succeeded_at'] ?? null) }}</span></p><p class="mt-2 text-xs text-slate-500">{{ number_format($health['inventory']['active_assignments'] ?? 0) }} تخصیص IP ثبت‌شده</p></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-black text-slate-500">۲ · جمع‌آوری و پردازش</p><h2 class="mt-3 font-black {{ $collectorReady ? 'text-emerald-700' : 'text-amber-700' }}">{{ $collectorReady ? 'داده شبکه در حال دریافت است' : 'داده تازه از جمع‌آورنده تأیید نشده' }}</h2><p class="mt-2 text-xs text-slate-500">آخرین داده جمع‌آورنده: <span dir="ltr">{{ $time($health['collector']['last_seen_at'] ?? null) }}</span></p><p class="mt-2 text-xs text-slate-500">مصرف نهایی IPDR تا: <span dir="ltr">{{ $time($health['usage']['finalized_through'] ?? null) }}</span></p></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-black text-slate-500">۳ · دریافت در اویاتو</p><h2 class="mt-3 font-black text-slate-950">{{ $latestUsage ? 'مصرف نهایی دریافت شده است' : 'در انتظار اولین مصرف نهایی' }}</h2><p class="mt-2 text-xs text-slate-500">مصرف دریافتی تا: <span dir="ltr">{{ $time($latestUsage) }}</span></p><p class="mt-2 text-xs {{ !empty($health['delivery']['has_error']) ? 'text-red-600' : 'text-slate-500' }}">{{ $fresh ? number_format($health['delivery']['pending'] ?? 0).' رکورد در انتظار ارسال' : 'وضعیت ارسال هنوز تأیید نشده است' }}{{ !empty($health['delivery']['has_error']) ? ' · خطا در ارسال' : '' }}</p></section>
        </div>
        <p class="mt-3 text-xs text-slate-500">همه زمان‌ها به وقت تهران است. مصرف ساعتی پس از پایان ساعت و {{ $health['usage']['finalization_delay_minutes'] ?? 10 }} دقیقه تأخیر نهایی می‌شود؛ نبود داده به معنی مصرف صفر نیست. آخرین بررسی: {{ $time($healthAt) }}.</p>
        @if(!$fresh && $configured)<p class="mt-2 text-xs text-amber-700">برای بررسی وضعیت فعلی، «همگام‌سازی اکنون» را بزنید.</p>@endif
        @if($exceptionCount)
        @adminRoute('admin.billing.network.exceptions')<a href="{{ route('admin.billing.network.exceptions') }}" class="mt-4 block rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">{{ number_format($exceptionCount) }} رکورد نیازمند بررسی · مشاهده استثناها ←</a>@endadminRoute
        @endif
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 p-5"><h2 class="font-black">فعالیت اخیر</h2><p class="mt-1 text-xs text-slate-500">دریافت داده و محاسبه هزینه در پس‌زمینه انجام می‌شود.</p></div><div class="divide-y divide-slate-100">
        @forelse($runs as $run)
            <div class="p-4"><div class="flex flex-wrap items-center justify-between gap-2 text-sm"><b>{{ $kinds[$run->kind] ?? 'همگام‌سازی' }}</b><span class="{{ $run->status === 'failed' || $run->errors ? 'text-red-700' : 'text-slate-500' }}">{{ $labels[$run->status] ?? $run->status }} · {{ $time($run->created_at) }}</span></div>
            @if($run->stats)<p class="mt-2 text-xs text-slate-500">{{ number_format($run->stats['received'] ?? 0) }} رکورد دریافت شد · {{ number_format($run->stats['changed'] ?? 0) }} رکورد جدید یا اصلاح‌شده@if($run->kind !== 'delivery') · {{ number_format($run->stats['rated'] ?? 0) }} رکورد قیمت‌گذاری شد@endif @if($run->stats['has_more'] ?? false) · دریافت باقی داده‌ها ادامه دارد@endif</p>@endif
            @if($run->errors)<details data-detail="issues-{{ $run->id }}" class="mt-2 text-xs text-red-700"><summary class="cursor-pointer">{{ count($run->errors) }} رکورد پذیرفته نشد · بررسی جزئیات</summary>@foreach($run->errors as $issue)<p class="mt-2 break-words" dir="ltr">{{ $issue['bucket_id'] ?? 'Unknown record' }}: {{ $issue['message'] }}</p>@endforeach</details>@endif
            @if($run->status === 'failed' || $run->errors)
            @adminRoute('admin.billing.network.sync')<form method="POST" action="{{ route('admin.billing.network.sync') }}" class="mt-3">@csrf<input type="hidden" name="retry_run" value="{{ $run->id }}"><button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">پردازش دوباره</button></form>@endadminRoute
            @endif
            @if($run->error)<details data-detail="error-{{ $run->id }}" class="mt-2 text-xs text-red-700"><summary class="cursor-pointer">جزئیات خطا</summary><p class="mt-2 break-words" dir="ltr">{{ $run->error }}</p></details>@endif</div>
        @empty<div class="p-8 text-center text-sm text-slate-500">هنوز همگام‌سازی انجام نشده است. برای شروع «همگام‌سازی اکنون» را بزنید.</div>@endforelse
        </div></section>
    </div>
    <section id="integration-machines" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 p-5"><h2 class="font-black">وضعیت ماشین‌ها</h2><p class="mt-1 text-xs text-slate-500">ثبت خودکار پس از تخصیص IP انجام می‌شود. تأیید ثبت با دریافت مصرف متفاوت است.</p></div><div class="overflow-x-auto"><table class="w-full text-right text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-4">ماشین</th><th class="p-4">ثبت در IPDR</th><th class="p-4">آخرین بازه مصرف دریافتی</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($machines as $machine)<tr><td class="p-4 font-bold">@adminRoute('admin.billing.network.vm')<a class="text-[#0069FF]" href="{{ route('admin.billing.network.vm', $machine) }}">{{ $machine->display_name }}</a>@else{{ $machine->display_name }}@endadminRoute<p class="mt-1 font-mono text-xs font-normal text-slate-400" dir="ltr">{{ $machine->ip_address ?: 'IP تخصیص نیافته' }}</p></td><td class="p-4">{{ $machineStates[$machine->id]['label'] }}</td><td class="p-4 text-xs">{{ $machineStates[$machine->id]['latest'] ? $time($machineStates[$machine->id]['latest']) : 'در انتظار داده؛ مصرف صفر تأیید نشده' }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-500">هنوز ماشینی ایجاد نشده است.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $machines->links() }}</div></section>
    <details class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" @if($errors->any()) open @endif><summary class="cursor-pointer font-black text-slate-700">تنظیمات اتصال و دریافت تاریخچه</summary><div class="mt-5 grid gap-6 md:grid-cols-2">
        <section><h2 class="text-sm font-bold">اتصال IPDR</h2><p class="mt-3 break-all font-mono text-sm text-slate-500" dir="ltr">{{ $baseUrl ?: 'تنظیم نشده' }}</p><p class="mt-2 text-xs text-slate-500">توکن: {{ $configured ? 'پیکربندی شده' : 'ناقص' }} · ارسال مستقیم: {{ $deliveryEnabled ? 'توکن پیکربندی شده' : 'توکن تنظیم نشده' }}</p>
        @adminRoute('admin.billing.network.ipdr.test')<form method="POST" action="{{ route('admin.billing.network.ipdr.test') }}" class="mt-4">@csrf<button @disabled(!$configured) class="rounded-xl border border-[#0069FF] px-4 py-3 text-sm font-black text-[#0069FF] disabled:opacity-50">بررسی اتصال</button></form>@endadminRoute</section>
        <section><h2 class="text-sm font-bold">دریافت مصرف تاریخی</h2><p class="mt-2 text-xs text-slate-500">زمان را به وقت تهران وارد کنید. داده تکراری هزینه دوباره ایجاد نمی‌کند؛ مصرف واجد شرایط طبق سیاست حسابداری محاسبه می‌شود.</p>
        @adminRoute('admin.billing.network.sync')<form method="POST" action="{{ route('admin.billing.network.sync') }}" class="mt-4 space-y-3">@csrf<label class="block text-xs font-bold">از تاریخ (تهران)<input required name="from" type="datetime-local" value="{{ old('from') }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2" dir="ltr"></label><label class="block text-xs font-bold">تا تاریخ (تهران)<input required name="to" type="datetime-local" value="{{ old('to') }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2" dir="ltr"></label><button id="integration-backfill-button" @disabled(!$configured || $activeRun) class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-black disabled:opacity-50">دریافت تاریخچه</button></form>@endadminRoute</section>
    </div></details>
</div>
<script>
(() => {
    let checks = 0;
    const poll = async () => {
        if (++checks > 120) return;
        if (!document.hidden) {
            try {
                const response = await fetch(window.location.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                if (!response.ok || response.redirected) return;
                const next = new DOMParser().parseFromString(await response.text(), 'text/html');
                for (const id of ['integration-progress', 'integration-machines']) {
                    const current = document.getElementById(id), updated = next.getElementById(id);
                    if (current && updated) {
                        const expanded = new Set([...current.querySelectorAll('details[open][data-detail]')].map(item => item.dataset.detail));
                        for (const detail of updated.querySelectorAll('details[data-detail]')) detail.open = expanded.has(detail.dataset.detail);
                        current.replaceWith(updated);
                    }
                }
                const button = document.getElementById('integration-sync-button'), updated = next.getElementById('integration-sync-button');
                if (button && updated) { button.disabled = updated.disabled; button.textContent = updated.textContent; }
                const backfill = document.getElementById('integration-backfill-button'), nextBackfill = next.getElementById('integration-backfill-button');
                if (backfill && nextBackfill) backfill.disabled = nextBackfill.disabled;
            } catch (_) { /* A transient refresh failure leaves the last known status visible. */ }
        }
        setTimeout(poll, document.getElementById('integration-progress')?.dataset.active === '1' ? 5000 : 30000);
    };
    setTimeout(poll, {{ $activeRun ? 5000 : 30000 }});
})();
</script>
@endsection
