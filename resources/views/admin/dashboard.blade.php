<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پنل مدیران</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A3D37] p-6 text-white">
    <main class="mx-auto max-w-4xl rounded-[2rem] bg-white/10 p-8 ring-1 ring-white/15">
        <h1 class="text-3xl font-black">داشبورد مدیران</h1>
        <p class="mt-3 text-emerald-50/80">این صفحه با گارد admin محافظت شده است.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-8">@csrf <button class="rounded-2xl bg-white px-5 py-3 font-black text-[#0A3D37]">خروج</button></form>
    </main>
</body>
</html>
