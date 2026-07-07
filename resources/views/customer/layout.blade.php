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
        $projectAccess = app(\App\Services\ProjectAccessService::class);
        $projects = $projects ?? $projectAccess->projectsFor($customer);
        $activeProject = $activeProject ?? $projectAccess->activeProject(request(), $customer);
        $activeMembership = $activeMembership ?? $projectAccess->membership($activeProject, $customer);
        $canViewVms = $canViewVms ?? $projectAccess->canViewVms($activeProject, $customer);
        $canManageVms = $canManageVms ?? $projectAccess->canManageVms($activeProject, $customer);
        $customerInitial = mb_substr($customer->name ?? 'م', 0, 1);
        $balanceIsNegative = ($wallet->balance ?? 0) < 0;
        $walletRestrictionThreshold = \App\Models\AppSetting::customerWalletNegativeThreshold();
        $activeNav = $activeNav ?? 'dashboard';
        $navGroups = [
            'فضای کاری' => [
                ['key' => 'projects', 'label' => 'فضاهای کاری', 'route' => route('customer.projects.index', [], false), 'icon' => 'M4 5h7v7H4V5Zm9 0h7v7h-7V5ZM4 14h7v5H4v-5Zm9 0h7v5h-7v-5Z'],
                ['key' => 'dashboard', 'label' => 'داشبورد', 'route' => route('dashboard', [], false), 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
            ],
            'مدیریت' => [
                ...($canViewVms ? [
                    ['key' => 'servers', 'label' => 'ماشین ها', 'route' => route('customer.servers.index', [], false), 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                    ['key' => 'network', 'label' => 'شبکه', 'route' => null, 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4'],
                    ['key' => 'backups', 'label' => 'بکاپ ها', 'route' => route('customer.backups.index', [], false), 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                    ['key' => 'monitoring', 'label' => 'مانیتورینگ', 'route' => route('customer.monitoring.index', [], false), 'icon' => 'M4 19V5m4 14v-7m4 7V8m4 11v-4m4 4V9'],
                ] : []),
            ],
            'حساب' => [
                ['key' => 'profile', 'label' => 'پروفایل', 'route' => route('customer.profile.show', [], false), 'icon' => 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm-8 9a8 8 0 0 1 16 0'],
                ['key' => 'tickets', 'label' => 'تیکت‌ها', 'route' => route('customer.tickets.index', [], false), 'icon' => 'M4 5h16v10H7l-3 3V5Zm5 4h6m-6 3h4'],
                ['key' => 'wallet', 'label' => 'کیف پول', 'route' => route('customer.wallet.show', [], false), 'icon' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Zm1 2.5h.01'],
                ['key' => 'invoices', 'label' => 'صورتحساب ها', 'route' => route('customer.invoices.index', [], false), 'icon' => 'M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5M8 13h8M8 17h6'],
            ],
            ...(auth('customer')->check() && auth('customer')->user()->isReseller() ? [
                'فروشندگی' => [
                    ['key' => 'reseller', 'label' => 'داشبورد فروشندگی', 'route' => route('customer.reseller.dashboard', [], false), 'icon' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0M19 8v6m3-3h-6'],
                    ['key' => 'reseller-customers', 'label' => 'مشتریان', 'route' => route('customer.reseller.customers', [], false), 'icon' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 21a8 8 0 0 1 16 0'],
                    ['key' => 'reseller-commissions', 'label' => 'کمیسیون‌ها', 'route' => route('customer.reseller.commissions', [], false), 'icon' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
                    ['key' => 'reseller-referral', 'label' => 'لینک معرفی', 'route' => route('customer.reseller.referral', [], false), 'icon' => 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71'],
                    ['key' => 'reseller-withdrawals', 'label' => 'برداشت‌ها', 'route' => route('customer.reseller.withdrawals', [], false), 'icon' => 'M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9'],
                ],
            ] : []),
        ];
        $searchBaseItems = [
            ['title' => 'داشبورد', 'description' => 'نمای کلی ماشین ها، کیف پول و مصرف', 'url' => route('dashboard', [], false), 'type' => 'صفحه'],
            ['title' => 'فضاهای کاری', 'description' => 'انتخاب فضای کاری فعال، ساخت فضای کاری و مدیریت اعضا', 'url' => route('customer.projects.index', [], false), 'type' => 'صفحه'],
            ['title' => 'تیکت‌ها', 'description' => 'درخواست‌های پشتیبانی و پاسخ‌ها', 'url' => route('customer.tickets.index', [], false), 'type' => 'صفحه'],
            ['title' => 'تیکت جدید', 'description' => 'ثبت درخواست جدید برای پشتیبانی', 'url' => route('customer.tickets.create', [], false), 'type' => 'عملیات'],
            ['title' => 'پروفایل', 'description' => 'کد ملی، سطح حساب و سهمیه ساخت', 'url' => route('customer.profile.show', [], false), 'type' => 'صفحه'],
            ['title' => 'کیف پول', 'description' => 'موجودی، تراکنش ها و افزایش اعتبار', 'url' => route('customer.wallet.show', [], false), 'type' => 'صفحه'],
            ['title' => 'افزایش اعتبار', 'description' => 'شارژ سریع کیف پول', 'url' => route('customer.wallet.show', ['topup' => 1], false), 'type' => 'عملیات'],
            ['title' => 'صورتحساب ها', 'description' => 'بایگانی و جزئیات فاکتورهای ماهانه', 'url' => route('customer.invoices.index', [], false), 'type' => 'صفحه'],
        ];

        if ($canViewVms) {
            $searchBaseItems[] = ['title' => 'سرورها', 'description' => 'فهرست ماشین های ابری و وضعیت آنها', 'url' => route('customer.servers.index', [], false), 'type' => 'صفحه'];

            if ($canManageVms) {
                $searchBaseItems[] = ['title' => 'ساخت ماشین', 'description' => 'انتخاب پلن، سیستم عامل و دیتاسنتر', 'url' => route('customer.servers.create', [], false), 'type' => 'عملیات'];
            }

            $searchBaseItems[] = ['title' => 'بکاپ ها', 'description' => 'بکاپ دستی، برنامه بکاپ خودکار و نگهداری نسخه ها', 'url' => route('customer.backups.index', [], false), 'type' => 'صفحه'];
            $searchBaseItems[] = ['title' => 'مانیتورینگ', 'description' => 'نمودار مصرف CPU، RAM، شبکه و وضعیت بکاپ', 'url' => route('customer.monitoring.index', [], false), 'type' => 'صفحه'];
        }
    @endphp

    @if (($wallet->balance ?? 0) < $walletRestrictionThreshold)
        <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-red-900 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[1600px] flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-xl bg-red-600 text-white">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path d="M12 8v5" stroke-linecap="round"/>
                            <path d="M12 17h.01" stroke-linecap="round"/>
                            <path d="M10.3 3.9h3.4L22 17.8A2 2 0 0 1 20.3 21H3.7A2 2 0 0 1 2 17.8L10.3 3.9Z" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-black tracking-[0.2em] text-red-700">نیاز به شارژ کیف پول</p>
                        <p class="mt-1 text-sm font-bold leading-7 text-red-800">
                            موجودی کیف پول این فضای کاری کافی نیست. فعلا فقط شارژ کیف پول، صورتحساب‌ها و تراکنش‌های مالی در دسترس است.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2 text-sm font-black text-white transition hover:bg-red-500">
                        رفتن به کیف پول
                    </a>
                    <a href="{{ route('customer.suspension.notice', [], false) }}" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-black text-red-700 transition hover:bg-red-100">
                        مشاهده توضیح
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div
        x-data="{
            sidebarOpen: false,
            searchOpen: false,
            searchQuery: '',
            walletOpen: false,
            profileOpen: false,
            searchItems: [],
            init() {
                const baseItems = @json($searchBaseItems);
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
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="pointer-events-none fixed inset-y-0 right-0 z-40 flex w-[min(86vw,280px)] translate-x-full flex-col overflow-y-auto border-l border-white/10 bg-[#031B4E] px-4 py-4 text-white shadow-2xl shadow-[#031B4E]/40 transition-transform duration-200 lg:pointer-events-auto lg:static lg:w-[230px] lg:translate-x-0 lg:overflow-visible lg:px-0 lg:shadow-none"
            :class="{ '!pointer-events-auto !translate-x-0': sidebarOpen }"
            aria-label="منوی مشتری"
        >
            <div class="flex items-center justify-between lg:px-4">
                <a href="{{ route('dashboard', [], false) }}" class="flex items-center gap-2.5">
                    <img src="{{ asset("assets/images/aviato_icon_color.png") }}" class="w-10" alt="Aviato Logo">
                    {{-- <span class="grid size-9 place-items-center rounded-full border border-white/15 bg-white/10 text-base font-black text-white shadow-sm shadow-black/20">آ</span> --}}
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
                <div class="px-3 pt-2">
                    <form method="POST" action="{{ route('customer.projects.switch', [], false) }}">
                        @csrf
                        <select name="project_id" onchange="this.form.submit()" class="w-full rounded-md border border-white/10 bg-[#08245A] px-3 py-1.5 text-xs font-black text-white outline-none">
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((int) $activeProject->id === (int) $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
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
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
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



            <div class="mt-5 border-t border-white/10 pt-4 lg:px-3">
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
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-3 shadow-sm shadow-slate-200/50 backdrop-blur sm:px-4 md:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between gap-2">
                    <button
                        type="button"
                        class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm shadow-slate-200/50 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] lg:hidden"
                        @click="sidebarOpen = true"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-label="باز کردن منو"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <div class="shrink-0 sm:min-w-0 sm:flex-1 md:max-w-md">
                        <button
                            type="button"
                            @click="openSearch()"
                            class="group flex size-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 text-sm text-slate-500 shadow-sm shadow-slate-200/40 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:h-11 sm:w-full sm:justify-start sm:px-3"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-slate-500 shadow-sm shadow-slate-200/50 transition group-hover:text-[#0069FF]">
                                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="hidden truncate text-right font-bold sm:block">جستجو...</span>
                            <span class="mr-auto hidden items-center gap-1 text-[11px] font-black text-slate-400 lg:flex">
                                <kbd class="rounded-md border border-slate-200 bg-white px-1.5 py-0.5 shadow-sm">Ctrl</kbd>
                                <kbd class="rounded-md border border-slate-200 bg-white px-1.5 py-0.5 shadow-sm">K</kbd>
                            </span>
                        </button>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($canManageVms)
                        <a href="{{ route('customer.servers.create', [], false) }}" class="group inline-flex size-11 items-center justify-center rounded-xl border border-[#00A67E]/20 bg-[#00A67E] text-white shadow-lg shadow-[#00A67E]/20 transition hover:-translate-y-0.5 hover:bg-[#008F6E] hover:shadow-[#00A67E]/30 sm:w-auto sm:px-3.5" aria-label="ساخت ماشین">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            <span class="hidden pr-2 text-sm font-black sm:inline">ساخت</span>
                        </a>
                        @endif

                        <div class="relative">
                            <button
                                type="button"
                                @click="walletOpen = !walletOpen; profileOpen = false; searchOpen = false"
                                class="group flex h-11 max-w-[9.5rem] items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 text-slate-600 shadow-sm shadow-slate-200/50 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:max-w-none sm:px-3"
                                aria-label="کیف پول"
                                :aria-expanded="walletOpen.toString()"
                            >
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $balanceIsNegative ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }} transition group-hover:bg-white">
                                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm12 3h4v5h-4a2.5 2.5 0 0 1 0-5Z" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="min-w-0 text-right">
                                    <span class="block truncate text-xs font-black leading-4 {{ $balanceIsNegative ? 'text-red-600' : 'text-slate-800' }}">{{ $wallets->format($wallet->balance) }}</span>
                                    <span class="hidden text-[10px] font-black leading-3 text-slate-400 sm:block">کیف پول</span>
                                </span>
                            </button>

                            <div
                                x-cloak
                                x-show="walletOpen"
                                x-transition
                                @click.away="walletOpen = false"
                                class="absolute left-0 top-14 z-50 w-72 rounded-xl border border-slate-200 bg-white p-4 text-right shadow-2xl shadow-slate-950/10"
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
                                class="group flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-slate-600 shadow-sm shadow-slate-200/50 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] sm:px-2.5"
                                aria-label="پروفایل"
                                :aria-expanded="profileOpen.toString()"
                            >
                                <span class="relative grid size-8 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-950 text-sm font-black text-white shadow-sm">
                                    {{ $customerInitial }}
                                    <span class="absolute inset-x-0 bottom-0 h-1 bg-[#00A67E]"></span>
                                </span>
                                <svg class="hidden size-4 text-slate-400 transition group-hover:text-[#0069FF] sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>

                            <div
                                x-cloak
                                x-show="profileOpen"
                                x-transition
                                @click.away="profileOpen = false"
                                class="absolute left-0 top-14 z-50 w-72 rounded-xl border border-slate-200 bg-white p-4 text-right shadow-2xl shadow-slate-950/10"
                            >
                                <div class="flex items-center gap-3 border-b border-slate-200 pb-4">
                                    <span class="relative grid size-11 place-items-center overflow-hidden rounded-xl bg-slate-950 text-lg font-black text-white">
                                        {{ $customerInitial }}
                                        <span class="absolute inset-x-0 bottom-0 h-1.5 bg-[#00A67E]"></span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-black text-slate-950">{{ $customer->name }}</p>
                                        <p class="truncate text-sm text-slate-500">{{ $customer->email ?? $customer->phone ?? 'حساب مشتری' }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 space-y-1">
                                    <a href="{{ route('dashboard', [], false) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">داشبورد</a>
                                    <a href="{{ route('customer.profile.show', [], false) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">پروفایل</a>
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
                                {{-- <p class="text-xs font-black text-[#0069FF]">{{ $customer->name }}</p> --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="truncate text-2xl font-black tracking-normal text-slate-950">@yield('header_title', 'پنل مشتریان')</h1>
                                    <a href="{{ route('customer.projects.show', $activeProject, false) }}" class="inline-flex max-w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-black text-slate-600 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                                        <span class="size-2 rounded-full bg-[#00A67E]"></span>
                                        <span class="truncate">فضای کاری: {{ $activeProject->name }}</span>
                                        <span class="hidden text-slate-400 sm:inline">{{ ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'][$activeMembership?->role ?? 'member'] ?? 'عضو' }}</span>
                                    </a>
                                </div>
                                <p class="mt-1 max-w-3xl text-sm leading-7 text-slate-500">@yield('header_subtitle', 'نمای کامل کیف پول، کارکرد و صورتحساب ها')</p>
                            </div>
                        </div>
                        {{-- <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex w-fit shrink-0 items-center justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                            ساخت ماشین
                        </a> --}}
                    </div>
                </div>

                @if (session('status'))
                    <div class="mb-6 w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 w-full rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
                @endif
                @if (session('provisioning_password'))
                    <div class="mb-6 w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                        Password اولیه فقط همین حالا نمایش داده می‌شود:
                        <span dir="ltr">{{ session('provisioning_password') }}</span>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 w-full rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </div>

            <script type="application/json" id="customer-search-data">@yield('search_data', '[]')</script>
        </main>
    </div>
</body>
</html>
