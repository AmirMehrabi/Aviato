<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پنل مشتریان</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3efe6] p-6 text-slate-950">
    <main class="mx-auto max-w-4xl rounded-[2rem] bg-white p-8 shadow-2xl shadow-[#0A3D37]/10">
        <h1 class="text-3xl font-black">داشبورد مشتریان</h1>
        <p class="mt-3 text-slate-600">این صفحه با گارد customer محافظت شده است.</p>
        <form method="POST" action="{{ route('customer.logout') }}" class="mt-8">@csrf <button class="rounded-2xl bg-[#0A3D37] px-5 py-3 font-black text-white">خروج</button></form>
    </main>
</body>
</html>
