<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'آویاتو | خرید ماشین مجازی ابری سریع')</title>
    <meta name="description" content="@yield('description', 'خرید ماشین مجازی ابری با دیسک NVMe، IP اختصاصی، منابع شفاف، قیمت قابل پیش بینی و پشتیبانی فارسی.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title'))) ">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('description'))) ">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="Aviato">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">

    <meta name="theme-color" content="#0B6BFF">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="journal">
    <a href="#main" class="journal-skip">رفتن به نوشته‌ها</a>
    <header class="journal-shell journal-header">
        <a href="{{ route('blog') }}" class="journal-brand" aria-label="نوشته‌های آویاتو">
            <img src="{{ asset('assets/images/aviato_logo_full_color.webp') }}" alt="آویاتو" width="100" height="40">
            <span>یادداشت‌ها و تجربه‌ها</span>
        </a>
        <a href="{{ route('home') }}" class="journal-home">وب‌سایت آویاتو <span aria-hidden="true">↗︎</span></a>
    </header>
    <main id="main" class="journal-shell" tabindex="-1">
        @yield('content')
    </main>
    <footer class="journal-shell journal-footer">
        <p>از تیم آویاتو، درباره چیزهایی که می‌سازیم و یاد می‌گیریم.</p>
        <nav aria-label="پیوندهای پایین صفحه">
            <a href="{{ route('blog') }}">همه نوشته‌ها</a>
            <a href="{{ route('contact') }}">تماس با ما</a>
            <a href="{{ route('home') }}">آویاتو</a>
        </nav>
    </footer>
</body>
</html>
