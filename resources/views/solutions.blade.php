@extends('layouts.marketing')

@section('title', 'راهکارهای سرور ابری و Droplet | آویاتو')
@section('description', 'راهکارهای عملی آویاتو برای میزبانی وب، SaaS، فروشگاه، دیتابیس، worker و محیط توسعه با Dropletهای NVMe و پشتیبانی فارسی.')

@php
    $activePage = 'solutions';
    $solutionBundles = ($bundles ?? collect())->values();
    $starterBundle = $solutionBundles->first();
    $growthBundle = $solutionBundles->get(1) ?? $starterBundle;
    $scaleBundle = $solutionBundles->get(2) ?? $growthBundle;

    $solutions = [
        [
            'name' => 'میزبانی وب و فروشگاه',
            'summary' => 'برای WordPress، WooCommerce، Laravel و فروشگاه اختصاصی که سرعت صفحه و تحویل سریع برایشان مهم است.',
            'bundle' => $growthBundle,
            'best' => 'سایت تجاری، فروشگاه، landing و پنل مشتریان',
            'stack' => 'Nginx، PHP-FPM، Redis، MySQL یا PostgreSQL',
            'items' => ['NVMe برای پاسخ سریع دیتابیس و cache', 'IP اختصاصی برای DNS و SSL', 'امکان جداسازی staging و production'],
        ],
        [
            'name' => 'SaaS و API production',
            'summary' => 'برای تیم هایی که محصول آنلاین، API، queue worker و releaseهای منظم دارند.',
            'bundle' => $growthBundle,
            'best' => 'B2B SaaS، API عمومی، پنل مدیریتی',
            'stack' => 'Docker، Queue Worker، PostgreSQL، Private Network',
            'items' => ['منابع قابل پیش بینی برای releaseهای روزانه', 'شبکه خصوصی بین app و دیتابیس', 'ارتقا به پلن بالاتر هنگام رشد ترافیک'],
        ],
        [
            'name' => 'دیتابیس و Worker',
            'summary' => 'برای سرویس هایی که I/O، RAM و پایداری پردازش پس زمینه روی کیفیت تجربه کاربر اثر مستقیم دارد.',
            'bundle' => $scaleBundle,
            'best' => 'PostgreSQL، MySQL، Redis، queue و پردازش batch',
            'stack' => 'Dedicated VM، Backup Policy، Monitoring، Firewall',
            'items' => ['دیسک NVMe برای query و jobهای سنگین', 'بکاپ و IP اختصاصی برای سناریوهای عملیاتی', 'مناسب جداسازی worker از app اصلی'],
        ],
        [
            'name' => 'محیط توسعه و تست',
            'summary' => 'برای تیم هایی که به sandbox، staging، demo server و تست release نیاز دارند.',
            'bundle' => $starterBundle,
            'best' => 'تیم توسعه، QA، demo و preview environment',
            'stack' => 'Cloud Image، SSH Key، Template VM، Wallet Billing',
            'items' => ['ساخت سریع محیط جدید از ایمیج آماده', 'هزینه کنترل شده برای محیط های موقت', 'دسترسی root برای تست دقیق production-like'],
        ],
    ];
@endphp

@section('content')
    <section class="relative isolate overflow-hidden bg-[#06162E] px-4 pb-20 pt-16 text-white md:px-8 md:pb-24 md:pt-24 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full bg-[radial-gradient(circle_at_top_right,rgba(0,128,255,0.28),transparent_34%),linear-gradient(180deg,#071B3A_0%,#06162E_72%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#8FC7FF]">راهکارها</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight md:text-6xl">Droplet را بر اساس کاری که می خواهید اجرا کنید انتخاب کنید.</h1>
                <p class="mt-6 max-w-3xl text-lg leading-9 text-slate-300">اگر نمی دانید از کدام پلن شروع کنید، این صفحه مسیر انتخاب را از use case واقعی شروع می کند: فروشگاه، SaaS، دیتابیس، worker یا محیط توسعه.</p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0080FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0080FF]/20 transition hover:bg-[#0069FF]">دیدن پلن ها</a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 px-7 py-4 text-base font-black text-white transition hover:bg-white/10">مشاوره انتخاب</a>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[0.05] p-6 shadow-2xl shadow-slate-950/20">
                <p class="text-sm font-black text-[#8FC7FF]">نقشه تصمیم خرید</p>
                <div class="mt-5 space-y-3">
                    @foreach ([['شروع سبک', $starterBundle?->name ?? 'پلن پایه'], ['ترافیک production', $growthBundle?->name ?? 'پلن رشد'], ['دیتابیس و worker', $scaleBundle?->name ?? 'پلن قوی تر']] as $row)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-white/10 px-4 py-3">
                            <span class="text-sm text-slate-300">{{ $row[0] }}</span>
                            <span class="font-black">{{ $row[1] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 text-sm leading-7 text-slate-300">پیشنهادها از باندل های فعال VM خوانده می شوند و با تغییر پلن ها در مدیریت، این راهنما هم به روز می ماند.</p>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="mb-10 max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">سناریوهای پیشنهادی</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">برای هر بار کاری، یک نقطه شروع روشن</h2>
            </div>
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($solutions as $solution)
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#B8D6FF] hover:shadow-xl hover:shadow-slate-200/70">
                        <div class="grid gap-6 xl:grid-cols-[1fr_260px]">
                            <div>
                                <p class="text-sm font-black text-[#0069FF]">{{ $solution['best'] }}</p>
                                <h3 class="mt-3 text-2xl font-black text-slate-950">{{ $solution['name'] }}</h3>
                                <p class="mt-4 text-sm leading-8 text-slate-600">{{ $solution['summary'] }}</p>
                                <div class="mt-5 rounded-xl bg-[#F7FBFF] p-4">
                                    <p class="text-xs font-black text-slate-500">پشته پیشنهادی</p>
                                    <p class="mt-2 text-sm font-bold leading-7 text-slate-700">{{ $solution['stack'] }}</p>
                                </div>
                            </div>
                            <aside class="rounded-xl bg-[#06162E] p-5 text-white">
                                <p class="text-xs font-black text-[#8FC7FF]">پلن پیشنهادی</p>
                                <h4 class="mt-2 text-2xl font-black">{{ $solution['bundle']?->name ?? 'بعد از انتشار پلن' }}</h4>
                                @if ($solution['bundle'])
                                    <p class="mt-3 text-xl font-black">{{ $wallets->format($solution['bundle']->monthly_price) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">ماهانه</p>
                                    <div class="mt-5 grid grid-cols-2 gap-2 text-center text-xs font-black">
                                        <span class="rounded-md bg-white/10 p-2">{{ $solution['bundle']->cpu_cores }} CPU</span>
                                        <span class="rounded-md bg-white/10 p-2">{{ $solution['bundle']->ram_gb }}GB RAM</span>
                                        <span class="rounded-md bg-white/10 p-2">{{ $solution['bundle']->disk_gb }}GB NVMe</span>
                                        <span class="rounded-md bg-white/10 p-2">{{ $solution['bundle']->ip_count }} IP</span>
                                    </div>
                                @else
                                    <p class="mt-3 text-sm leading-7 text-slate-300">بعد از فعال شدن باندل ها در پنل مدیریت، پیشنهاد پلن این بخش نمایش داده می شود.</p>
                                @endif
                            </aside>
                        </div>
                        <div class="mt-6 grid gap-3 md:grid-cols-3">
                            @foreach ($solution['items'] as $item)
                                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm font-bold leading-7 text-slate-700">
                                    {{ $item }}
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">شروع با این مسیر</a>
                            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:border-[#0080FF] hover:text-[#0069FF]">مقایسه پلن ها</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#0069FF]">چرا این ساختار بهتر می فروشد؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">مشتری پلن نمی خرد؛ نتیجه قابل اجرا می خرد.</h2>
                <p class="mt-5 leading-8 text-slate-600">راهکارها کمک می کنند مشتری خودش را در یکی از سناریوها ببیند، بعد با اطمینان به صفحه قیمت گذاری یا ثبت نام برسد.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['title' => 'انتخاب ساده تر', 'body' => 'هر سناریو یک پلن شروع دارد و مشتری لازم نیست همه جزئیات فنی را خودش تفسیر کند.'],
                    ['title' => 'اعتماد بیشتر', 'body' => 'به جای وعده کلی، اجزای عملی مثل NVMe، IP، بکاپ و شبکه خصوصی را در زمینه واقعی توضیح می دهد.'],
                    ['title' => 'مسیر خرید مستقیم', 'body' => 'هر کارت به ثبت نام یا مقایسه قیمت وصل است تا کاربر در صفحه گیر نکند.'],
                ] as $item)
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-black text-slate-950">{{ $item['title'] }}</h3>
                        <p class="mt-3 text-sm leading-8 text-slate-600">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#07172D] px-4 py-20 text-white md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-black text-[#8FC7FF]">نیاز خاص دارید؟</p>
                    <h2 class="mt-3 max-w-4xl text-3xl font-black leading-tight md:text-5xl">اگر معماری شما از یک Droplet ساده بزرگ تر است، از همین جا گفتگو را شروع کنید.</h2>
                    <p class="mt-4 max-w-3xl leading-8 text-slate-300">برای جداسازی اپ و دیتابیس، طراحی شبکه خصوصی، سیاست بکاپ یا migration از سرور قبلی، مسیر پیشنهادی را قبل از خرید مشخص می کنیم.</p>
                </div>
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#07172D] transition hover:bg-blue-50">درخواست مشاوره</a>
            </div>
        </div>
    </section>
@endsection
