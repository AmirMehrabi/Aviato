<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'داشبورد آویاتو')</title>
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">

    <meta name="theme-color" content="#0B6BFF">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="overflow-x-hidden bg-[#F5F7FB] text-slate-950">
    <div
        x-data="{
            sidebarOpen: false,
            createOpen: false,
            period: 'روزانه',
            searchOpen: false,
            searchQuery: '',
            notificationsOpen: false,
            profileOpen: false,
            openSearch() {
                this.searchOpen = true;
                this.notificationsOpen = false;
                this.profileOpen = false;
                this.$nextTick(() => this.$refs.adminSearch?.focus());
            },
            closePanels() {
                this.searchOpen = false;
                this.notificationsOpen = false;
                this.profileOpen = false;
            },
            submitSearch() {
                const query = this.searchQuery.trim();
                if (!query) return;

                const target = /(^|\s)(vm|vps|ip|ماشین|سرور)|\d{1,3}(\.\d{1,3}){1,3}/i.test(query)
                    ? '{{ route('admin.virtual-machines.index') }}'
                    : /(proxmox|node|نود|کلاستر)/i.test(query)
                        ? '{{ route('admin.proxmox-servers.index') }}'
                        : '{{ route('admin.customers.index') }}';

                window.location.href = `${target}?search=${encodeURIComponent(query)}`;
            }
        }"
        @keydown.window.ctrl.k.prevent="openSearch()"
        @keydown.window.meta.k.prevent="openSearch()"
        @keydown.window.escape="closePanels(); createOpen = false; sidebarOpen = false"
        @keydown.window="
            if ($event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                $event.preventDefault();
                openSearch();
            }
        "
        class="min-h-screen overflow-x-hidden lg:flex"
    >
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-30 bg-slate-950/35 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="pointer-events-none fixed inset-y-0 right-0 z-40 flex w-[min(86vw,288px)] translate-x-full flex-col overflow-y-auto border-l border-white/10 bg-[#0069FF] px-4 py-4 text-white shadow-2xl shadow-[#0069FF]/30 transition-transform duration-200 lg:pointer-events-auto lg:static lg:w-64 lg:translate-x-0 lg:overflow-visible lg:shadow-none"
            :class="{ '!pointer-events-auto !translate-x-0': sidebarOpen }"
            aria-label="منوی مدیریت"
        >
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                    <img src="{{ asset("assets/images/aviato_icon_white.png") }}" class="w-10" alt="Aviato Logo">

                    <span>
                        <span class="block text-base font-black text-white">آویاتو</span>
                        <span class="block text-[11px] text-white/70">کنسول مدیریت</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-lg border border-white/10 text-white/80 transition hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="بستن منو"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="mt-6 space-y-4 text-sm font-semibold">
                @php
                    $navGroups = [
                        [
                            'items' => [
                                ['label' => 'داشبورد', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
                            ],
                        ],
                        [
                            'label' => 'مدیریت',
                            'items' => [
                                ['label' => 'مشتریان', 'route' => 'admin.customers.index', 'active' => request()->routeIs('admin.customers.*'), 'icon' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0M19 8v6m3-3h-6'],
                                ['label' => 'فضاهای کاری', 'route' => 'admin.projects.index', 'active' => request()->routeIs('admin.projects.*'), 'icon' => 'M4 5h7v7H4V5Zm9 0h7v7h-7V5ZM4 14h7v5H4v-5Zm9 0h7v5h-7v-5Z'],
                            ],
                        ],
                        [
                            'label' => 'زیرساخت',
                            'items' => [
                                ['label' => 'Proxmox', 'route' => 'admin.proxmox-servers.index', 'active' => request()->routeIs('admin.proxmox-servers.*'), 'icon' => 'M5 6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4H5V6Zm0 4h14v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-8Zm3 4h2m4 0h2M8 17h8'],
                                ['label' => 'ماشین‌ها', 'route' => 'admin.virtual-machines.index', 'active' => request()->routeIs('admin.virtual-machines.*'), 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                                ['label' => 'Cloud Images', 'route' => 'admin.cloud-images.index', 'active' => request()->routeIs('admin.cloud-images.*'), 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                                ['label' => 'IP Pools', 'route' => 'admin.ip-pools.index', 'active' => request()->routeIs('admin.ip-pools.*'), 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4M4.9 16.9l2.8-2.8m8.6-8.6 2.8-2.8'],
                            ],
                        ],
                        [
                            'label' => 'پشتیبانی',
                            'items' => [
                                ['label' => 'تیکت‌ها', 'route' => 'admin.tickets.index', 'active' => request()->routeIs('admin.tickets.*') || request()->routeIs('admin.support-teams.*') || request()->routeIs('admin.ticket-categories.*'), 'icon' => 'M4 5h16v10H7l-3 3V5Zm5 4h6m-6 3h4'],
                            ],
                        ],
                        [
                            'label' => 'صورتحساب',
                            'items' => [
                                ['label' => 'قیمت منابع', 'route' => 'admin.billing.rates.index', 'active' => request()->routeIs('admin.billing.rates.*'), 'icon' => 'M7 4h10v16H7V4Zm3 4h4m-4 4h4m-4 4h2'],
                                ['label' => 'باندل‌ها', 'route' => 'admin.billing.bundles.index', 'active' => request()->routeIs('admin.billing.bundles.*'), 'icon' => 'M4 7h16M4 12h16M4 17h16'],
                            ],
                        ],
                        [
                            'label' => 'سیستم',
                            'items' => [
                                ['label' => 'تنظیمات', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v6m0-12V3m9 9h-6m-6 0H3m15.364 5.364-4.243-4.243m-6.364 0L3.636 17.364M20.364 6.636l-4.243 4.243m-6.364 0L5.636 6.636'],
                            ],
                        ],
                    ];
                @endphp
                @foreach ($navGroups as $group)
                    @if (isset($group['label']))
                        <div>
                            <span class="block px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-white/50">{{ $group['label'] }}</span>
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'bg-white text-[#0069FF] shadow-sm' : 'text-white/80 hover:bg-white/15 hover:text-white' }}"
                                    >
                                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'bg-white text-[#0069FF] shadow-sm' : 'text-white/80 hover:bg-white/15 hover:text-white' }}"
                            >
                                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>

            <div class="mt-auto rounded-lg border border-white/10 bg-white/[0.07] p-4 shadow-lg shadow-black/10">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-white/90">ریسک عملیاتی</p>
                    <span class="rounded-md bg-amber-400/15 px-2 py-1 text-xs font-black text-amber-100">۳ هشدار</span>
                </div>
                <p class="mt-3 text-2xl font-black text-white">۸۷٪ آماده</p>
                <p class="mt-2 text-xs leading-6 text-white/70">ظرفیت تهران ۱ نیاز به بازبینی دارد.</p>
                <a href="{{ route('admin.proxmox-servers.index') }}" class="mt-4 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#0069FF] transition hover:bg-white/90">
                    بررسی زیرساخت
                </a>
            </div>
        </aside>

        <main class="w-full min-w-0 flex-1 overflow-x-hidden">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm shadow-slate-200/60 backdrop-blur md:px-8 lg:px-10">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] lg:hidden"
                        @click="sidebarOpen = true"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-label="باز کردن منو"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <div class="relative flex-1">
                        <button
                            type="button"
                            @click="openSearch()"
                            class="flex w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] md:max-w-md"
                        >
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                            </svg>
                            <span class="hidden sm:inline">جستجو در مشتری، VM، IP...</span>
                            <span class="sm:hidden">جستجو...</span>
                            <div class="mr-auto hidden gap-1 text-[11px] font-black text-slate-400 md:flex">
                                <kbd class="rounded border border-slate-200 px-1.5 py-0.5">Ctrl</kbd>
                                <kbd class="rounded border border-slate-200 px-1.5 py-0.5">K</kbd>
                            </div>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="notificationsOpen = !notificationsOpen; searchOpen = false; profileOpen = false"
                            class="relative grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50"
                            aria-label="اعلان‌ها"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-2 top-2 size-2 rounded-full bg-[#0069FF] ring-2 ring-white"></span>
                        </button>

                        <button
                            type="button"
                            @click="profileOpen = !profileOpen; searchOpen = false; notificationsOpen = false"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 transition hover:bg-slate-50"
                            aria-label="پروفایل"
                        >
                            <span class="hidden text-right text-sm sm:block">
                                <span class="block font-black leading-tight text-slate-950">امیر حسینی</span>
                                <span class="block text-[11px] font-bold leading-4 text-slate-500">مدیر سیستم</span>
                            </span>
                            <span class="grid size-7 place-items-center rounded-lg bg-[#0069FF] text-sm font-black text-white">ا</span>
                        </button>
                    </div>
                </div>
            </header>

            <div
                x-show="searchOpen"
                x-transition.opacity
                @click.self="closePanels()"
                class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/50 px-4 pt-20 backdrop-blur-sm"
            >
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <form @submit.prevent="submitSearch()">
                        <input
                            x-ref="adminSearch"
                            x-model="searchQuery"
                            type="text"
                            placeholder="نام مشتری، hostname، IP یا VMID را جستجو کنید..."
                            class="w-full rounded-lg border-2 border-[#0069FF] bg-white px-4 py-3 text-right text-base text-slate-950 placeholder:text-slate-400 focus:outline-none"
                        />
                        <div class="mt-4 flex gap-2">
                            <button
                                type="submit"
                                class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]"
                            >
                                جستجو
                            </button>
                            <button
                                type="button"
                                @click="closePanels()"
                                class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50"
                            >
                                لغو
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                x-show="notificationsOpen"
                x-transition
                @click.away="notificationsOpen = false"
                class="absolute left-4 top-16 z-50 w-80 rounded-lg border border-slate-200 bg-white p-5 shadow-2xl md:left-8"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-black text-slate-950">اعلان‌ها</h3>
                    <span class="inline-flex items-center justify-center rounded-full bg-[#0069FF] px-2 py-0.5 text-xs font-black text-white">۳</span>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach (auth('admin')->user()?->notifications()->latest()->limit(3)->get() ?? collect() as $notification)
                        <a href="{{ data_get($notification->data, 'url', route('admin.tickets.index')) }}" class="flex gap-3 rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                            <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-300' : 'bg-[#0069FF]' }}"></span>
                            <p class="text-sm leading-7 text-slate-600"><span class="font-black text-slate-900">{{ data_get($notification->data, 'title', 'اعلان') }}</span><br>{{ data_get($notification->data, 'body', '') }}</p>
                        </a>
                    @endforeach
                    @if ((auth('admin')->user()?->notifications()->count() ?? 0) === 0)
                        <p class="rounded-lg border border-slate-200 p-4 text-center text-sm font-bold text-slate-500">اعلان جدیدی وجود ندارد.</p>
                    @endif
                </div>
            </div>

            <div
                x-show="profileOpen"
                x-transition
                @click.away="profileOpen = false"
                class="absolute left-4 top-16 z-50 w-64 rounded-lg border border-slate-200 bg-white p-4 shadow-2xl md:left-8"
            >
                <div class="flex items-center gap-3 border-b border-slate-200 pb-4">
                    <span class="grid size-12 place-items-center rounded-lg bg-[#0069FF] text-lg font-black text-white">ا</span>
                    <div>
                        <p class="font-black text-slate-950">امیر حسینی</p>
                        <p class="text-sm text-slate-500">مدیر سیستم</p>
                    </div>
                </div>
                <div class="mt-4 space-y-1">
                    <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v6m0-12V3m9 9h-6m-6 0H3m15.364 5.364-4.243-4.243m-6.364 0L3.636 17.364M20.364 6.636l-4.243 4.243m-6.364 0L5.636 6.636" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        تنظیمات
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-right text-sm font-bold text-red-600 transition hover:bg-red-50">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            خروج از حساب
                        </button>
                    </form>
                </div>
            </div>

            @yield('content')

            <div
                x-show="createOpen"
                x-transition.opacity
                @click.self="createOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 backdrop-blur-sm"
            >
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 shadow-2xl">
                    <h2 class="text-xl font-black text-slate-950">ساخت ماشین مجازی جدید</h2>
                    <p class="mt-2 text-sm text-slate-600">موقعیت، منابع و سیستم‌عامل را انتخاب کنید.</p>
                    <div class="mt-6 grid grid-cols-3 gap-3">
                        <button type="button" class="rounded-lg border-2 border-[#0069FF] bg-[#EBF3FF] p-4 text-right">
                            <span class="block font-black text-[#0069FF]">تهران ۱</span>
                            <span class="mt-1 block text-xs text-slate-500">ایران</span>
                        </button>
                        <button type="button" class="rounded-lg border border-slate-200 p-4 text-right hover:bg-slate-50">
                            <span class="block font-black">شیراز ۱</span>
                            <span class="mt-1 block text-xs text-slate-500">ایران</span>
                        </button>
                        <button type="button" class="rounded-lg border border-slate-200 p-4 text-right hover:bg-slate-50">
                            <span class="block font-black">فرانکفورت</span>
                            <span class="mt-1 block text-xs text-slate-500">آلمان</span>
                        </button>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <button type="button" class="rounded-lg border-2 border-[#0069FF] bg-[#EBF3FF] p-4 text-right">
                            <span class="block font-black text-[#0069FF]">Ubuntu</span>
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
                            <p class="text-left text-lg font-black text-[#0069FF]">۴۹۰٬۰۰۰<br><span class="text-xs text-slate-500">تومان / ماه</span></p>
                        </div>
                    </div>
                    <a href="{{ route('admin.virtual-machines.create') }}" class="mt-5 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white hover:bg-[#0050D0]">
                        ادامه ساخت ماشین
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
