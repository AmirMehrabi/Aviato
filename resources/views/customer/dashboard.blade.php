@extends('customer.layout')

@section('title', 'داشبورد مشتری')
@section('header_title', 'داشبورد VPS')
@section('header_subtitle', 'ساخت سریع ماشین ابری، کنترل هزینه و مدیریت سرورها')

@php
    $activeNav = 'dashboard';
@endphp

@section('search_data')
[
    {
        "title": "ساخت ماشین",
        "description": "انتخاب پلن VPS و شروع مسیر ساخت",
        "type": "عملیات",
        "url": @json(route('customer.servers.create', [], false)),
        "keywords": "ساخت ماشین vps server"
    },
    {
        "title": "سرورها",
        "description": "مشاهده همه ماشین های ابری",
        "type": "صفحه",
        "url": @json(route('customer.servers.index', [], false)),
        "keywords": "servers ماشین سرورها"
    }@if ($vmRows->isNotEmpty()),@endif
@foreach ($vmRows as $vm)
    {
        "title": @json($vm['name']),
        "description": @json($vm['ip'].' - '.$vm['region'].' - '.$vm['plan']),
        "type": "VM",
        "url": @json(route('customer.servers.index', [], false)),
        "keywords": @json($vm['name'].' '.$vm['ip'].' '.$vm['region'].' '.$vm['plan'].' '.$vm['status'])
    }@if (! $loop->last),@endif
@endforeach
]
@endsection

@section('content')
    <section class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_360px]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-center">
                <div>
                    <p class="text-sm font-black text-[#0069FF]">VPS Cloud</p>
                    <h2 class="mt-2 text-3xl font-black leading-tight text-slate-950">ماشین ابری بعدی را در چند دقیقه آماده کنید</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-8 text-slate-600">
                        پلن، سیستم عامل و دیتاسنتر را انتخاب کنید. هزینه به صورت PAYG از کیف پول محاسبه می شود و هر زمان خواستید می توانید منابع را ارتقا دهید.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                            ساخت ماشین
                        </a>
                        <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                            مشاهده سرورها
                        </a>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-black text-slate-950">آمادگی حساب</span>
                        <span class="rounded-md px-2 py-1 text-[11px] font-black {{ $wallet->balance < 0 || $wallet->is_locked ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $wallet->is_locked ? 'کیف پول قفل' : 'آماده ساخت' }}</span>
                    </div>
                    <p class="mt-4 text-2xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                    <p class="mt-2 text-xs font-bold leading-6 text-slate-500">موجودی کیف پول برای پرداخت لحظه ای VPS و افزونه ها استفاده می شود.</p>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="mt-4 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-black text-[#0069FF] ring-1 ring-slate-200 transition hover:bg-[#EBF3FF]">
                        افزایش اعتبار
                    </a>
                </div>
            </div>
        </div>

        <aside class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            @foreach ([
                ['label' => 'سرور فعال', 'value' => $summary['running'], 'hint' => 'در حال مصرف CPU/RAM', 'tone' => 'text-[#0069FF]'],
                ['label' => 'سرور خاموش', 'value' => $summary['stopped'], 'hint' => 'فقط دیسک و IP', 'tone' => 'text-slate-950'],
                ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage), 'hint' => 'در برداشت بعدی اعمال می شود', 'tone' => $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600'],
            ] as $metric)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                    <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 truncate text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-400">{{ $metric['hint'] }}</p>
                </div>
            @endforeach
        </aside>
    </section>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">پلن های پیشنهادی VPS</h2>
                <p class="mt-1 text-sm text-slate-500">داده نمونه برای کمک به انتخاب سریع پلن مناسب.</p>
            </div>
            <a href="{{ route('customer.servers.create', [], false) }}" class="text-sm font-black text-[#0069FF]">مشاهده همه گزینه ها</a>
        </div>
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            @foreach ([
                ['name' => 'شروع سریع', 'fit' => 'سایت و API سبک', 'cpu' => '2 vCPU', 'ram' => '4GB RAM', 'disk' => '60GB NVMe', 'price' => '۷۹۰٬۰۰۰', 'accent' => 'border-[#0069FF] bg-[#F2F8FF]'],
                ['name' => 'رشد', 'fit' => 'فروشگاه و SaaS', 'cpu' => '4 vCPU', 'ram' => '8GB RAM', 'disk' => '120GB NVMe', 'price' => '۱٬۴۹۰٬۰۰۰', 'accent' => 'border-slate-200 bg-white'],
                ['name' => 'Performance', 'fit' => 'دیتابیس و پردازش', 'cpu' => '8 vCPU', 'ram' => '16GB RAM', 'disk' => '240GB NVMe', 'price' => '۲٬۹۰۰٬۰۰۰', 'accent' => 'border-slate-200 bg-white'],
            ] as $plan)
                <article class="rounded-xl border p-4 {{ $plan['accent'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black text-slate-950">{{ $plan['name'] }}</h3>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $plan['fit'] }}</p>
                        </div>
                        <span class="rounded-md bg-white px-2 py-1 text-[11px] font-black text-[#0069FF] ring-1 ring-slate-200">VPS</span>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><span class="block font-black">{{ $plan['cpu'] }}</span></div>
                        <div class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><span class="block font-black">{{ $plan['ram'] }}</span></div>
                        <div class="rounded-lg bg-white p-2 ring-1 ring-slate-200"><span class="block font-black">{{ $plan['disk'] }}</span></div>
                    </div>
                    <p class="mt-4 text-left text-xl font-black text-slate-950">{{ $plan['price'] }} <span class="text-xs text-slate-500">/ ماه</span></p>
                    <a href="{{ route('customer.servers.create', [], false) }}" class="mt-4 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">انتخاب پلن</a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">سرورهای اخیر</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $virtualMachines->count() }} ماشین در حساب شما ثبت شده است.</p>
                </div>
                <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex w-fit justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">همه سرورها</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-right text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="px-5 py-3">ماشین</th>
                            <th class="px-5 py-3">منطقه</th>
                            <th class="px-5 py-3">پلن</th>
                            <th class="px-5 py-3">وضعیت</th>
                            <th class="px-5 py-3 text-left">هزینه ماهانه</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($vmRows->take(5) as $vm)
                            <tr class="transition hover:bg-[#F8FBFF]">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-black text-slate-950" dir="ltr">{{ $vm['name'] }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $vm['ip'] }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['region'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['plan'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black {{ $vm['statusClass'] }}">
                                        <span class="size-2 rounded-full {{ $vm['dot'] }}"></span>
                                        {{ $vm['status'] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-left font-black text-slate-950">{{ $wallets->format($vm['cost']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <p class="text-sm font-black text-slate-950">هنوز سروری ندارید.</p>
                                    <a href="{{ route('customer.servers.create', [], false) }}" class="mt-3 inline-flex rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ساخت اولین ماشین</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-base font-black text-slate-950">دیتاسنترهای آماده</h2>
                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['name' => 'تهران ۱', 'latency' => 'کمترین تاخیر داخل ایران', 'status' => 'آماده'],
                        ['name' => 'شیراز ۱', 'latency' => 'ظرفیت اقتصادی', 'status' => 'آماده'],
                        ['name' => 'فرانکفورت', 'latency' => 'اتصال بین الملل', 'status' => 'محدود'],
                    ] as $region)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $region['name'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $region['latency'] }}</p>
                            </div>
                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-black text-emerald-700">{{ $region['status'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-base font-black text-slate-950">ایمیج های محبوب</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (['Ubuntu 24.04', 'Debian 12', 'Rocky Linux 9', 'Windows Server'] as $image)
                        <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">{{ $image }}</span>
                    @endforeach
                </div>
                <div class="mt-5 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p class="text-sm font-black text-slate-950">افزونه پیشنهادی</p>
                    <p class="mt-2 text-sm leading-7 text-slate-500">بکاپ روزانه و مانیتورینگ پایه را هنگام ساخت ماشین فعال کنید.</p>
                </div>
            </div>
        </aside>
    </section>
@endsection
