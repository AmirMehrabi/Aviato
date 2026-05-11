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
        @keydown.window.escape="closePanels(); createOpen = false"
        @keydown.window="
            if ($event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                $event.preventDefault();
                openSearch();
            }
        "
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
            class="fixed inset-y-0 right-0 z-40 hidden w-72 translate-x-full flex-col border-l border-white/10 bg-[#061A33] px-5 py-5 text-white shadow-2xl shadow-[#061A33]/30 transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-[#2563EB] text-lg font-black text-white shadow-lg shadow-blue-950/30">آ</span>
                    <span>
                        <span class="block text-lg font-black text-white">آویاتو</span>
                        <span class="block text-xs text-blue-100/65">کنسول مدیریت ابر</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-lg border border-white/10 text-blue-100/80 transition hover:bg-white/10 hover:text-white lg:hidden"
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
                        class="flex items-center gap-3 rounded-lg px-3 py-3 transition {{ $item['active'] ? 'bg-white text-[#0B2550] shadow-lg shadow-black/10' : 'text-blue-100/70 hover:bg-[#082B55] hover:text-white' }}"
                    >
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto rounded-lg border border-white/10 bg-white/[0.07] p-4 shadow-lg shadow-black/10">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-blue-100/80">ریسک عملیاتی</p>
                    <span class="rounded-md bg-amber-400/15 px-2 py-1 text-xs font-black text-amber-100">۳ هشدار</span>
                </div>
                <p class="mt-3 text-2xl font-black text-white">۸۷٪ آماده</p>
                <p class="mt-2 text-xs leading-6 text-blue-100/65">ظرفیت تهران ۱ نیاز به بازبینی دارد.</p>
                <a href="{{ route('admin.proxmox-servers.index') }}" class="mt-4 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#061A33] transition hover:bg-blue-50">
                    بررسی زیرساخت
                </a>
            </div>
        </aside>

        <main class="w-full min-w-0 flex-1 overflow-x-hidden">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm shadow-slate-200/60 backdrop-blur md:px-8 lg:px-10">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#0B2550] lg:hidden"
                        @click="sidebarOpen = true"
                        aria-label="باز کردن منو"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <div class="relative min-w-0 flex-1" @click.outside="searchOpen = false">
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <input
                            x-ref="adminSearch"
                            x-model="searchQuery"
                            @focus="searchOpen = true"
                            @keydown.enter.prevent="submitSearch()"
                            type="search"
                            placeholder="جستجو در مشتری، VM، IP، فاکتور..."
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-24 pr-11 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >
                        <div class="pointer-events-none absolute inset-y-0 left-2 hidden items-center gap-1 text-[11px] font-bold text-slate-500 sm:flex">
                            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 shadow-sm">Ctrl K</kbd>
                            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 shadow-sm">/</kbd>
                        </div>

                        <div
                            x-cloak
                            x-show="searchOpen"
                            x-transition
                            class="absolute right-0 top-full z-30 mt-2 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl shadow-slate-950/10 lg:max-w-2xl"
                        >
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-xs font-black text-slate-500">جستجوی سریع مدیریت</p>
                                <p class="mt-1 text-xs text-slate-400">برای جستجوی دقیق، نام مشتری، نام VM یا IP را وارد کنید.</p>
                            </div>
                            <div class="grid gap-2 p-2 md:grid-cols-2">
                                <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg p-3 transition hover:bg-blue-50">
                                    <span class="block text-sm font-black text-slate-900">ماشین‌های مجازی</span>
                                    <span class="mt-1 block text-xs text-slate-500">وضعیت، IP، مصرف و هزینه</span>
                                </a>
                                <a href="{{ route('admin.customers.index') }}" class="rounded-lg p-3 transition hover:bg-blue-50">
                                    <span class="block text-sm font-black text-slate-900">مشتریان</span>
                                    <span class="mt-1 block text-xs text-slate-500">کیف پول، تعلیق و پروفایل</span>
                                </a>
                                <a href="{{ route('admin.proxmox-servers.index') }}" class="rounded-lg p-3 transition hover:bg-blue-50">
                                    <span class="block text-sm font-black text-slate-900">Proxmox Nodes</span>
                                    <span class="mt-1 block text-xs text-slate-500">Sync، ظرفیت و اتصال</span>
                                </a>
                                <a href="{{ route('admin.billing.rates.index') }}" class="rounded-lg p-3 transition hover:bg-blue-50">
                                    <span class="block text-sm font-black text-slate-900">قیمت منابع</span>
                                    <span class="mt-1 block text-xs text-slate-500">CPU، RAM، دیسک و ترافیک</span>
                                </a>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                                <span>Enter برای رفتن به نتیجه مرتبط</span>
                                <span>Esc برای بستن</span>
                            </div>
                        </div>
                    </div>

                    <a
                        href="{{ route('admin.virtual-machines.create') }}"
                        class="hidden shrink-0 items-center gap-2 rounded-lg bg-[#2563EB] px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-[#1D4ED8] xl:inline-flex"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                        </svg>
                        VM جدید
                    </a>

                    <div class="relative shrink-0" @click.outside="notificationsOpen = false">
                        <button
                            type="button"
                            class="relative grid size-11 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#0B2550]"
                            @click="notificationsOpen = !notificationsOpen; profileOpen = false; searchOpen = false"
                            aria-label="اعلان‌ها"
                        >
                            <span class="absolute left-2 top-2 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div
                            x-cloak
                            x-show="notificationsOpen"
                            x-transition
                            class="absolute left-0 top-full z-30 mt-2 w-80 overflow-hidden rounded-lg border border-slate-200 bg-white text-right shadow-xl shadow-slate-950/10"
                        >
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                <p class="font-black text-slate-900">اعلان‌های مدیریت</p>
                                <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-black text-red-700">۳ جدید</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <a href="{{ route('admin.virtual-machines.index') }}" class="block p-4 transition hover:bg-slate-50">
                                    <p class="text-sm font-black text-slate-900">Provisioning ماشین staging-api طولانی شده است</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">۸ دقیقه در صف ساخت مانده است.</p>
                                </a>
                                <a href="{{ route('admin.proxmox-servers.index') }}" class="block p-4 transition hover:bg-slate-50">
                                    <p class="text-sm font-black text-slate-900">ظرفیت RAM تهران ۱ به ۸۷٪ رسید</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">برای فروش پلن‌های بزرگ‌تر ظرفیت را بررسی کنید.</p>
                                </a>
                                <a href="{{ route('admin.customers.index') }}" class="block p-4 transition hover:bg-slate-50">
                                    <p class="text-sm font-black text-slate-900">۲ مشتری با بدهی نزدیک به تعلیق</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">کیف پول کمتر از هزینه ۲۴ ساعت آینده است.</p>
                                </a>
                            </div>
                            <button type="button" class="w-full bg-slate-50 px-4 py-3 text-sm font-black text-[#2563EB]">
                                مشاهده همه اعلان‌ها
                            </button>
                        </div>
                    </div>

                    <div class="relative shrink-0" @click.outside="profileOpen = false">
                        <button
                            type="button"
                            class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-slate-700 transition hover:border-blue-200 hover:bg-blue-50"
                            @click="profileOpen = !profileOpen; notificationsOpen = false; searchOpen = false"
                            aria-label="پروفایل مدیر"
                        >
                            <span class="grid size-8 place-items-center rounded-md bg-[#061A33] text-sm font-black text-white">ا</span>
                            <span class="hidden text-right md:block">
                                <span class="block text-sm font-black leading-4 text-slate-900">امیر</span>
                                <span class="block text-[11px] font-bold leading-4 text-slate-500">مدیر سیستم</span>
                            </span>
                            <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div
                            x-cloak
                            x-show="profileOpen"
                            x-transition
                            class="absolute left-0 top-full z-30 mt-2 w-64 overflow-hidden rounded-lg border border-slate-200 bg-white text-right shadow-xl shadow-slate-950/10"
                        >
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="font-black text-slate-900">امیر</p>
                                <p class="mt-1 text-xs text-slate-500">دسترسی مدیریت آویاتو</p>
                            </div>
                            <div class="p-2">
                                <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-blue-50 hover:text-[#0B2550]">
                                    تنظیمات پنل
                                </a>
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-blue-50 hover:text-[#0B2550]">
                                    نمای کلی مدیریت
                                </a>
                                <form method="POST" action="{{ route('admin.logout', [], false) }}" class="mt-1 border-t border-slate-100 pt-2">
                                    @csrf
                                    <button class="w-full rounded-lg px-3 py-2.5 text-right text-sm font-black text-red-600 transition hover:bg-red-50">
                                        خروج از پنل مدیریت
                                    </button>
                                </form>
                            </div>
                        </div>
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
                            <p class="mt-2 text-sm leading-7 text-slate-600">برای ساخت عملیاتی VM، مشتری، نود و منابع را از مسیر اصلی انتخاب کنید.</p>
                        </div>
                        <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600" @click="createOpen = false" aria-label="بستن">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <button type="button" class="rounded-lg border-2 border-[#2563EB] bg-blue-50 p-4 text-right">
                            <span class="block font-black text-[#1D4ED8]">Ubuntu</span>
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
                            <p class="text-left text-lg font-black text-[#1D4ED8]">۴۹۰٬۰۰۰<br><span class="text-xs text-slate-500">تومان / ماه</span></p>
                        </div>
                    </div>
                    <a href="{{ route('admin.virtual-machines.create') }}" class="mt-5 inline-flex w-full justify-center rounded-lg bg-[#2563EB] px-5 py-3 text-sm font-black text-white hover:bg-[#1D4ED8]">
                        ادامه ساخت ماشین
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
