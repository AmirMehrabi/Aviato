@extends('layouts.marketing')

@section('title', 'راهکارهای آویاتو | سرور ابری، دامنه، DNS و میزبانی تجهیزات')
@section('description', 'ثبت دامنه، مدیریت DNS، سرور مجازی ابری و میزبانی تجهیزات؛ سرویس مورد نیاز پروژه خود را در آویاتو انتخاب کنید.')

@php
    $activePage = 'solutions';
    $scenarios = [
        ['title' => 'یک سایت یا فروشگاه راه می‌اندازید؟', 'body' => 'نام دامنه را انتخاب کنید، فضای اجرای سایت را آماده کنید و با رکوردهای DNS آن‌ها را به هم متصل کنید.', 'services' => ['domains', 'cloud', 'dns']],
        ['title' => 'اپلیکیشن یا API منتشر می‌کنید؟', 'body' => 'برای اجرای برنامه منابع انتخاب کنید و زیردامنه‌های سرویس را به سرور متصل کنید. با رشد مصرف، نیاز به منابع بیشتر را بررسی کنید.', 'services' => ['cloud', 'dns']],
        ['title' => 'سرور و تجهیزات خودتان را دارید؟', 'body' => 'برای انتخاب فضای رک، برق و اتصال شبکه متناسب با تجهیزات، شرایط میزبانی را با تیم ما هماهنگ کنید.', 'services' => ['colocation']],
    ];
    $faqs = [
        ['q' => 'برای شروع به همه این سرویس‌ها نیاز دارم؟', 'a' => 'خیر. سرویس را بر اساس نیاز انتخاب کنید. اگر دامنه یا سرور دارید، ممکن است فقط به مدیریت DNS نیاز داشته باشید. برای میزبانی تجهیزات خودتان نیز می‌توانید شرایط Co-location را بررسی کنید.'],
        ['q' => 'ثبت دامنه چه تفاوتی با مدیریت DNS دارد؟', 'a' => 'با ثبت دامنه، نام پروژه را برای یک دوره مشخص ثبت می‌کنید. DNS مشخص می‌کند این نام و زیردامنه‌های آن به کدام سرور، ایمیل یا سرویس متصل شوند.'],
        ['q' => 'آیا نرم‌افزارهای سرور را هم مدیریت می‌کنید؟', 'a' => 'در سرویس سرور مجازی، نصب نرم‌افزارها، به‌روزرسانی سیستم عامل، امنیت اپلیکیشن و نگهداری دیتابیس با شماست، مگر اینکه خدمات مدیریتی جداگانه توافق شده باشد.'],
        ['q' => 'هزینه و شرایط خرید را کجا ببینم؟', 'a' => 'پلن‌های سرور در صفحه قیمت‌گذاری نمایش داده می‌شوند. قیمت و پسوندهای قابل ثبت دامنه را در پنل مشتری بررسی کنید. برای شرایط میزبانی تجهیزات با ما تماس بگیرید.'],
    ];
@endphp

@section('content')
    <section class="relative overflow-hidden bg-[#F5F8FD] px-4 pb-14 pt-32 md:px-8 md:pb-20 lg:px-10">
        <div aria-hidden="true" class="pointer-events-none absolute -left-24 top-12 size-96 rounded-full bg-blue-100/70 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
            <div>
                <p class="text-sm font-bold text-[#0069FF]">راهکارهای آویاتو</p>
                <h1 class="mt-5 text-4xl font-medium leading-[1.4] text-slate-950 md:text-5xl">از دامنه تا زیرساخت،<br><span class="text-[#0069FF]">کنار پروژه شما</span></h1>
                <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 md:text-lg md:leading-9">ثبت دامنه، مدیریت DNS، سرور ابری و میزبانی تجهیزات؛ سرویس مورد نیاز پروژه‌تان را انتخاب کنید.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#services" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-6 py-3.5 text-sm font-bold text-white transition hover:bg-[#0050D0]">مشاهده سرویس‌ها ↓</a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-700 transition hover:border-blue-400">مشاوره انتخاب سرویس</a>
                </div>
            </div>
            <div class="rounded-3xl bg-[#07172D] p-6 text-white md:p-8">
                <p class="text-xs font-bold tracking-widest text-blue-300" dir="ltr">AVIATO / YOUR NEXT PROJECT</p>
                <p class="mt-6 text-xl leading-9">هر بخش از پروژه،<br>سرویس مناسب خودش را دارد.</p>
                <div class="mt-7 divide-y divide-white/10">
                    @foreach (config('solutions') as $solution)
                        <a href="{{ route($solution['route']) }}" class="group flex items-center justify-between gap-4 py-4 text-sm transition hover:text-blue-300">
                            <span>{{ $solution['label'] }}</span>
                            <span class="text-xs text-blue-200" dir="ltr">{{ $solution['eyebrow'] }} <span aria-hidden="true">↗</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="scroll-mt-28 bg-white px-4 py-16 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl leading-relaxed text-slate-950">برای قدم بعدی، انتخاب کنید.</h2>
                <p class="text-sm leading-7 text-slate-500">چهار سرویس، متناسب با نیاز پروژه شما</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach (config('solutions') as $solution)
                    <article class="flex flex-col rounded-2xl border border-slate-200 p-6 transition hover:border-blue-300 hover:shadow-lg hover:shadow-blue-100/50">
                        <div class="flex items-center justify-between gap-3">
                            <span class="grid size-12 place-items-center rounded-xl bg-blue-50 text-[#0069FF]">
                                <svg aria-hidden="true" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    @switch($solution['key'])
                                        @case('cloud')
                                            <rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01M12 7h5M12 18h5"/>
                                            @break
                                        @case('domains')
                                            <circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>
                                            @break
                                        @case('dns')
                                            <rect x="8" y="2" width="8" height="5" rx="1"/><rect x="2" y="17" width="7" height="5" rx="1"/><rect x="15" y="17" width="7" height="5" rx="1"/><path d="M12 7v5M5.5 17v-5h13v5"/>
                                            @break
                                        @default
                                            <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M5 8h14M5 15h14M9 5h.01M9 11h.01M9 18h.01"/>
                                    @endswitch
                                </svg>
                            </span>
                            <span class="text-xs text-slate-400" dir="ltr">{{ $solution['eyebrow'] }}</span>
                        </div>
                        <h3 class="mt-6 text-xl text-slate-950">{{ $solution['label'] }}</h3>
                        <p class="mb-7 mt-3 text-sm leading-7 text-slate-600">{{ $solution['body'] }}</p>
                        <a href="{{ route($solution['route']) }}" class="mt-auto inline-flex items-center gap-2 text-sm font-bold text-[#0069FF] hover:underline">{{ $solution['cta'] }} <span aria-hidden="true">←</span></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F5F8FD] px-4 py-16 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <h2 class="text-3xl leading-relaxed text-slate-950">چه چیزی می‌سازید؟</h2>
            <p class="mt-3 leading-8 text-slate-600">یک نقطه شروع برای پیدا کردن سرویس‌های مورد نیازتان.</p>
            <div class="mt-8 grid gap-8 lg:grid-cols-3">
                @foreach ($scenarios as $scenario)
                    <article class="border-t-2 border-blue-200 pt-6">
                        <h3 class="text-xl leading-8 text-slate-950">{{ $scenario['title'] }}</h3>
                        <p class="mt-3 text-sm leading-8 text-slate-600">{{ $scenario['body'] }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            @foreach ($scenario['services'] as $key)
                                <a href="{{ route(config('solutions.'.$key.'.route')) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-[#0069FF] hover:border-blue-400">{{ config('solutions.'.$key.'.label') }} ←</a>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-b border-slate-100 bg-white px-4 py-10 md:px-8">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-8 md:flex-row">
            <p class="text-sm font-bold text-slate-600">همراه پروژه‌هایی که هر روز در حال اجرا هستند</p>
            <div class="flex flex-wrap items-center justify-center gap-10">
                @foreach ([['مبیت', 'https://mobit.ir', 'mobit-logo.svg'], ['حسابرو', 'https://hesabro.ir', 'hesabro-logo.png'], ['کارخانه نوآوری کرمان', 'https://kermanif.ir', 'kermanif-logo.png']] as [$name, $url, $logo])
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"><img src="{{ asset('assets/images/customers/'.$logo) }}" alt="{{ $name }}" class="h-12 w-28 object-contain" loading="lazy"></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-16 md:px-8">
        <div class="mx-auto max-w-3xl">
            <h2 class="mb-8 text-3xl leading-relaxed text-slate-950">پیش از شروع</h2>
            <div class="divide-y divide-slate-200">
                @foreach ($faqs as $faq)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-base font-bold leading-8 text-slate-800">{{ $faq['q'] }}<span aria-hidden="true" class="text-xl text-[#0069FF] group-open:rotate-45">+</span></summary>
                        <p class="mt-3 text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#07172D] px-4 py-14 text-white md:px-8">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 md:flex-row md:items-center">
            <div><h2 class="text-2xl leading-relaxed md:text-3xl">برای انتخاب سرویس همراهتان هستیم.</h2><p class="mt-3 leading-8 text-slate-300">از نیاز پروژه بگویید تا مسیر شروع را با هم بررسی کنیم.</p></div>
            <a href="{{ route('contact') }}" class="shrink-0 rounded-xl bg-white px-6 py-4 text-sm font-bold text-[#07172D] hover:bg-blue-50">صحبت با تیم آویاتو ←</a>
        </div>
    </section>
@endsection
