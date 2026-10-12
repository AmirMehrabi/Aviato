@extends('layouts.admin')
@section('title', 'داشبورد عملیات آویاتو')
@inject('wallets', 'App\Services\WalletService')
@section('content')
    @php
        $reportRange = ['from' => $activity['from']->toDateString(), 'to' => $activity['to']->toDateString()];
        $queueParams = array_filter(['period' => $period, 'queue' => 1, 'category' => $dashboard['category'], 'warning_type' => $dashboard['warning_type'], 'hidden' => $dashboard['hidden_only'] ? 1 : null]);
        $queueItems = $expandedQueue ? $dashboard['items'] : $dashboard['groups'];
    @endphp
    <div class="mx-auto max-w-7xl px-4 py-6 md:px-8 lg:px-10">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="text-2xl font-bold text-slate-950">داشبورد مدیریت</h1><p class="mt-1 text-sm text-slate-600">نمای مالی، وضعیت عملیات و آخرین فعالیت مشتریان</p></div>
            <div class="flex flex-wrap items-center gap-3">
                <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1" aria-label="بازه گزارش داشبورد">
                    @foreach ([1 => 'امروز', 7 => '۷ روز', 30 => '۳۰ روز'] as $days => $label)
                        <a href="{{ route('admin.dashboard', array_merge($queueParams, ['period' => $days, 'queue' => $expandedQueue ? 1 : null])) }}" @if ($period === $days) aria-current="page" @endif class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold {{ $period === $days ? 'bg-[#0069FF] text-white' : 'text-slate-600 hover:bg-slate-100' }} focus-visible:outline-2 focus-visible:outline-[#0069FF]">{{ $label }}</a>
                    @endforeach
                </nav>
                <a href="{{ route('admin.dashboard', array_merge($queueParams, ['queue' => $expandedQueue ? 1 : null, 'page' => $dashboard['page']])) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:text-[#0059DB]">به‌روزرسانی</a>
                <span class="text-xs text-slate-500">{{ $refreshedAt->format('H:i') }}</span>
            </div>
        </header>
        @if (session('status'))
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="status">
                <span>{{ session('status') }}</span>
                @if (session('dismissed_warning_key'))
                    @adminRoute('admin.dashboard.warnings.restore')
                        <form method="POST" action="{{ route('admin.dashboard.warnings.restore') }}">@csrf @method('DELETE')<input type="hidden" name="warning_key" value="{{ session('dismissed_warning_key') }}"><button type="submit" class="min-h-10 px-3 font-semibold underline">واگرد</button></form>
                    @endadminRoute
                @endif
            </div>
        @endif
        @if ($errors->any())
            <p class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
        @endif
        @include('admin._dashboard-summary')
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="action-queue-heading">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div><div class="flex flex-wrap items-center gap-2"><h2 id="action-queue-heading" class="text-lg font-bold text-slate-950">{{ $dashboard['hidden_only'] ? 'موارد کنارگذاشته‌شده' : 'نیازمند توجه' }}</h2><span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ number_format($dashboard['hidden_only'] ? $dashboard['hidden_count'] : $dashboard['total_count'] - $dashboard['hidden_count']) }} مورد</span></div><p class="mt-1 text-xs text-slate-500">موارد جاری از همه بازه‌ها · ابتدا موارد مهم‌تر و قدیمی‌تر</p></div>
                <div class="flex flex-wrap items-center gap-3 text-sm font-semibold text-[#0059DB]">
                    @if ($dashboard['hidden_count'] > 0 && ! $dashboard['hidden_only'])
                        <a class="min-h-10 py-2 hover:underline" href="{{ route('admin.dashboard', array_merge($queueParams, ['hidden' => 1])) }}">کنارگذاشته‌شده‌ها ({{ number_format($dashboard['hidden_count']) }})</a>
                    @endif
                    <a class="min-h-10 py-2 hover:underline" href="{{ route('admin.dashboard', ['period' => $period, 'queue' => $expandedQueue ? null : 1]) }}">{{ $expandedQueue ? 'بازگشت به خلاصه' : 'همه موارد ←' }}</a>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3">
                @foreach ($dashboard['health'] as $health)
                    <a href="{{ route('admin.dashboard', ['period' => $period, 'category' => $health['category']]) }}" @if ($dashboard['category'] === $health['category']) aria-current="page" @endif class="rounded-lg px-3 py-2 text-xs font-semibold {{ $dashboard['category'] === $health['category'] ? 'bg-blue-50 text-[#0059DB]' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ $health['label'] }} · {{ number_format($health['count']) }}</a>
                @endforeach
                @if ($dashboard['filter_label'])<span class="text-xs text-slate-500">{{ $dashboard['filter_label'] }}</span>@endif
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($queueItems as $item)
                    @php($grouped = ! $expandedQueue && $item['group_count'] > 1)
                    <article class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $item['severity'] === 'critical' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800' }}">{{ $item['category'] }}</span><time class="text-xs text-slate-500" datetime="{{ $item['occurred_at']->toIso8601String() }}">{{ $item['age'] }}</time></div><h3 class="mt-2 break-words text-sm font-semibold text-slate-950" dir="auto">{{ $grouped ? number_format($item['group_count']).' مورد · '.$item['category'] : $item['title'] }}</h3><p class="mt-1 break-words text-xs text-slate-500" dir="auto">{{ $grouped ? 'قدیمی‌ترین: '.$item['title'].' · '.$item['meta'] : $item['meta'] }}</p></div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ $grouped ? route('admin.dashboard', ['period' => $period, 'warning_type' => $item['type'], 'queue' => 1]) : $item['url'] }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:border-[#0069FF] hover:text-[#0059DB]">{{ $grouped ? 'بررسی موارد' : $item['action'] }}</a>
                            @if (! $grouped)
                                @if ($dashboard['hidden_only'])
                                    @adminRoute('admin.dashboard.warnings.restore')
                                        <form method="POST" action="{{ route('admin.dashboard.warnings.restore') }}">@csrf @method('DELETE')<input type="hidden" name="warning_key" value="{{ $item['key'] }}"><button type="submit" class="min-h-11 rounded-xl px-3 text-sm font-semibold text-[#0059DB] hover:bg-blue-50">بازیابی</button></form>
                                    @endadminRoute
                                @else
                                    @adminRoute('admin.dashboard.warnings.dismiss')
                                        <details class="relative"><summary class="flex min-h-11 cursor-pointer items-center rounded-xl px-3 text-sm text-slate-600 hover:bg-slate-100" aria-label="گزینه‌های {{ $item['title'] }}">گزینه‌ها</summary><div class="absolute end-0 top-full z-20 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"><form method="POST" action="{{ route('admin.dashboard.warnings.dismiss') }}">@csrf<input type="hidden" name="warning_key" value="{{ $item['key'] }}"><button type="submit" name="mode" value="snooze" class="min-h-11 w-full rounded-lg px-3 text-start text-sm hover:bg-slate-50">تعویق تا فردا</button><button type="submit" name="mode" value="dismiss" class="min-h-11 w-full rounded-lg px-3 text-start text-sm hover:bg-slate-50">کنار گذاشتن این رخداد</button></form></div></details>
                                    @endadminRoute
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-8 text-center"><p class="text-sm font-semibold text-slate-900">{{ $dashboard['hidden_only'] ? 'مورد کنارگذاشته‌شده‌ای وجود ندارد.' : 'مورد قابل مشاهده‌ای نیازمند توجه نیست.' }}</p><p class="mt-1 text-xs text-slate-500">{{ $dashboard['hidden_count'] > 0 && ! $dashboard['hidden_only'] ? 'موارد کنارگذاشته‌شده را از لینک بالا بررسی کنید.' : 'آخرین فعالیت‌ها را در بخش‌های پایین ببینید.' }}</p></div>
                @endforelse
            </div>
            @if ($expandedQueue && ($dashboard['page'] > 1 || $dashboard['remaining_count'] > 0))
                <nav class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm" aria-label="صفحات موارد نیازمند توجه"><span class="text-slate-500">{{ number_format($dashboard['remaining_count']) }} مورد دیگر</span><div class="flex gap-3">@if ($dashboard['page'] > 1)<a class="min-h-10 py-2 text-[#0059DB]" href="{{ route('admin.dashboard', array_merge($queueParams, ['page' => $dashboard['page'] - 1])) }}">قبلی</a>@endif @if ($dashboard['remaining_count'] > 0)<a class="min-h-10 py-2 text-[#0059DB]" href="{{ route('admin.dashboard', array_merge($queueParams, ['page' => $dashboard['page'] + 1])) }}">بعدی</a>@endif</div></nav>
            @endif
        </section>
        @include('admin._dashboard-activity')
        @adminRoute('admin.dashboard.warnings.preferences')
            <details class="mt-6 rounded-2xl border border-slate-200 bg-white px-5 py-4"><summary class="cursor-pointer text-sm font-semibold text-slate-700">تنظیمات هشدارهای داشبورد @if (count($mutedTypes)) · {{ count($mutedTypes) }} نوع غیرفعال @endif</summary><p class="mt-3 text-xs leading-6 text-slate-500">این تنظیمات فقط برای حساب شماست. غیرفعال‌کردن یک نوع هشدار، موارد فعلی و آینده آن را از داشبورد شما حذف می‌کند؛ وضعیت سرویس یا اعلان‌های زنگ تغییر نمی‌کند.</p><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach ($warningTypes as $type => $label)<form method="POST" action="{{ route('admin.dashboard.warnings.preferences') }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2">@csrf @method('PATCH')<input type="hidden" name="warning_type" value="{{ $type }}"><input type="hidden" name="muted" value="{{ in_array($type, $mutedTypes, true) ? 0 : 1 }}"><span class="text-xs text-slate-700">{{ $label }}</span><button type="submit" class="min-h-10 rounded-lg px-3 text-xs font-semibold {{ in_array($type, $mutedTypes, true) ? 'text-[#0059DB]' : 'text-slate-600' }} hover:bg-white">{{ in_array($type, $mutedTypes, true) ? 'فعال کردن' : 'غیرفعال کردن' }}</button></form>@endforeach</div></details>
        @endadminRoute
    </div>
@endsection
