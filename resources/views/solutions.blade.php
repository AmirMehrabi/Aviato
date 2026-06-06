@extends('layouts.marketing')

@section('title', 'آویاتو: راهکارهای ما')
@section('description', 'راهنمای ساده آویاتو برای انتخاب ماشین مجازی مناسب سایت، فروشگاه، اپلیکیشن، دیتابیس و محیط تست.')

@php
    $activePage = 'solutions';
    $solutionBundles = ($bundles ?? collect())->values();
    $starterBundle = $solutionBundles->first();
    $growthBundle = $solutionBundles->get(1) ?? $starterBundle;
    $scaleBundle = $solutionBundles->get(2) ?? $growthBundle;

    $solutions = [
        [
            'name' => 'میزبانی وب و فروشگاه',
            'summary' => 'برای وردپرس، فروشگاه اینترنتی، سایت شرکتی و پروژه هایی که سرعت و دسترسی پایدار برایشان مهم است.',
            'bundle' => $growthBundle,
            'best' => 'سایت، فروشگاه و پنل مشتریان',
            'stack' => 'وب سرور، دیتابیس، کش و ابزارهای مورد نیاز سایت',
            'items' => ['دیسک NVMe برای سرعت بهتر سایت', 'IP اختصاصی برای دامنه و SSL', 'امکان ساخت محیط جدا برای تست تغییرات'],
        ],
        [
            'name' => 'اپلیکیشن و API',
            'summary' => 'برای تیم هایی که یک سرویس آنلاین، اپلیکیشن یا API دارند و می خواهند منابع مشخص و قابل پیگیری داشته باشند.',
            'bundle' => $growthBundle,
            'best' => 'اپلیکیشن، API و پنل مدیریتی',
            'stack' => 'Docker، دیتابیس، پردازش پس زمینه و اتصال امن',
            'items' => ['منابع مشخص برای اجرای سرویس', 'امکان جدا کردن اپلیکیشن و دیتابیس', 'انتخاب پلن قوی تر هنگام رشد ترافیک'],
        ],
        [
            'name' => 'دیتابیس و پردازش',
            'summary' => 'برای پروژه هایی که دیتابیس، حافظه و کارهای پس زمینه روی سرعت و کیفیت سرویس اثر زیادی دارند.',
            'bundle' => $scaleBundle,
            'best' => 'دیتابیس، کش، صف و پردازش های روزانه',
            'stack' => 'سرور مجازی، بکاپ، مانیتورینگ و فایروال',
            'items' => ['دیسک NVMe برای پاسخ سریع تر دیتابیس', 'بکاپ و IP اختصاصی برای اطمینان بیشتر', 'مناسب برای جدا کردن پردازش ها از سایت اصلی'],
        ],
        [
            'name' => 'محیط توسعه و تست',
            'summary' => 'برای تیم هایی که می خواهند قبل از اعمال تغییرات روی سایت اصلی، یک محیط جدا برای تست داشته باشند.',
            'bundle' => $starterBundle,
            'best' => 'تست، نمونه، تمرین و بررسی نسخه جدید',
            'stack' => 'ایمیج آماده، SSH، قالب سرور و پرداخت از کیف پول',
            'items' => ['ساخت سریع محیط جدید', 'هزینه قابل کنترل برای کارهای موقت', 'دسترسی کامل برای نصب و تست ابزارها'],
        ],
    ];
@endphp

@section('content')
    <section class="relative isolate overflow-hidden bg-[#06162E] px-4 pb-20 pt-16 text-white md:px-8 md:pb-24 md:pt-24 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full bg-[radial-gradient(circle_at_top_right,rgba(0,128,255,0.28),transparent_34%),linear-gradient(180deg,#071B3A_0%,#06162E_72%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#8FC7FF]">کاربردهای ماشین مجازی</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight md:text-6xl">سرور مجازی را بر اساس نیاز خود انتخاب کنید.</h1>
                <p class="mt-6 max-w-3xl text-lg leading-9 text-slate-300">اگر نمی دانید از کدام پلن شروع کنید، این صفحه چند کاربرد رایج را نشان می دهد: سایت، فروشگاه، اپلیکیشن، دیتابیس یا محیط تست.</p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0080FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0080FF]/20 transition hover:bg-[#0069FF]">دیدن پلن ها</a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 px-7 py-4 text-base font-black text-white transition hover:bg-white/10">کمک برای انتخاب</a>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[0.05] p-6 shadow-2xl shadow-slate-950/20">
                <p class="text-sm font-black text-[#8FC7FF]">راهنمای سریع انتخاب</p>
                <div class="mt-5 space-y-3">
                    @foreach ([['شروع سبک', $starterBundle?->name ?? 'پلن پایه'], ['سایت یا فروشگاه فعال', $growthBundle?->name ?? 'پلن رشد'], ['دیتابیس و پردازش', $scaleBundle?->name ?? 'پلن قوی تر']] as $row)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-white/10 px-4 py-3">
                            <span class="text-sm text-slate-300">{{ $row[0] }}</span>
                            <span class="font-black">{{ $row[1] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 text-sm leading-7 text-slate-300">این پیشنهادها بر اساس پلن های فعال شما نمایش داده می شوند و با تغییر پلن ها به روز می شوند.</p>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="mb-10 max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">سناریوهای پیشنهادی</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">برای هر نیاز، یک نقطه شروع ساده</h2>
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
                                    <p class="text-xs font-black text-slate-500">ابزارهای رایج</p>
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
                                    <p class="mt-3 text-sm leading-7 text-slate-300">بعد از فعال شدن پلن ها در پنل مدیریت، پیشنهاد این بخش نمایش داده می شود.</p>
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
                            <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">شروع خرید</a>
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
                <p class="text-sm font-black text-[#0069FF]">چرا این راهنما کمک می کند؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">انتخاب سرور وقتی راحت تر است که کاربرد آن روشن باشد.</h2>
                <p class="mt-5 leading-8 text-slate-600">به جای مقایسه کردن همه جزئیات فنی، می توانید اول ببینید سرور را برای چه کاری می خواهید و بعد پلن مناسب را انتخاب کنید.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['title' => 'انتخاب ساده تر', 'body' => 'برای هر کاربرد یک پیشنهاد اولیه دارید و لازم نیست همه جزئیات فنی را از اول بررسی کنید.'],
                    ['title' => 'اطمینان بیشتر', 'body' => 'می بینید هر قابلیت مثل NVMe، IP اختصاصی، بکاپ و فایروال در چه کاری به شما کمک می کند.'],
                    ['title' => 'مسیر خرید کوتاه تر', 'body' => 'از هر بخش می توانید مستقیم به ثبت نام یا مقایسه پلن ها بروید.'],
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
                    <h2 class="mt-3 max-w-4xl text-3xl font-black leading-tight md:text-5xl">اگر مطمئن نیستید کدام پلن مناسب شماست، با ما صحبت کنید.</h2>
                    <p class="mt-4 max-w-3xl leading-8 text-slate-300">برای جدا کردن سایت و دیتابیس، تنظیم بکاپ، انتخاب پلن یا انتقال از سرور قبلی، قبل از خرید راهنمایی تان می کنیم.</p>
                </div>
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#07172D] transition hover:bg-blue-50">تماس با ما</a>
            </div>
        </div>
    </section>
@endsection
