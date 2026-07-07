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
            overflow-x: hidden;
            max-width: 100%;
        }

        body,
        .marketing-main,
        .marketing-main>section {
            max-width: 100%;
            overflow-x: hidden;
        }

        [x-cloak] {
            display: none !important;
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
        $darkHeaderTop = in_array($activePage, ['solutions'], true);
        $marketingNavItems = config('marketing.navigation', []);
    @endphp

    <div class="min-h-screen overflow-hidden" x-data="{ scrolled: false, menuOpen: false }" x-init="scrolled = window.scrollY > 12;
        window.addEventListener('scroll', () => scrolled = window.scrollY > 12, { passive: true })"
        @keydown.escape.window="menuOpen = false">
        <header
            :class="scrolled ? 'h-14 border-slate-200/80 bg-white/95 shadow-lg shadow-slate-950/5' :
                'h-[4.75rem] border-transparent bg-transparent'"
            class="fixed inset-x-0 top-0 z-50 border-b border-transparent backdrop-blur transition-all duration-300">
            <nav class="mx-auto flex h-full max-w-7xl items-center justify-between gap-4 px-4 md:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center" aria-label="آویاتو">
                    <span class="relative block h-10 w-24 sm:w-40" :class="scrolled ? 'h-9 w-20 sm:w-40' : 'h-10 w-24 sm:w-40'">
                        <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو"
                            x-show="scrolled || ! {{ $darkHeaderTop ? 'true' : 'false' }}"
                            :class="scrolled ? 'h-9 w-20' : 'h-10 w-24'"
                            class="absolute inset-0 h-10 w-24 object-contain object-right transition-all sm:w-40">
                        @if ($darkHeaderTop)
                            <img src="{{ asset('assets/images/aviato_logo_full_white.webp') }}" alt="آویاتو"
                                x-show="! scrolled" :class="scrolled ? 'h-9 w-20' : 'h-10 w-24'"
                                class="absolute inset-0 h-10 w-24 object-contain object-right transition-all sm:w-40">
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

                <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                    <a href="{{ route('customer.login') }}"
                        :class="scrolled ?
                            '!border-slate-200 !bg-white !text-slate-700 hover:!border-[#B8D6FF] hover:!bg-[#EBF3FF] hover:!text-[#0069FF]' :
                            '{{ $darkHeaderTop ? 'border-white/20 bg-white/10 text-white hover:bg-white/15' : 'border-slate-200 bg-white/80 text-slate-700 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]' }}'"
                        class="hidden items-center justify-center rounded-lg border {{ $darkHeaderTop ? 'border-white/20 bg-white/10 text-white' : 'border-slate-200 bg-white/80 text-slate-700' }} px-3 py-2 text-xs shadow-sm transition sm:inline-flex sm:px-4 sm:py-2 sm:text-sm">
                        ورود
                    </a>
                    <a href="{{ route('customer.register') }}"
                        class="hidden items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:inline-flex sm:px-4 sm:py-2 sm:text-sm">
                        ثبت نام مشتری
                    </a>
                    <button type="button" @click="menuOpen = true"
                        :class="scrolled ?
                            'border-slate-200 bg-white text-slate-700 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]' :
                            '{{ $darkHeaderTop ? 'border-white/20 bg-white/10 text-white hover:bg-white/15' : 'border-slate-200 bg-white/80 text-slate-700 hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]' }}'"
                        class="inline-flex size-10 items-center justify-center rounded-lg border shadow-sm transition lg:hidden"
                        aria-label="باز کردن منو" :aria-expanded="menuOpen.toString()">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </nav>

        </header>

        <div x-cloak x-show="menuOpen" class="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true">
            <div x-show="menuOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/30 backdrop-blur-sm" @click="menuOpen = false"></div>
            <aside x-show="menuOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-full opacity-0"
                class="absolute right-0 top-0 flex h-dvh w-[min(21rem,88vw)] flex-col border-l border-slate-200 bg-white p-5 shadow-2xl shadow-slate-950/15">
                <div class="flex items-center justify-between gap-4">
                    <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو"
                        class="h-10 w-28 object-contain object-right">
                    <button type="button" @click="menuOpen = false" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50" aria-label="بستن منو">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="mt-8 grid gap-2">
                    @foreach ($marketingNavItems as $item)
                        @php($isActive = $activePage === $item['key'])
                        <a href="{{ route($item['route']) }}" @click="menuOpen = false"
                            class="rounded-xl px-4 py-3 text-sm font-bold transition {{ $isActive ? 'bg-[#EEF5FF] text-[#0069FF]' : 'text-slate-700 hover:bg-slate-50 hover:text-[#0069FF]' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="mt-auto grid gap-3 border-t border-slate-200 pt-5">
                    <a href="{{ route('customer.login') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                        ورود به پنل
                    </a>
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#0050D0]">
                        ثبت نام مشتری
                    </a>
                </div>
            </aside>
        </div>

        <main class="marketing-main">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-[#F5F8FD] px-4 py-12 text-slate-700 md:px-8 lg:px-10">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_0.85fr_0.85fr]">
                <div>
                    <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو"
                        class="h-14 w-44 object-contain object-right">
                    <p class="mt-5 max-w-xl text-sm leading-8 text-slate-600">
                        ماشین مجازی ابری ساده، روشن و قابل پیش بینی برای تیم هایی که می خواهند سرور را بدون پیچیدگی اضافه بخرند و
                        سرویس را اجرا کنند.
                    </p>
                    <a referrerpolicy='origin' target='_blank'
                        href='https://trustseal.enamad.ir/?id=741993&Code=nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'
                        class="mt-6 hidden max-w-full sm:inline-flex"><img referrerpolicy='origin'
                            src='https://trustseal.enamad.ir/logo.aspx?id=741993&Code=nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'
                            alt='' class="h-auto max-w-full" style='cursor:pointer' code='nS8E7FstzvRwYUnf48e4uvEM0kHHqTGU'></a>
                </div>

                <div>
                    <p class="text-sm text-slate-950">خرید و بررسی</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-slate-600">
                        <a href="{{ route('home') }}" class="transition hover:text-[#2C67C9]">خانه</a>
                        <a href="{{ route('pricing') }}" class="transition hover:text-[#2C67C9]">قیمت‌گذاری</a>
                        <a href="{{ route('solutions') }}" class="transition hover:text-[#2C67C9]">راهکارهای ما</a>
                        <a href="{{ route('blog') }}" class="transition hover:text-[#2C67C9]">بلاگ</a>
                        <a href="{{ route('customer.register') }}" class="transition hover:text-[#2C67C9]">ثبت نام
                            مشتری</a>
                    </div>
                </div>

                <div>
                    <p class="text-sm text-slate-950">ارتباط و پنل</p>
                    <div class="mt-4 grid gap-3 text-sm font-bold text-slate-600">
                        <a href="{{ route('customer.login') }}" class="transition hover:text-[#2C67C9]">ورود به پنل</a>
                        <a href="{{ route('changelog') }}" class="transition hover:text-[#2C67C9]">تغییرات</a>
                        <a href="{{ route('contact') }}" class="transition hover:text-[#2C67C9]">تماس با ما</a>
                    </div>
                </div>
            </div>

            <div
                class="mx-auto mt-10 flex max-w-7xl flex-col gap-3 border-t border-slate-200 pt-6 text-xs font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ now()->year }} آویاتو. همه حقوق محفوظ است.</p>
                <p>ماشین مجازی روشن برای خرید سریع، اجرای ساده و رشد قابل پیش بینی.</p>
            </div>
        </footer>
    </div>
</body>

</html>
