<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>وضعیت سرویس‌ها | آویاتو</title>
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/status.css') }}?v={{ filemtime(public_path('assets/css/status.css')) }}">
</head>
<body>
    <div class="status-shell">
        <header class="site-header">
            <a href="{{ route('home') }}" aria-label="صفحه اصلی آویاتو"><img src="{{ asset('assets/images/aviato_logo_full_black.png') }}" width="108" alt="آویاتو"></a>
            <a class="home-link" href="{{ route('home') }}">بازگشت به صفحه اصلی <span aria-hidden="true">↖</span></a>
        </header>
        <main id="main">@yield('content')</main>
        <footer class="site-footer"><span>آویاتو · وضعیت زیرساخت ابری</span><span>همه زمان‌ها به وقت تهران</span></footer>
    </div>
    @stack('scripts')
</body>
</html>
