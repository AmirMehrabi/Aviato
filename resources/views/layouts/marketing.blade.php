<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'آویاتو | سرور ابری در کمتر از یک دقیقه')</title>
    <meta name="description" content="@yield('description', 'ماشین مجازی سریع با دیسک NVMe، IP اختصاصی، بکاپ روزانه و پشتیبانی فارسی. بدون قرارداد، پرداخت ساعتی.')">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 5rem;
        }
    </style>
</head>
<body class="overflow-x-hidden bg-[#F5F7FB] text-slate-950 @yield('body_class')">
    @php($activePage = $activePage ?? 'home')

    <div class="min-h-screen overflow-hidden">
        <header
            x-data="{ scrolled: false }"
            x-init="scrolled = window.scrollY > 16; window.addEventListener('scroll', () => scrolled = window.scrollY > 16, { passive: true })"
            :class="scrolled ? 'h-12 shadow-md shadow-slate-200/60' : 'h-16'"
            class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/90 backdrop-blur transition-all duration-200"
        >
            <nav class="mx-auto flex h-full max-w-7xl items-center justify-between gap-4 px-4 md:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="آویاتو">
                    <span :class="scrolled ? 'size-8' : 'size-9'" class="grid place-items-center rounded-lg bg-[#0069FF] text-sm font-black text-white transition-all">آ</span>
                    <span :class="scrolled ? 'text-sm' : 'text-base'" class="font-black transition-all">آویاتو</span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-bold text-slate-600 lg:flex">
                    <a href="{{ route('home') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'home' ? 'text-[#0069FF]' : '' }}">خانه</a>
                    <a href="{{ route('solutions') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'solutions' ? 'text-[#0069FF]' : '' }}">راهکارها</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'pricing' ? 'text-[#0069FF]' : '' }}">قیمت گذاری</a>
                    <a href="{{ route('changelog') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'changelog' ? 'text-[#0069FF]' : '' }}">تغییرات</a>
                    <a href="{{ route('contact') }}" class="transition hover:text-[#0069FF] {{ $activePage === 'contact' ? 'text-[#0069FF]' : '' }}">تماس با ما</a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('customer.login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:text-[#0069FF] sm:inline-flex">ورود</a>
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-[#0050D0]">
                        ثبت نام
                        <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </nav>
        </header>

        <div class="h-16" aria-hidden="true"></div>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-8 md:px-8 lg:px-10">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="font-black text-slate-900">آویاتو</p>
                    <p class="mt-1">زیرساخت ابری ساده، سریع و قابل اتکا برای تیم های در حال رشد.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4 font-bold">
                    <a href="{{ route('home') }}" class="transition hover:text-[#0069FF]">خانه</a>
                    <a href="{{ route('solutions') }}" class="transition hover:text-[#0069FF]">راهکارها</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-[#0069FF]">قیمت گذاری</a>
                    <a href="{{ route('changelog') }}" class="transition hover:text-[#0069FF]">تغییرات</a>
                    <a href="{{ route('contact') }}" class="transition hover:text-[#0069FF]">تماس با ما</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
