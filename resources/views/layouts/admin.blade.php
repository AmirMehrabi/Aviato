<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'داشبورد آویاتو')</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="overflow-x-hidden bg-[#F7F8FA] text-slate-950">
    <div
        x-data="{ sidebarOpen: false, createOpen: false, period: 'روزانه' }"
        class="min-h-screen overflow-x-hidden lg:flex"
    >
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-30 bg-slate-950/35 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="fixed inset-y-0 right-0 z-40 hidden w-72 translate-x-full flex-col border-l border-white/10 bg-[#0A3D37] px-5 py-5 text-white shadow-2xl shadow-[#0A3D37]/30 transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-[#105D52] text-lg font-black text-white shadow-lg shadow-[#105D52]/25">آ</span>
                    <span>
                        <span class="block text-lg font-black text-white">آویاتو</span>
                        <span class="block text-xs text-emerald-50/60">پنل مدیریت سرورها</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-lg border border-white/10 text-emerald-50/80 transition hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="بستن منو"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="mt-8 space-y-1 text-sm font-semibold">
                @php
                    $navItems = [
                        ['label' => 'داشبورد', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
                        ['label' => 'مشتریان', 'route' => 'admin.customers.index', 'active' => request()->routeIs('admin.customers.*'), 'icon' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0M19 8v6m3-3h-6'],
                        ['label' => 'Proxmox', 'route' => 'admin.proxmox-servers.index', 'active' => request()->routeIs('admin.proxmox-servers.*'), 'icon' => 'M5 6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4H5V6Zm0 4h14v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-8Zm3 4h2m4 0h2M8 17h8'],
                        ['label' => 'ماشین‌ها', 'route' => 'admin.virtual-machines.index', 'active' => request()->routeIs('admin.virtual-machines.*'), 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                        ['label' => 'تصاویر و بکاپ', 'route' => null, 'active' => false, 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                        ['label' => 'شبکه و فایروال', 'route' => null, 'active' => false, 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4M4.9 16.9l2.8-2.8m8.6-8.6 2.8-2.8'],
                        ['label' => 'قیمت منابع', 'route' => 'admin.billing.rates.index', 'active' => request()->routeIs('admin.billing.rates.*'), 'icon' => 'M7 4h10v16H7V4Zm3 4h4m-4 4h4m-4 4h2'],
                        ['label' => 'باندل‌ها', 'route' => 'admin.billing.bundles.index', 'active' => request()->routeIs('admin.billing.bundles.*'), 'icon' => 'M4 7h16M4 12h16M4 17h16'],
                        ['label' => 'تنظیمات', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0-5v3m0 12v3M4.2 4.2l2.1 2.1m11.4 11.4 2.1 2.1M3 12h3m12 0h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    <a
                        href="{{ $item['route'] ? route($item['route']) : '#' }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-3 transition {{ $item['active'] ? 'bg-white text-[#105D52] shadow-lg shadow-black/10' : 'text-emerald-50/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto rounded-lg border border-white/10 bg-white/10 p-4 shadow-lg shadow-black/10">
                <p class="text-sm font-bold text-emerald-50/80">اعتبار کیف پول</p>
                <p class="mt-2 text-2xl font-black text-white">۲٬۴۵۰٬۰۰۰ تومان</p>
                <button type="button" class="mt-4 w-full rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-slate-100">
                    افزایش اعتبار
                </button>
            </div>
        </aside>

        <main class="w-full min-w-0 flex-1 overflow-x-hidden">
            <header class="sticky top-0 z-20 border-b border-white/10 bg-[#0A3D37]/95 px-4 py-4 text-white shadow-lg shadow-[#0A3D37]/10 backdrop-blur md:px-8 lg:px-10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-lg border border-white/10 bg-white/10 text-emerald-50/90 transition hover:bg-white/15 hover:text-white lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="باز کردن منو"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <p class="text-sm text-emerald-50/60">خوش آمدید، امیر</p>
                            <h1 class="text-lg font-black leading-8 text-white md:text-2xl">داشبورد سرورهای شما</h1>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:justify-start">
                        <button type="button" class="hidden rounded-lg border border-white/10 bg-white/10 px-4 py-2.5 text-sm font-bold text-emerald-50/90 transition hover:bg-white/15 hover:text-white sm:block">
                            مستندات
                        </button>
                        <a
                            href="{{ route('admin.virtual-machines.create') }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#105D52] px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#105D52]/20 transition hover:bg-[#0D4C44]"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            افزودن VM
                        </a>
                        <form method="POST" action="{{ route('admin.logout', [], false) }}">@csrf <button class="rounded-2xl bg-white px-5 py-3 font-black text-[#0A3D37]">خروج</button></form>
                    </div>
                </div>
            </header>

            @yield('content')

            <div
                x-show="createOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"
                @keydown.escape.window="createOpen = false"
                style="display: none;"
            >
                <div
                    x-show="createOpen"
                    x-transition
                    @click.outside="createOpen = false"
                    class="w-full max-w-xl rounded-lg bg-white p-5 shadow-2xl md:p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-black">ساخت ماشین جدید</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-600">این پنجره فعلا نمایشی است تا حس مسیر ساخت ماشین را ببینید.</p>
                        </div>
                        <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600" @click="createOpen = false" aria-label="بستن">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <button type="button" class="rounded-lg border-2 border-[#105D52] bg-[#F1F7F5] p-4 text-right">
                            <span class="block font-black text-[#105D52]">Ubuntu</span>
                            <span class="mt-1 block text-xs text-slate-500">۲۴.۰۴ LTS</span>
                        </button>
                        <button type="button" class="rounded-lg border border-slate-200 p-4 text-right hover:bg-slate-50">
                            <span class="block font-black">Debian</span>
                            <span class="mt-1 block text-xs text-slate-500">۱۲ Bookworm</span>
                        </button>
                        <button type="button" class="rounded-lg border border-slate-200 p-4 text-right hover:bg-slate-50">
                            <span class="block font-black">Rocky</span>
                            <span class="mt-1 block text-xs text-slate-500">۹ Linux</span>
                        </button>
                    </div>
                    <div class="mt-4 rounded-lg border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-black">پلن پیشنهادی شروع</p>
                                <p class="mt-1 text-sm text-slate-500">۲ vCPU، ۴GB رم، ۸۰GB NVMe</p>
                            </div>
                            <p class="text-left text-lg font-black text-[#105D52]">۴۹۰٬۰۰۰<br><span class="text-xs text-slate-500">تومان / ماه</span></p>
                        </div>
                    </div>
                    <button type="button" class="mt-5 w-full rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white hover:bg-[#0D4C44]" @click="createOpen = false">
                        ادامه ساخت ماشین
                    </button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
