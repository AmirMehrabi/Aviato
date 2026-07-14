<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'مستندات API | آویاتو')</title>
    <meta name="description" content="@yield('description', 'مستندات API آویاتو برای ساخت و مدیریت ماشین‌های مجازی ابری.')">
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
        [x-cloak] { display: none !important; }
        .api-docs-code ol { counter-reset: line; list-style: none; margin: 0; padding: 0; }
        .api-docs-code li { counter-increment: line; display: flex; min-height: 1.75rem; }
        .api-docs-code li::before { content: counter(line); width: 2.75rem; flex: 0 0 2.75rem; padding-right: .75rem; color: #64748b; text-align: right; user-select: none; }
        .api-docs-code li > span { white-space: pre; }
    </style>
</head>
<body class="min-h-screen bg-[#f8fafc] text-slate-950 antialiased">
    <header class="sticky top-0 z-50 border-b border-slate-200/90 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 w-full max-w-[1600px] items-center justify-between gap-6 px-5 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="آویاتو">
                <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو" class="h-9 w-28 object-contain object-right">
                <span class="hidden border-r border-slate-200 pr-3 text-xs font-black text-slate-500 sm:inline">API reference</span>
            </a>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700 sm:inline-flex">v1 · Stable</span>
                <a href="{{ route('customer.login') }}" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">ورود به پنل</a>
            </div>
        </div>
    </header>
    <main class="w-full">@yield('content')</main>
</body>
</html>
