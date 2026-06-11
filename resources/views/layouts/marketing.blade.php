<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'آویاتو | خرید ماشین مجازی ابری سریع')</title>
    <meta name="description" content="@yield('description', 'خرید ماشین مجازی ابری با دیسک NVMe، IP اختصاصی، منابع شفاف، قیمت قابل پیش بینی و پشتیبانی فارسی.')">
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">

    <meta name="theme-color" content="#0B6BFF">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 5rem;
        }

        .marketing-main>section:first-child {
            padding-top: 6.5rem;
        }

        @media (min-width: 768px) {
            .marketing-main>section:first-child {
                padding-top: 7rem;
            }
        }
    </style>
</head>

<body class="overflow-x-hidden bg-white text-slate-950 antialiased @yield('body_class')">
    @php
        $activePage = $activePage ?? 'home';
        $darkHeaderTop = in_array($activePage, ['home', 'solutions'], true);
        $marketingNavItems = [
            ['label' => 'خانه', 'route' => 'home', 'key' => 'home'],
            ['label' => 'قیمت‌گذاری', 'route' => 'pricing', 'key' => 'pricing'],
            ['label' => 'راهکارهای ما', 'route' => 'solutions', 'key' => 'solutions'],
            ['label' => 'لیست تغییرات', 'route' => 'changelog', 'key' => 'changelog'],
            ['label' => 'تماس', 'route' => 'contact', 'key' => 'contact'],
        ];
    @endphp

    <div class="min-h-screen overflow-hidden">
        <header x-data="{ scrolled: false }" x-init="scrolled = window.scrollY > 12;
        window.addEventListener('scroll', () => scrolled = window.scrollY > 12, { passive: true })"
            :class="scrolled ? 'h-14 border-slate-200/80 bg-white/95 shadow-lg shadow-slate-950/5' :
                'h-[4.75rem] border-transparent bg-transparent'"
            class="fixed inset-x-0 top-0 z-50 border-b border-transparent backdrop-blur transition-all duration-300">
            <nav class="mx-auto flex h-full max-w-7xl items-center justify-between gap-4 px-4 md:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center" aria-label="آویاتو">
                    <span class="relative block" :class="scrolled ? 'h-9 w-28 sm:w-40' : 'h-11 w-36 sm:w-40'">
                        <img src="{{ asset('assets/images/aviato_logo_full_color.png') }}" alt="آویاتو"
                            x-show="scrolled || ! {{ $darkHeaderTop ? 'true' : 'false' }}"
                            :class="scrolled ? 'h-9 w-28' : 'h-11 w-36'"
                            class="absolute inset-0 object-contain object-right transition-all sm:w-40">
                        @if ($darkHeaderTop)
                            <img src="{{ asset('assets/images/aviato_logo_full_white.png') }}" alt="آویاتو"
                                x-show="! scrolled" :class="scrolled ? 'h-9 w-28' : 'h-11 w-36'"
                                class="absolute inset-0 object-contain object-right transition-all sm:w-40">
                        @endif
                    </span>
                </a>

                <div :class="scrolled ? 'bg-slate-50/95 ring-slate-200/80 shadow-sm shadow-slate-950/5' :
                    '{{ $darkHeaderTop ? 'bg-white/10 ring-white/10' : 'bg-white/65 ring-sky-100/80 shadow-sm shadow-sky-100/60' }}'"
                    class="hidden items-center gap-1 rounded-full p-1 text-sm  ring-1 {{ $darkHeaderTop ? 'bg-white/10 ring-white/10' : 'bg-white/65 ring-sky-100/80 shadow-sm shadow-sky-100/60' }} backdrop-blur transition-all duration-300 lg:flex">
                    @foreach ($marketingNavItems as $item)
                        @php($isActive = $activePage === $item['key'])
                        <a href="{{ route($item['route']) }}"
                            @if (!$isActive) :class="scrolled ? 'text-slate-600 hover:bg-white hover:text-[#0069FF]' : '{{ $darkHeaderTop ? 'text-white/80 hover:bg-white/10 hover:text-white' : 'text-slate-600 hover:bg-white/80 hover:text-[#0069FF]' }}'" @endif
                            class="relative rounded-full px-4 py-2 transition {{ $isActive ? 'bg-[#0069FF] text-white shadow-sm shadow-[#0069FF]/25' : '' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('customer.login') }}"
                        :class="scrolled ?
                            '!border-slate-200 !bg-white !text-slate-700 hover:!border-[#B8D6FF] hover:!bg-[#EBF3FF] hover:!text-[#0069FF]' :
                            '{{ $darkHeaderTop ? 'border-white/20 bg-white/10 text-white hover:bg-white/15' : 'border-slate-200 bg-white/80 text-slate-700 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]' }}'"
                        class="hidden items-center justify-center rounded-lg border {{ $darkHeaderTop ? 'border-white/20 bg-white/10 text-white' : 'border-slate-200 bg-white/80 text-slate-700' }} px-4 py-2 text-sm  shadow-sm transition sm:inline-flex">
                        ورود
                    </a>
                    <a href="{{ route('customer.register') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm  text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        ثبت نام مشتری
                    </a>
                </div>
            </nav>
        </header>

        <main class="marketing-main">
            @yield('content')
        </main>

        <footer class="border-t border-sky-100 bg-[#06152B] px-4 py-12 text-white md:px-8 lg:px-10">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_0.85fr_0.85fr]">
                <div>
                    <img src="{{ asset('assets/images/aviato_logo_full_white.png') }}" alt="آویاتو"
                        class="h-14 w-44 object-contain object-right">
                    <p class="mt-5 max-w-xl text-sm leading-8 text-sky-100/80">
                        ماشین مجازی ابری ساده، سریع و شفاف برای تیم هایی که می خواهند سرور را بدون پیچیدگی اضافه بخرند و
                        سرویس را اجرا کنند.
                    </p>
                    <a referrerpolicy='origin' target='_blank'
                        href='https://trustseal.enamad.ir/?id=741993&Code=nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'
                        class="mt-6 inline-flex"><img referrerpolicy='origin'
                            src='https://trustseal.enamad.ir/logo.aspx?id=741993&Code=nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'
                            alt='' style='cursor:pointer' code='nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'></a>
                </div>

                <div>
                    <p class="text-sm  text-white">خرید و بررسی</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-sky-100/70">
                        <a href="{{ route('home') }}" class="transition hover:text-white">خانه</a>
                        <a href="{{ route('pricing') }}" class="transition hover:text-white">قیمت‌گذاری</a>
                        <a href="{{ route('solutions') }}" class="transition hover:text-white">راهکارهای ما</a>
                        <a href="{{ route('customer.register') }}" class="transition hover:text-white">ثبت نام
                            مشتری</a>
                    </div>
                </div>

                <div>
                    <p class="text-sm  text-white">ارتباط و پنل</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-sky-100/70">
                        <a href="{{ route('customer.login') }}" class="transition hover:text-white">ورود به پنل</a>
                        <a href="{{ route('changelog') }}" class="transition hover:text-white">تغییرات</a>
                        <a href="{{ route('contact') }}" class="transition hover:text-white">تماس با ما</a>
                    </div>
                </div>
            </div>

            <div
                class="mx-auto mt-10 flex max-w-7xl flex-col gap-3 border-t border-white/10 pt-6 text-xs font-bold text-sky-100/50 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ now()->year }} آویاتو. همه حقوق محفوظ است.</p>
                <p>ماشین مجازی شفاف برای خرید سریع، اجرای ساده و رشد قابل پیش بینی.</p>
            </div>
        </footer>
    </div>
</body>

</html>
