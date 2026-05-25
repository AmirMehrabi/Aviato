@extends('layouts.marketing')

@section('title', 'قیمت گذاری Droplet و VPS | آویاتو')
@section('description', 'قیمت پلن های فعال Droplet آویاتو بر اساس باندل های واقعی دیتابیس؛ CPU، RAM، NVMe، IP، نرخ ماهانه و نرخ ساعتی.')

@php
    $activePage = 'pricing';
    $pricingBundles = ($bundles ?? collect())->values();
    $featuredIndex = $pricingBundles->count() > 1 ? 1 : 0;
    $meta = [
        ['badge' => 'برای شروع سریع', 'fit' => 'وب سایت، لندینگ، MVP و محیط تست'],
        ['badge' => 'پیشنهاد تیم محصول', 'fit' => 'SaaS، فروشگاه، API و production متوسط'],
        ['badge' => 'برای عملیات پایدار', 'fit' => 'دیتابیس، worker، صف و سرویس پرترافیک'],
    ];
@endphp

@section('content')
    <section class="relative isolate overflow-hidden bg-white px-4 pb-16 pt-16 md:px-8 md:pb-20 md:pt-24 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[76%] bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_88%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black text-[#0069FF]">قیمت گذاری واقعی از دیتابیس</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight text-slate-950 md:text-6xl">پلن Droplet را شفاف انتخاب کنید، بدون مذاکره و حدس.</h1>
                <p class="mt-6 max-w-3xl text-lg leading-9 text-slate-600">این قیمت ها از باندل های فعال VM خوانده می شوند. هر پلن منابع مشخص دارد، نرخ ماهانه و ساعتی آن قابل مشاهده است و مسیر خرید مستقیم به ساخت حساب مشتری می رسد.</p>
            </div>
            <div class="rounded-2xl border border-[#B8D6FF] bg-white p-6 shadow-xl shadow-[#0080FF]/10">
                <p class="text-sm font-black text-[#0069FF]">راهنمای سریع انتخاب</p>
                <div class="mt-5 space-y-4 text-sm leading-7 text-slate-600">
                    <p><span class="font-black text-slate-950">شروع:</span> کمترین هزینه برای تست، سایت سبک و MVP.</p>
                    <p><span class="font-black text-slate-950">رشد:</span> انتخاب مناسب برای بیشتر پروژه های production.</p>
                    <p><span class="font-black text-slate-950">مقیاس:</span> منابع بیشتر برای دیتابیس، worker و ترافیک سنگین.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-5 lg:grid-cols-3">
                @forelse ($pricingBundles as $bundle)
                    @php($copy = $meta[$loop->index] ?? ['badge' => 'پلن آماده', 'fit' => 'سرویس های سفارشی و بارهای کاری جدی'])
                    <article class="relative rounded-2xl border p-6 shadow-sm {{ $loop->index === $featuredIndex ? 'border-[#0080FF] bg-[#EAF4FF] shadow-xl shadow-[#0080FF]/10' : 'border-slate-200 bg-white' }}">
                        @if ($loop->index === $featuredIndex)
                            <span class="absolute left-5 top-5 rounded-md bg-[#0069FF] px-3 py-1 text-xs font-black text-white">پیشنهادی</span>
                        @endif
                        <p class="text-sm font-black text-[#0069FF]">{{ $copy['badge'] }}</p>
                        <h2 class="mt-4 text-3xl font-black text-slate-950">{{ $bundle->name }}</h2>
                        <p class="mt-3 min-h-14 text-sm leading-7 text-slate-600">{{ $bundle->description ?: $copy['fit'] }}</p>
                        <div class="mt-6">
                            <p class="text-4xl font-black text-slate-950">{{ $wallets->format($bundle->monthly_price) }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-500">ماهانه</p>
                            <p class="mt-2 text-xs font-bold text-slate-500" dir="ltr">{{ number_format((float) $bundle->hourly_price, 2) }} / hour</p>
                        </div>
                        <div class="mt-7 grid grid-cols-2 gap-2 text-center text-xs font-black text-slate-700">
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $bundle->cpu_cores }} vCPU</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $bundle->ram_gb }}GB RAM</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $bundle->disk_gb }}GB NVMe</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $bundle->ip_count }} IP</span>
                        </div>
                        <a href="{{ route('customer.register') }}" class="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">انتخاب و ساخت Droplet</a>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center lg:col-span-3">
                        <h2 class="text-2xl font-black text-slate-950">فعلا پلن فعالی برای نمایش وجود ندارد.</h2>
                        <p class="mx-auto mt-3 max-w-2xl text-sm leading-8 text-slate-600">برای نمایش قیمت های عمومی، در پنل مدیریت حداقل یک VM Bundle را فعال کنید. این صفحه بعد از انتشار باندل ها خودکار به روز می شود.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($pricingBundles->isNotEmpty())
        <section class="bg-[#06162E] px-4 py-20 text-white md:px-8 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#8FC7FF]">مقایسه سریع منابع</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">همه چیز قبل از پرداخت مشخص است</h2>
                    </div>
                    <p class="max-w-xl text-sm leading-7 text-slate-300">هزینه خاموش یا منابع سفارشی ممکن است بر اساس سیاست های billing متفاوت باشد؛ این جدول قیمت پایه باندل فعال را نشان می دهد.</p>
                </div>
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10 text-sm">
                            <thead class="bg-white/[0.03] text-right text-xs font-black text-slate-300">
                                <tr>
                                    <th class="whitespace-nowrap px-5 py-4">پلن</th>
                                    <th class="whitespace-nowrap px-5 py-4">CPU</th>
                                    <th class="whitespace-nowrap px-5 py-4">RAM</th>
                                    <th class="whitespace-nowrap px-5 py-4">NVMe</th>
                                    <th class="whitespace-nowrap px-5 py-4">IP</th>
                                    <th class="whitespace-nowrap px-5 py-4">ماهانه</th>
                                    <th class="whitespace-nowrap px-5 py-4">ساعتی</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @foreach ($pricingBundles as $bundle)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-4 font-black text-white">{{ $bundle->name }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $bundle->cpu_cores }} vCPU</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $bundle->ram_gb }}GB</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $bundle->disk_gb }}GB</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $bundle->ip_count }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 font-black text-white">{{ $wallets->format($bundle->monthly_price) }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-300" dir="ltr">{{ number_format((float) $bundle->hourly_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="bg-white px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-5 md:grid-cols-3">
            @foreach ([
                ['title' => 'پرداخت با کیف پول', 'body' => 'حساب را شارژ می کنید و مصرف سرورها از کیف پول مشتری قابل پیگیری است.'],
                ['title' => 'ارتقا هنگام رشد', 'body' => 'اگر پروژه بزرگ تر شد، سراغ باندل قوی تر بروید و منابع بیشتری بگیرید.'],
                ['title' => 'شروع بدون قرارداد', 'body' => 'پلن های آماده برای خرید سریع طراحی شده اند؛ برای نیاز خاص می توانید با فروش صحبت کنید.'],
            ] as $item)
                <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xl font-black text-slate-950">{{ $item['title'] }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600">{{ $item['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-[#EAF4FF] px-4 pb-20 pt-2 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#0069FF] p-8 text-white shadow-2xl shadow-[#0069FF]/20 md:p-12">
            <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="text-3xl font-black leading-tight md:text-5xl">پلن مناسب را پیدا کردید؟</h2>
                    <p class="mt-4 max-w-2xl leading-8 text-blue-50">حساب بسازید و Droplet را از پنل مشتری با همین منابع آماده کنید.</p>
                </div>
                <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#0069FF] shadow-xl transition hover:bg-blue-50">شروع خرید</a>
            </div>
        </div>
    </section>
@endsection
