<!DOCTYPE html>
<html lang="fa" dir="rtl" class="website-font">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'آویاتو')</title>
    <meta name="description" content="@yield('description', 'اخبار، تجربه‌ها و وضعیت سرویس‌های آویاتو.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title')))">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('description')))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="Aviato">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicons/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/public.css') }}?v={{ filemtime(public_path('assets/css/public.css')) }}">
    @stack('styles')
</head>
<body>
    <a href="#main" class="public-skip">رفتن به محتوای صفحه</a>
    <div class="public-shell">
        <header class="site-header">
            <a href="{{ route('home') }}" aria-label="صفحه اصلی آویاتو"><img src="{{ asset($publicLogo ?? 'assets/images/aviato_logo_full_color.webp') }}" width="108" alt="آویاتو"></a>
            <a class="home-link" href="{{ route('home') }}">بازگشت به صفحه اصلی <span aria-hidden="true">↖</span></a>
        </header>
        <main id="main" class="@yield('main_class')" tabindex="-1">@yield('content')</main>
        <footer class="site-footer">
            <span>آویاتو · زیرساخت ابری</span>
            <nav aria-label="پیوندهای پایین صفحه">
                <a href="{{ route('blog') }}">بلاگ</a>
                <a href="{{ route('status') }}">وضعیت سرویس‌ها</a>
                <a href="{{ route('contact') }}">تماس با ما</a>
            </nav>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
