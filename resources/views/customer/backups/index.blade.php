@extends('customer.layout')

@section('title', 'بکاپ ها')
@section('header_title', 'بکاپ ها')
@section('header_subtitle', 'بکاپ دستی، زمان‌بندی خودکار و نگهداری نسخه‌های هر ماشین مجازی')

@php
    $activeNav = 'backups';
@endphp

@section('search_data')
[
    {"title":"بکاپ ها","description":"مدیریت بکاپ ماشین های مجازی","type":"صفحه","url":@json(route('customer.backups.index', [], false)),"keywords":"backup بکاپ schedule retention"}
]
@endsection

@section('content')
    <section class="grid gap-3 md:grid-cols-3">
        @php
            $allBackups = $vms->flatMap->backups;
            $totalBytes = $allBackups->where('status', 'ready')->sum('size_bytes');
        @endphp
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">بکاپ‌های آماده</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ $allBackups->where('status', 'ready')->count() }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">فضای بکاپ</p>
            <p class="mt-2 text-2xl font-black text-slate-950" dir="ltr">{{ number_format($totalBytes / 1024 / 1024 / 1024, 2) }} GB</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">قیمت بکاپ</p>
            <p class="mt-2 text-2xl font-black text-[#0069FF]">{{ $backupRate ? $wallets->format($backupRate->monthly_price) : '—' }} <span class="text-xs text-slate-500">/ GB ماهانه</span></p>
        </article>
    </section>

    <section class="mt-5 space-y-5">
        @forelse ($vms as $vm)
            @php
                $policy = $vm->backupPolicy;
                $running = $vm->backups->whereIn('status', ['queued', 'running'])->isNotEmpty();
                $readyBackups = $vm->backups->where('status', 'ready');
            @endphp
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="font-black text-slate-950" dir="ltr">{{ $vm->display_name }}</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $vm->ip_address ?: 'no-ip' }}</p>
                    </div>
                    <form method="POST" action="{{ route('customer.backups.manual.store', $vm, false) }}">
                        @csrf
                        <button @disabled($running || $vm->provisioning_status !== 'ready') class="rounded-lg px-4 py-2.5 text-sm font-black transition {{ $running || $vm->provisioning_status !== 'ready' ? 'cursor-not-allowed bg-slate-200 text-slate-500' : 'bg-[#0069FF] text-white hover:bg-[#0050D0]' }}">
                            بکاپ دستی
                        </button>
                    </form>
                </div>

                <div class="grid gap-0 lg:grid-cols-[360px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('customer.backups.policy.update', $vm, false) }}" class="border-b border-slate-200 p-5 lg:border-b-0 lg:border-l">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-950">بکاپ خودکار</p>
                                <p class="mt-1 text-xs leading-6 text-slate-500">بکاپ‌ها در زمان تعیین‌شده به‌صورت خودکار ایجاد می‌شوند.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-black text-slate-700">
                                <input type="checkbox" name="is_enabled" value="1" @checked($policy?->is_enabled) class="size-4 rounded border-slate-300 text-[#0069FF]">
                                فعال
                            </label>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <label>
                                <span class="text-xs font-black text-slate-500">دوره</span>
                                <select name="frequency" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                                    <option value="daily" @selected(($policy?->frequency ?? 'daily') === 'daily')>روزانه</option>
                                    <option value="weekly" @selected(($policy?->frequency ?? 'daily') === 'weekly')>هفتگی</option>
                                </select>
                            </label>
                            <label>
                                <span class="text-xs font-black text-slate-500">ساعت اجرا</span>
                                <input name="preferred_time" type="time" value="{{ old('preferred_time', $policy?->preferred_time ?: '02:00') }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                            </label>
                            <label>
                                <span class="text-xs font-black text-slate-500">تعداد نگهداری</span>
                                <input name="retention_count" type="number" min="1" max="30" value="{{ old('retention_count', $policy?->retention_count ?: 3) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                            </label>
                        </div>
                        <button class="mt-5 w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-black text-white">ذخیره برنامه</button>
                        @if($policy?->next_run_at)
                            <p class="mt-3 text-xs font-bold text-slate-500">اجرای بعدی: <span dir="ltr">{{ $policy->next_run_at->format('Y-m-d H:i') }}</span></p>
                        @endif
                    </form>

                    <div class="p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-black text-slate-950">آخرین بکاپ‌ها</h3>
                            <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">{{ $readyBackups->count() }} آماده</span>
                        </div>
                        <div class="mt-4 divide-y divide-slate-100">
                            @forelse ($vm->backups as $backup)
                                <div class="flex items-center justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-black text-slate-900">نسخه پشتیبان · {{ $backup->created_at->format('Y/m/d H:i') }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-500">{{ $backup->source === 'policy' ? 'خودکار' : 'دستی' }} · {{ $backup->created_at->format('Y/m/d H:i') }} · {{ number_format($backup->sizeGb(), 2) }}GB</p>
                                        @if($backup->error)<p class="mt-1 text-xs text-red-600">ایجاد این نسخه کامل نشد. برای بررسی بیشتر با پشتیبانی تماس بگیرید.</p>@endif
                                    </div>
                                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $backup->status === 'ready' ? 'bg-emerald-50 text-emerald-700' : ($backup->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-[#0069FF]') }}">{{ $backup->status === 'ready' ? 'آماده' : ($backup->status === 'failed' ? 'ناموفق' : 'در حال ایجاد') }}</span>
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">هنوز بکاپی برای این ماشین ثبت نشده است.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="font-black text-slate-950">برای استفاده از بکاپ ابتدا ماشین مجازی بسازید.</p>
                <a href="{{ route('customer.servers.create', [], false) }}" class="mt-4 inline-flex rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ساخت ماشین مجازی</a>
            </div>
        @endforelse
    </section>
@endsection
