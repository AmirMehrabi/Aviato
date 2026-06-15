<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>خطای سرور | آویاتو</title>
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">
    <meta name="theme-color" content="#0B6BFF">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen overflow-x-hidden bg-[#F5F8FD] text-slate-950 antialiased">
    <header class="fixed inset-x-0 top-0 z-50 h-[4.75rem] border-b border-transparent bg-white/95 shadow-lg shadow-slate-950/5 backdrop-blur transition-all duration-300">
        <nav class="mx-auto flex h-full max-w-7xl items-center justify-center gap-4 px-4 md:px-8 lg:px-10">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center" aria-label="آویاتو">
                <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو"
                    class="h-9 w-20 object-contain object-right">
            </a>
            {{-- <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                <a href="{{ route('home') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white/80 px-3 py-2 text-xs text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:inline-flex sm:px-4 sm:py-2 sm:text-sm">
                    خانه
                </a>
            </div> --}}
        </nav>
    </header>

    <main class="flex min-h-screen items-center justify-center px-4 pt-24 pb-16">
        <div class="mx-auto max-w-2xl text-center">
            <div class="relative">
                <p class="text-[8rem] font-black leading-none text-red-50 select-none sm:text-[12rem]">۵۰۰</p>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-5xl font-black text-red-500 sm:text-7xl">۵۰۰</span>
                </div>
            </div>

            <h1 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">خطایی در سرور رخ داده است</h1>
            <p class="mx-auto mt-4 max-w-md text-sm leading-8 text-slate-600">
                مشکلی در سمت سرور پیش آمده که فعلا از دسترس خارج است. تیم فنی در حال بررسی است و به زودی رفع می‌شود.
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('home') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                    بازگشت به خانه
                </a>
                <button onclick="location.reload()"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white/75 px-7 py-3.5 text-sm font-bold text-slate-700 shadow-sm backdrop-blur transition hover:border-[#B8D6FF] hover:bg-white hover:text-[#0069FF]">
                    تلاش مجدد
                </button>
            </div>
        </div>
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
            </div>
            <div>
                <p class="text-sm text-slate-950">خرید و بررسی</p>
                <div class="mt-4 grid gap-3 text-sm font-bold text-slate-600">
                    <a href="{{ route('home') }}" class="transition hover:text-[#2C67C9]">خانه</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-[#2C67C9]">قیمت‌گذاری</a>
                    <a href="{{ route('solutions') }}" class="transition hover:text-[#2C67C9]">راهکارهای ما</a>
                    <a href="{{ route('blog') }}" class="transition hover:text-[#2C67C9]">بلاگ</a>
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
        <div class="mx-auto mt-10 flex max-w-7xl flex-col gap-3 border-t border-slate-200 pt-6 text-xs font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} آویاتو. همه حقوق محفوظ است.</p>
            <p>ماشین مجازی روشن برای خرید سریع، اجرای ساده و رشد قابل پیش بینی.</p>
        </div>
    </footer>
</body>

</html>
