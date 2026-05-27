<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل مشتریان')</title>
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
<body class="min-h-screen bg-[#FFF] text-slate-950">
    @php
        $customerInitial = mb_substr($customer->name ?? 'م', 0, 1);
        $balanceIsNegative = ($wallet->balance ?? 0) < 0;
        $activeNav = $activeNav ?? 'dashboard';
        $navGroups = [
            'پروژه ها' => [
                ['key' => 'dashboard', 'label' => 'داشبورد', 'route' => route('dashboard', [], false), 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
            ],
            'مدیریت' => [
                ['key' => 'servers', 'label' => 'ماشین ها', 'route' => route('customer.servers.index', [], false), 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                ['key' => 'network', 'label' => 'شبکه', 'route' => null, 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4'],
                ['key' => 'backups', 'label' => 'بکاپ ها', 'route' => route('customer.backups.index', [], false), 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                ['key' => 'monitoring', 'label' => 'مانیتورینگ', 'route' => route('customer.monitoring.index', [], false), 'icon' => 'M4 19V5m4 14v-7m4 7V8m4 11v-4m4 4V9'],
            ],
            'حساب' => [
                ['key' => 'wallet', 'label' => 'کیف پول', 'route' => route('customer.wallet.show', [], false), 'icon' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Zm1 2.5h.01'],
                ['key' => 'invoices', 'label' => 'صورتحساب ها', 'route' => route('customer.invoices.index', [], false), 'icon' => 'M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5M8 13h8M8 17h6'],
            ],
        ];
    @endphp

    <div
        x-data="{
            sidebarOpen: false,
            searchOpen: false,
            searchQuery: '',
            walletOpen: false,
            profileOpen: false,
            searchItems: [],
            init() {
                const baseItems = [
                    { title: 'داشبورد', description: 'نمای کلی ماشین ها، کیف پول و مصرف', url: '{{ route('dashboard', [], false) }}', type: 'صفحه' },
                    { title: 'سرورها', description: 'فهرست ماشین های ابری و وضعیت آنها', url: '{{ route('customer.servers.index', [], false) }}', type: 'صفحه' },
                    { title: 'ساخت ماشین', description: 'انتخاب پلن، سیستم عامل و دیتاسنتر', url: '{{ route('customer.servers.create', [], false) }}', type: 'عملیات' },
                    { title: 'بکاپ ها', description: 'بکاپ دستی، برنامه بکاپ خودکار و نگهداری نسخه ها', url: '{{ route('customer.backups.index', [], false) }}', type: 'صفحه' },
                    { title: 'مانیتورینگ', description: 'نمودار مصرف CPU، RAM، شبکه و وضعیت بکاپ', url: '{{ route('customer.monitoring.index', [], false) }}', type: 'صفحه' },
                    { title: 'کیف پول', description: 'موجودی، تراکنش ها و افزایش اعتبار', url: '{{ route('customer.wallet.show', [], false) }}', type: 'صفحه' },
                    { title: 'افزایش اعتبار', description: 'شارژ سریع کیف پول', url: '{{ route('customer.wallet.show', ['topup' => 1], false) }}', type: 'عملیات' },
                    { title: 'صورتحساب ها', description: 'بایگانی و جزئیات فاکتورهای ماهانه', url: '{{ route('customer.invoices.index', [], false) }}', type: 'صفحه' }
                ];
                const source = document.getElementById('customer-search-data');
                let pageItems = [];

                if (source?.textContent.trim()) {
                    try {
                        pageItems = JSON.parse(source.textContent);
                    } catch (error) {
                        pageItems = [];
                    }
                }

                this.searchItems = baseItems.concat(pageItems);
            },
            get filteredSearchItems() {
                const query = this.searchQuery.trim().toLowerCase();

                if (!query) {
                    return this.searchItems.slice(0, 8);
                }

                return this.searchItems.filter((item) => {
                    return [item.title, item.description, item.type, item.keywords]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                        .includes(query);
                }).slice(0, 10);
            },
            openSearch() {
                this.searchOpen = true;
                this.walletOpen = false;
                this.profileOpen = false;
                this.$nextTick(() => this.$refs.customerSearch?.focus());
            },
            closePanels() {
                this.searchOpen = false;
                this.walletOpen = false;
                this.profileOpen = false;
            },
            goTo(item) {
                if (!item?.url) return;
                window.location.href = item.url;
            }
        }"
        @keydown.window.ctrl.k.prevent="openSearch()"
        @keydown.window.meta.k.prevent="openSearch()"
        @keydown.window.escape="closePanels(); sidebarOpen = false"
        @keydown.window="
            if ($event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                $event.preventDefault();
                openSearch();
            }
        "
        class="min-h-screen lg:grid lg:grid-cols-[230px_minmax(0,1fr)]"
    >
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="fixed inset-y-0 right-0 z-40 hidden w-[230px] translate-x-full flex-col border-l border-white/10 bg-[#031B4E] py-4 text-white shadow-2xl shadow-[#031B4E]/40 transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between px-4">
                <a href="{{ route('dashboard', [], false) }}" class="flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-full border border-white/15 bg-white/10 text-base font-black text-white shadow-sm shadow-black/20">آ</span>
                    <span>
                        <span class="block text-base font-black leading-5 text-white">آویاتو</span>
                        <span class="block text-[11px] font-bold leading-4 text-[#8FA6D2]">کنسول ابری</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-md border border-white/10 text-[#9DB4DC] transition hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="بستن منو"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="mt-7 space-y-6 text-sm font-bold">
                @foreach ($navGroups as $group => $items)
                    <div>
                        <p class="px-4 text-[10px] font-black text-[#5F79AA]">{{ $group }}</p>
                        <div class="mt-2 space-y-0.5">
                            @foreach ($items as $item)
                                @php
                                    $isActive = $activeNav === $item['key'];
                                @endphp
                                <a
                                    href="{{ $item['route'] ?: '#' }}"
                                    @if (! $item['route']) aria-disabled="true" @endif
                                    class="flex items-center gap-2.5 px-3 py-2 transition {{ $isActive ? 'bg-white/90 text-[#031B4E] shadow-sm shadow-black/10' : ($item['route'] ? 'text-[#C7D4EA] hover:bg-[#0A2A66] hover:text-white' : 'cursor-default text-[#6F86B5] opacity-70') }}"
                                >
                                    <svg class="size-[17px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['key'] === 'invoices' && ($invoiceCount ?? null))
                                        <span class="mr-auto rounded px-1.5 py-0.5 text-[10px] font-black {{ $isActive ? 'bg-[#E5F0FF] text-[#0069FF]' : 'bg-white/10 text-[#C7D4EA]' }}">{{ $invoiceCount }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="mt-7 border-t border-white/10 pt-4">
                <p class="px-3 text-[10px] font-black text-[#5F79AA]">مصرف</p>
                <div class="mt-2 rounded-md border border-white/10 bg-white/[0.06] p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-[#9DB4DC]">موجودی</span>
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-black {{ $wallet->is_locked ? 'bg-red-400/15 text-red-200' : 'bg-emerald-400/15 text-emerald-200' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
                    </div>
                    <p class="mt-2 truncate text-lg font-black {{ $balanceIsNegative ? 'text-red-200' : 'text-white' }}">{{ $wallets->format($wallet->balance) }}</p>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-[#00A67E] px-3 py-2 text-sm font-black text-white transition hover:bg-[#008F6E]">
                        افزایش اعتبار
                    </a>
                </div>
            </div>

            <div class="mt-auto border-t border-white/10 px-1 pt-4 text-xs leading-6 text-[#8FA6D2]">
                مصرف PAYG از کیف پول کسر می شود و صورتحساب ماهانه برای بایگانی صادر می گردد.
            </div>
        </aside>

        <main class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 shadow-sm shadow-slate-200/50 backdrop-blur md:px-6 lg:px-8">
                <div class="flex h-14 items-stretch gap-2">
                    <button
                        type="button"
                        class="my-2 grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] lg:hidden"
                        @click="sidebarOpen = true"
                        aria-label="باز کردن منو"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <button
                            type="button"
                            @click="openSearch()"
                            class="flex h-full w-full max-w-xs items-center gap-2 px-1 text-sm text-slate-400 transition hover:text-[#0069FF] md:max-w-sm"
                        >
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                            </svg>
                            <span class="truncate">جستجو...</span>
                            <span class="mr-auto hidden items-center gap-1 text-[11px] font-black text-slate-400 md:flex">
                                <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5">Ctrl</kbd>
                                <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5">K</kbd>
                            </span>
                        </button>
                    </div>

                    <a href="{{ route('customer.servers.create', [], false) }}" class="my-2 hidden h-10 shrink-0 items-center justify-center rounded-lg bg-[#0069FF] px-4 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0] md:inline-flex">
                        ساخت ماشین
                    </a>

                    <div class="relative">
                        <button
                            type="button"
                            @click="walletOpen = !walletOpen; profileOpen = false; searchOpen = false"
                            class="grid h-full w-10 place-items-center text-slate-500 transition hover:text-[#0069FF]"
                            aria-label="کیف پول"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div
                            x-cloak
                            x-show="walletOpen"
                            x-transition
                            @click.away="walletOpen = false"
                            class="absolute left-0 top-12 z-50 w-72 rounded-lg border border-slate-200 bg-white p-4 text-right shadow-2xl shadow-slate-950/10"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-black text-slate-950">کیف پول</p>
                                <span class="rounded-md px-2 py-1 text-[11px] font-black {{ $wallet->is_locked ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل شده' : 'فعال' }}</span>
                            </div>
                            <p class="mt-3 text-2xl font-black {{ $balanceIsNegative ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">تراکنش ها</a>
                                <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="inline-flex justify-center rounded-lg bg-[#0069FF] px-3 py-2 text-sm font-black text-white transition hover:bg-[#0050D0]">شارژ</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            @click="profileOpen = !profileOpen; walletOpen = false; searchOpen = false"
                            class="grid h-full w-10 place-items-center text-slate-500 transition hover:text-[#0069FF]"
                            aria-label="پروفایل"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">{{ $customerInitial }}</span>
                        </button>

                        <div
                            x-cloak
                            x-show="profileOpen"
                            x-transition
                            @click.away="profileOpen = false"
                            class="absolute left-0 top-12 z-50 w-72 rounded-lg border border-slate-200 bg-white p-4 text-right shadow-2xl shadow-slate-950/10"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-200 pb-4">
                                <span class="grid size-11 place-items-center rounded-lg bg-[#0069FF] text-lg font-black text-white">{{ $customerInitial }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-black text-slate-950">{{ $customer->name }}</p>
                                    <p class="truncate text-sm text-slate-500">{{ $customer->email ?? $customer->phone ?? 'حساب مشتری' }}</p>
                                </div>
                            </div>
                            <div class="mt-3 space-y-1">
                                <a href="{{ route('dashboard', [], false) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">داشبورد</a>
                                <a href="{{ route('customer.wallet.show', [], false) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">کیف پول</a>
                                <a href="{{ route('customer.invoices.index', [], false) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">صورتحساب ها</a>
                                <form method="POST" action="{{ route('customer.logout', [], false) }}" class="pt-2">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-right text-sm font-black text-red-600 transition hover:bg-red-50">
                                        خروج از حساب
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div
                x-cloak
                x-show="searchOpen"
                x-transition.opacity
                @click.self="closePanels()"
                class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/45 px-4 pt-16 backdrop-blur-sm sm:pt-24"
            >
                <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/20">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
                        <svg class="size-5 shrink-0 text-[#0069FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                        </svg>
                        <input
                            x-ref="customerSearch"
                            x-model="searchQuery"
                            type="text"
                            placeholder="نام ماشین، IP، صورتحساب یا عملیات را تایپ کنید..."
                            class="h-11 min-w-0 flex-1 border-0 bg-transparent text-right text-base font-semibold text-slate-950 outline-none placeholder:text-slate-400"
                        >
                        <button type="button" @click="closePanels()" class="rounded-md px-2 py-1 text-xs font-black text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">ESC</button>
                    </div>
                    <div class="max-h-[420px] overflow-y-auto p-2">
                        <template x-if="filteredSearchItems.length">
                            <div class="space-y-1">
                                <template x-for="item in filteredSearchItems" :key="item.url + item.title">
                                    <button type="button" @click="goTo(item)" class="flex w-full items-center justify-between gap-4 rounded-lg px-3 py-3 text-right transition hover:bg-[#EBF3FF]">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-black text-slate-950" x-text="item.title"></span>
                                            <span class="mt-1 block truncate text-xs font-bold text-slate-500" x-text="item.description"></span>
                                        </span>
                                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-[11px] font-black text-slate-500" x-text="item.type"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="!filteredSearchItems.length">
                            <div class="px-4 py-10 text-center text-sm font-bold text-slate-500">نتیجه ای پیدا نشد.</div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="px-4 pb-8 pt-4 md:px-6 lg:px-8">
                <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                    <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <nav class="flex flex-wrap items-center gap-2 text-xs font-black text-slate-400" aria-label="breadcrumb">
                                <a href="{{ route('dashboard', [], false) }}" class="transition hover:text-[#0069FF]">کنسول ابری</a>
                                <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
                                </svg>
                                @hasSection('breadcrumbs')
                                    @yield('breadcrumbs')
                                @else
                                    <span class="truncate text-slate-700">@yield('header_title', 'پنل مشتریان')</span>
                                @endif
                            </nav>
                            <div class="mt-3">
                                <p class="text-xs font-black text-[#0069FF]">{{ $customer->name }}</p>
                                <h1 class="mt-1 truncate text-2xl font-black tracking-normal text-slate-950">@yield('header_title', 'پنل مشتریان')</h1>
                                <p class="mt-1 max-w-3xl text-sm leading-7 text-slate-500">@yield('header_subtitle', 'نمای کامل کیف پول، کارکرد و صورتحساب ها')</p>
                            </div>
                        </div>
                        <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex w-fit shrink-0 items-center justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                            ساخت ماشین
                        </a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </div>

            <script type="application/json" id="customer-search-data">@yield('search_data', '[]')</script>
        </main>
    </div>
</body>
</html>
