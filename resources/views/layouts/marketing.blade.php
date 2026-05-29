<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'آویاتو | خرید VPS ابری سریع')</title>
    <meta name="description" content="@yield('description', 'خرید VPS ابری با دیسک NVMe، IP اختصاصی، منابع شفاف، قیمت قابل پیش بینی و پشتیبانی فارسی.')">
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
    </style>
</head>
<body class="overflow-x-hidden bg-white text-slate-950 antialiased @yield('body_class')">
    @php($activePage = $activePage ?? 'home')

    <div class="min-h-screen overflow-hidden">
        <header
            x-data="{ scrolled: false }"
            x-init="scrolled = window.scrollY > 12; window.addEventListener('scroll', () => scrolled = window.scrollY > 12, { passive: true })"
            :class="scrolled ? 'h-14 bg-white/95 shadow-sm shadow-sky-100/80' : 'h-[4.5rem] bg-white/90'"
            class="fixed inset-x-0 top-0 z-50 border-b border-sky-100 backdrop-blur transition-all duration-200"
        >
            <nav class="mx-auto flex h-full max-w-7xl items-center justify-between gap-4 px-4 md:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center" aria-label="آویاتو">
                    <img src="{{ asset('assets/images/aviato_logo_full_color.png') }}" alt="آویاتو" :class="scrolled ? 'h-9 w-28' : 'h-11 w-36'" class="object-contain object-right transition-all sm:w-40">
                </a>

                <div class="hidden items-center gap-7 text-sm font-bold text-slate-600 lg:flex">
                    <a href="{{ route('home') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'home' ? 'text-[#0069FF]' : '' }}">خانه</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'pricing' ? 'text-[#0069FF]' : '' }}">قیمت‌گذاری</a>
                    <a href="{{ route('solutions') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'solutions' ? 'text-[#0069FF]' : '' }}">راهکارهای ما</a>
                    <a href="{{ route('changelog') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'changelog' ? 'text-[#0069FF]' : '' }}">لیست تغییرات</a>
                    <a href="{{ route('contact') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'contact' ? 'text-[#0069FF]' : '' }}">تماس</a>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('customer.login') }}" class="hidden px-3 py-2 text-sm font-bold text-slate-600 transition hover:text-[#0069FF] sm:inline-flex">ورود</a>
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        ثبت نام
                    </a>
                </div>
            </nav>
        </header>

        <div class="h-[4.5rem]" aria-hidden="true"></div>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-sky-100 bg-[#06152B] px-4 py-12 text-white md:px-8 lg:px-10">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_0.85fr_0.85fr]">
                <div>
                    <img src="{{ asset('assets/images/aviato_logo_full_white.png') }}" alt="آویاتو" class="h-14 w-44 object-contain object-right">
                    <p class="mt-5 max-w-xl text-sm leading-8 text-sky-100/80">
                        VPS ابری ساده، سریع و شفاف برای تیم هایی که می خواهند سرور را بدون پیچیدگی اضافه بخرند و سرویس را اجرا کنند.
                    </p>
                </div>

                <div>
                    <p class="text-sm font-black text-white">خرید و بررسی</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-sky-100/70">
                        <a href="{{ route('home') }}" class="transition hover:text-white">خانه</a>
                        <a href="{{ route('pricing') }}" class="transition hover:text-white">قیمت ها</a>
                        <a href="{{ route('solutions') }}" class="transition hover:text-white">راهکارها</a>
                        <a href="{{ route('customer.register') }}" class="transition hover:text-white">ثبت نام مشتری</a>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-black text-white">ارتباط و پنل</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-sky-100/70">
                        <a href="{{ route('customer.login') }}" class="transition hover:text-white">ورود به پنل</a>
                        <a href="{{ route('changelog') }}" class="transition hover:text-white">تغییرات</a>
                        <a href="{{ route('contact') }}" class="transition hover:text-white">تماس با ما</a>
                    </div>
                </div>
            </div>

            <div class="mx-auto mt-10 flex max-w-7xl flex-col gap-3 border-t border-white/10 pt-6 text-xs font-bold text-sky-100/50 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ now()->year }} آویاتو. همه حقوق محفوظ است.</p>
                <p>VPS شفاف برای خرید سریع، اجرای ساده و رشد قابل پیش بینی.</p>
            </div>
        </footer>
    </div>
</body>
</html>
