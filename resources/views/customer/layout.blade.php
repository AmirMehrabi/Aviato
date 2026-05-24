<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل مشتریان')</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#F5F7FB] text-slate-950">
    @php
        $customerInitial = mb_substr($customer->name ?? 'م', 0, 1);
        $balanceIsNegative = ($wallet->balance ?? 0) < 0;
        $activeNav = $activeNav ?? 'dashboard';
        $navItems = [
            ['key' => 'dashboard', 'label' => 'داشبورد', 'route' => route('dashboard', [], false), 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
            ['key' => 'wallet', 'label' => 'کیف پول', 'route' => route('customer.wallet.show', [], false), 'icon' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Zm1 2.5h.01'],
            ['key' => 'invoices', 'label' => 'صورتحساب ها', 'route' => route('customer.invoices.index', [], false), 'icon' => 'M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5M8 13h8M8 17h6'],
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
        class="min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]"
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
            class="fixed inset-y-0 right-0 z-40 hidden w-60 translate-x-full flex-col border-l border-slate-200 bg-white px-3 py-4 shadow-2xl shadow-slate-950/10 transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between px-1">
                <a href="{{ route('dashboard', [], false) }}" class="flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-lg bg-[#0069FF] text-base font-black text-white shadow-sm shadow-[#0069FF]/25">آ</span>
                    <span>
                        <span class="block text-base font-black leading-5 text-slate-950">آویاتو</span>
                        <span class="block text-[11px] font-bold leading-4 text-slate-400">کنسول ابری</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="بستن منو"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="mt-7 space-y-1 text-sm font-bold">
                @foreach ($navItems as $item)
                    <a
                        href="{{ $item['route'] }}"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 transition {{ $activeNav === $item['key'] ? 'bg-[#0069FF] text-white shadow-sm shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-[#EBF3FF] hover:text-[#0069FF]' }}"
                    >
                        <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                        @if ($item['key'] === 'invoices' && ($invoiceCount ?? null))
                            <span class="mr-auto rounded-md px-2 py-0.5 text-[11px] {{ $activeNav === $item['key'] ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $invoiceCount }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="mt-7 border-t border-slate-200 pt-4">
                <p class="px-3 text-[11px] font-black text-slate-400">مصرف</p>
                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-slate-500">موجودی</span>
                        <span class="rounded-md px-2 py-0.5 text-[11px] font-black {{ $wallet->is_locked ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
                    </div>
                    <p class="mt-2 truncate text-lg font-black {{ $balanceIsNegative ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-[#0069FF] px-3 py-2 text-sm font-black text-white transition hover:bg-[#0050D0]">
                        افزایش اعتبار
                    </a>
                </div>
            </div>

            <div class="mt-auto px-1 pt-6 text-xs leading-6 text-slate-400">
                مصرف PAYG از کیف پول کسر می شود و صورتحساب ماهانه برای بایگانی صادر می گردد.
            </div>
        </aside>

        <main class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm shadow-slate-200/50 backdrop-blur md:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] lg:hidden"
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
                            class="flex h-10 w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-400 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] md:max-w-xl"
                        >
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                            </svg>
                            <span class="truncate">جستجو در ماشین ها، صورتحساب ها و عملیات...</span>
                            <span class="mr-auto hidden items-center gap-1 text-[11px] font-black text-slate-400 md:flex">
                                <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5">Ctrl</kbd>
                                <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5">K</kbd>
                            </span>
                        </button>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            @click="walletOpen = !walletOpen; profileOpen = false; searchOpen = false"
                            class="hidden h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:flex"
                            aria-label="کیف پول"
                        >
                            <span class="grid size-6 place-items-center rounded-md bg-[#EBF3FF] text-[#0069FF]">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="{{ $balanceIsNegative ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</span>
                            <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
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
                            class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF]"
                            aria-label="پروفایل"
                        >
                            <span class="hidden min-w-0 text-right text-sm sm:block">
                                <span class="block max-w-36 truncate font-black leading-5 text-slate-950">{{ $customer->name }}</span>
                                <span class="block truncate text-[11px] font-bold leading-4 text-slate-500">{{ $customer->email ?? $customer->phone ?? 'حساب مشتری' }}</span>
                            </span>
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#0069FF] text-sm font-black text-white">{{ $customerInitial }}</span>
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

            <div class="px-4 py-6 md:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-[#0069FF]">{{ $customer->name }}</p>
                        <h1 class="mt-1 text-2xl font-black tracking-normal text-slate-950">@yield('header_title', 'پنل مشتریان')</h1>
                        <p class="mt-1 text-sm leading-7 text-slate-500">@yield('header_subtitle', 'نمای کامل کیف پول، کارکرد و صورتحساب ها')</p>
                    </div>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        افزایش اعتبار
                    </a>
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
