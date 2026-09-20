@extends('customer.layout')

@section('title', 'بکاپ ها')
@section('header_title', 'بکاپ ها')
@section('header_subtitle', 'از اطلاعات ماشین‌های مجازی خود محافظت کنید؛ محل ذخیره‌سازی بکاپ‌ها به‌صورت خودکار مدیریت می‌شود.')

@php
    $activeNav = 'backups';
@endphp

@section('search_data')
[
    {"title":"بکاپ ها","description":"مدیریت بکاپ ماشین های مجازی","type":"صفحه","url":@json(route('customer.backups.index', [], false)),"keywords":"backup بکاپ schedule retention"}
]
@endsection

@section('content')
    <section class="rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 text-right">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black text-slate-950">فضای بکاپ، بدون نیاز به تنظیمات</h2>
                <p class="mt-1 text-sm font-bold leading-6 text-slate-600">نسخه‌های پشتیبان در فضای بکاپ مدیریت‌شده ذخیره می‌شوند. فقط زمان‌بندی و تعداد نسخه‌هایی را که می‌خواهید نگه دارید انتخاب کنید.</p>
            </div>
            <span class="w-fit rounded-full bg-white px-3 py-1.5 text-xs font-black text-[#0069FF]">ذخیره‌سازی خودکار</span>
        </div>
    </section>

    <section class="mt-4 grid gap-3 md:grid-cols-3">
        @php
            $allBackups = $vms->flatMap->backups;
            $totalBytes = $allBackups->where('status', 'ready')->sum('size_bytes');
        @endphp
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">بکاپ‌های آماده</p>
            <p class="mt-2 text-2xl font-black text-slate-950">{{ $allBackups->where('status', 'ready')->count() }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">فضای محافظت‌شده</p>
            <p class="mt-2 text-2xl font-black text-slate-950" dir="ltr">{{ number_format($totalBytes / 1024 / 1024 / 1024, 2) }} GB</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">هزینه ماهانه ذخیره‌سازی</p>
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
                <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-black text-slate-950" dir="ltr">{{ $vm->display_name }}</h2>
                            <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $policy?->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $policy?->is_enabled ? 'محافظت خودکار فعال' : 'بکاپ خودکار غیرفعال' }}</span>
                        </div>
                        <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $vm->ip_address ?: 'no-ip' }}</p>
                        @if($policy?->is_enabled && $policy?->next_run_at)
                            <p class="mt-2 text-xs font-bold text-slate-600">بکاپ بعدی: <span dir="ltr">{{ \App\Support\Jalali::format($policy->next_run_at) }}</span></p>
                        @else
                            <p class="mt-2 text-xs font-bold text-slate-500">برای محافظت پیوسته، بکاپ خودکار را فعال کنید.</p>
                        @endif
                    </div>
                    <div class="lg:text-left">
                        <form method="POST" action="{{ route('customer.backups.manual.store', $vm, false) }}">
                            @csrf
                            <button @disabled($running || $vm->provisioning_status !== 'ready') class="rounded-lg px-4 py-2.5 text-sm font-black transition {{ $running || $vm->provisioning_status !== 'ready' ? 'cursor-not-allowed bg-slate-200 text-slate-500' : 'bg-[#0069FF] text-white hover:bg-[#0050D0]' }}">
                                {{ $running ? 'بکاپ در حال ایجاد است' : 'ایجاد بکاپ الآن' }}
                            </button>
                        </form>
                        <p class="mt-2 max-w-xs text-xs font-bold leading-5 text-slate-500">یک نسخه پشتیبان از این ماشین ایجاد می‌شود و در فضای مدیریت‌شده ذخیره خواهد شد.</p>
                    </div>
                </div>

                <div class="grid gap-0 lg:grid-cols-[390px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('customer.backups.policy.update', $vm, false) }}" class="border-b border-slate-200 bg-slate-50/70 p-5 lg:border-b-0 lg:border-l">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-950">بکاپ خودکار</p>
                                <p class="mt-1 text-xs leading-6 text-slate-500">بکاپ‌ها طبق برنامه شما ایجاد می‌شوند و فقط آخرین نسخه‌ها نگه داشته خواهند شد.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-black text-slate-700">
                                <input type="checkbox" name="is_enabled" value="1" @checked($policy?->is_enabled) class="size-4 rounded border-slate-300 text-[#0069FF]">
                                فعال
                            </label>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <label>
                                <span class="text-xs font-black text-slate-500">هر چند وقت یک‌بار؟</span>
                                <select name="frequency" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                                    <option value="daily" @selected(($policy?->frequency ?? 'daily') === 'daily')>روزانه</option>
                                    <option value="weekly" @selected(($policy?->frequency ?? 'daily') === 'weekly')>هفتگی</option>
                                </select>
                            </label>
                            <label>
                                <span class="text-xs font-black text-slate-500">ساعت ایجاد بکاپ</span>
                                <input name="preferred_time" type="time" value="{{ old('preferred_time', $policy?->preferred_time ?: '02:00') }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                            </label>
                            <label>
                                <span class="text-xs font-black text-slate-500">تعداد نسخه‌های قابل نگهداری</span>
                                <input name="retention_count" type="number" min="1" max="30" value="{{ old('retention_count', $policy?->retention_count ?: 3) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
                                <span class="mt-1 block text-xs font-bold leading-5 text-slate-500">نسخه‌های قدیمی‌تر به‌صورت خودکار حذف می‌شوند.</span>
                            </label>
                        </div>
                        <button class="mt-5 w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-black text-white">ذخیره تنظیمات بکاپ</button>
                    </form>

                    <div class="p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-black text-slate-950">نسخه‌های پشتیبان</h3>
                                <p class="mt-1 text-xs font-bold text-slate-500">آخرین نسخه‌های ایجادشده برای این ماشین</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">{{ $readyBackups->count() }} آماده</span>
                        </div>
                        <div class="mt-4 divide-y divide-slate-100">
                            @forelse ($vm->backups as $backup)
                                <div class="flex items-center justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-black text-slate-900">{{ $backup->source === 'policy' ? 'بکاپ خودکار' : 'بکاپ دستی' }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-500"><span dir="ltr">{{ \App\Support\Jalali::format($backup->created_at) }}</span> · <span dir="ltr">{{ number_format($backup->sizeGb(), 2) }} GB</span></p>
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
