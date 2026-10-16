<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'داشبورد آویاتو')</title>
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" crossorigin="use-credentials" href="{{ asset('favicons/site.webmanifest') }}">

    <meta name="theme-color" content="#0B6BFF">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="overflow-x-clip bg-[#F5F7FB] text-slate-950">
    @php
        $adminUser = auth('admin')->user();
        $adminUnreadNotificationsCount = $adminUser?->unreadNotifications()->count() ?? 0;
        $navGroups = \App\Support\AdminNavigation::groups($adminUser);
        $mainNavGroups = array_filter($navGroups, fn (array $group): bool => ! $group['secondary']);
        $secondaryNavGroups = array_filter($navGroups, fn (array $group): bool => $group['secondary']);
        $initialSidebarPanel = collect($secondaryNavGroups)->firstWhere('active', true)['id'] ?? 'main';
    @endphp
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('adminLayout', (config = {}) => ({
            sidebarOpen: false,
            sidebarAccountOpen: false,
            sidebarPanel: config.sidebarPanel || 'main',

            showSidebarPanel(panel) {
                this.sidebarAccountOpen = false;
                this.sidebarPanel = panel;
                this.$nextTick(() => {
                    this.$refs.adminSidebar.scrollTop = 0;
                    this.$refs[`sidebarBack-${panel}`]?.focus({ preventScroll: true });
                });
            },

            showMainSidebar() {
                this.sidebarAccountOpen = false;
                const previousPanel = this.sidebarPanel;
                this.sidebarPanel = 'main';
                this.$nextTick(() => {
                    this.$refs.adminSidebar.scrollTop = 0;
                    this.$refs[`sidebarTrigger-${previousPanel}`]?.focus();
                });
            },
            createOpen: false,
            period: 'روزانه',
            searchOpen: false,
            searchQuery: '',
            searchResults: [],
            searchLoading: false,
            searchTimer: null,
            activeIndex: -1,
            notificationsOpen: false,
            profileOpen: false,
            notificationUnreadCount: Number(config.notificationUnreadCount || 0),

            openSearch() {
                this.searchOpen = true;
                this.notificationsOpen = false;
                this.profileOpen = false;
                this.searchResults = [];
                this.activeIndex = -1;
                this.$nextTick(() => this.$refs.adminSearch?.focus());
            },

            closePanels() {
                this.sidebarAccountOpen = false;
                this.searchOpen = false;
                this.searchResults = [];
                this.notificationsOpen = false;
                this.profileOpen = false;
            },

            doSearch() {
                clearTimeout(this.searchTimer);
                const q = this.searchQuery.trim();
                if (q.length < 2) {
                    this.searchResults = [];
                    this.searchLoading = false;
                    return;
                }
                this.searchLoading = true;
                this.searchTimer = setTimeout(() => {
                    fetch('{{ route("admin.search") }}?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            this.searchResults = data.groups || [];
                            this.activeIndex = -1;
                        })
                        .finally(() => this.searchLoading = false);
                }, 300);
            },

            searchKeydown(e) {
                const total = this.searchResults.reduce((s, g) => s + g.items.length, 0);
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.activeIndex = this.activeIndex < total - 1 ? this.activeIndex + 1 : 0;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.activeIndex = this.activeIndex > 0 ? this.activeIndex - 1 : total - 1;
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const link = this.$refs.searchDropdown?.querySelector('[data-active]');
                    if (link) link.click();
                } else if (e.key === 'Escape') {
                    this.closePanels();
                }
            },

            searchItemIdx(groupIdx, itemIdx) {
                let idx = 0;
                for (let i = 0; i < groupIdx; i++) idx += this.searchResults[i].items.length;
                return idx + itemIdx;
            },

            highlight(text, query) {
                if (!query || !text) return text;
                const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return text.replace(new RegExp('(' + escaped + ')', 'gi'), '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>');
            },
        }));
    });
    </script>

    <div
        x-data="adminLayout({ notificationUnreadCount: {{ $adminUnreadNotificationsCount }}, sidebarPanel: @js($initialSidebarPanel) })"
        @notification-unread-changed.window="notificationUnreadCount = $event.detail.count"
        @notification-center-open.window="searchOpen = false; profileOpen = false"
        @keydown.window.ctrl.k.prevent="openSearch()"
        @keydown.window.meta.k.prevent="openSearch()"
        @keydown.window.escape="closePanels(); createOpen = false; sidebarOpen = false"
        @keydown.window="
            if ($event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                $event.preventDefault();
                openSearch();
            }
        "
        class="min-h-screen overflow-x-clip lg:flex"
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
            class="pointer-events-none fixed inset-y-0 right-0 z-40 flex w-[min(86vw,288px)] translate-x-full flex-col overflow-hidden border-l border-white/10 px-4 py-4 text-white shadow-2xl shadow-[#0069FF]/30 transition-[transform,background-color] duration-200 motion-reduce:transition-none lg:pointer-events-auto lg:sticky lg:top-0 lg:h-dvh lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none"
            :class="{ '!pointer-events-auto !translate-x-0': sidebarOpen }"
            data-initial-panel="{{ $initialSidebarPanel }}"
            style="background-color: {{ $initialSidebarPanel === 'main' ? '#0069FF' : '#073C8C' }}"
            :style="{ backgroundColor: sidebarPanel === 'main' ? '#0069FF' : '#073C8C' }"
            aria-label="منوی مدیریت"
        >
            <div class="flex shrink-0 items-center justify-between">
                <a href="{{ route(\App\Support\AdminAccess::landingRoute(auth('admin')->user())) }}" class="flex items-center gap-2.5">
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
                    <x-admin.nav-icon name="x" />
                </button>
            </div>

            @php
                $awaitingTickets = \App\Support\AdminAccess::canVisit($adminUser, 'admin.tickets.index')
                    ? \App\Models\Ticket::where('status', \App\Models\Ticket::STATUS_OPEN)->count()
                    : 0;
            @endphp
            <div x-ref="adminSidebar" class="admin-sidebar-scroll -mx-2 mt-5 min-h-0 flex-1 overflow-y-auto px-2 pb-1">
                <nav id="admin-nav-main" x-show="sidebarPanel === 'main'" x-cloak aria-label="منوی اصلی" class="space-y-3">
                    @foreach ($mainNavGroups as $group)
                        <div class="space-y-0.5">
                            @if ($group['label'])
                                <span class="block px-3 pb-1 text-[10px] font-bold text-white/55">{{ $group['label'] }}</span>
                            @endif
                            @foreach ($group['items'] as $item)
                                <x-admin.nav-link :item="$item" :awaiting-tickets="$awaitingTickets" />
                            @endforeach
                        </div>
                    @endforeach
                    @if ($secondaryNavGroups !== [])
                        <div class="space-y-0.5 border-t border-white/15 pt-3">
                            <span class="block px-3 pb-1 text-[10px] font-bold text-white/55">ابزارها و تنظیمات</span>
                            @foreach ($secondaryNavGroups as $group)
                                <button type="button"
                                    x-ref="sidebarTrigger-{{ $group['id'] }}"
                                    @click="showSidebarPanel(@js($group['id']))"
                                    :aria-expanded="(sidebarPanel === @js($group['id'])).toString()"
                                    aria-controls="admin-nav-{{ $group['id'] }}"
                                    class="group flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-right text-[13px] font-medium text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                                    <x-admin.nav-icon :name="$group['icon']" />
                                    <span class="min-w-0 flex-1">{{ $group['label'] }}</span>
                                    <x-admin.nav-icon name="chevron-left" size="xs" class="text-white/50" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </nav>
                @foreach ($secondaryNavGroups as $group)
                    <nav id="admin-nav-{{ $group['id'] }}"
                        x-show="sidebarPanel === @js($group['id'])" x-cloak
                        x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
                        x-transition:enter-start="opacity-0 -translate-x-2 motion-reduce:transform-none"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        aria-label="{{ $group['label'] }}">
                        <button type="button"
                            x-ref="sidebarBack-{{ $group['id'] }}"
                            @click="showMainSidebar()"
                            class="mb-5 flex min-h-10 w-full items-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-sm font-semibold text-white/90 transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                            <x-admin.nav-icon name="arrow-right" size="sm" />
                            <span>منوی اصلی</span>
                        </button>
                        <div class="mb-4 flex items-center gap-3 border-b border-white/15 px-3 pb-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/15 bg-white/5"><x-admin.nav-icon :name="$group['icon']" class="size-5" /></span>
                            <div>
                                <p class="text-[10px] font-medium text-white/55">ابزارها و تنظیمات</p>
                                <h2 class="mt-1 text-sm font-bold text-white">{{ $group['label'] }}</h2>
                            </div>
                        </div>
                        <div class="space-y-1">
                            @foreach ($group['items'] as $item)
                                <x-admin.nav-link :item="$item" />
                            @endforeach
                        </div>
                    </nav>
                @endforeach
                <div class="relative mt-5 border-t border-white/15 pt-3"
                    x-on:click.outside="sidebarAccountOpen = false"
                    x-on:keydown.escape.stop.prevent="sidebarAccountOpen = false; $refs.sidebarAccountTrigger.focus()">
                    <button type="button"
                        x-ref="sidebarAccountTrigger"
                        x-on:click="sidebarAccountOpen = !sidebarAccountOpen"
                        x-on:keydown.arrow-down.prevent="sidebarAccountOpen = true; $nextTick(() => $refs.sidebarAccountProfile.focus())"
                        x-on:keydown.arrow-up.prevent="sidebarAccountOpen = true; $nextTick(() => $refs.sidebarAccountLogout.focus())"
                        :aria-expanded="sidebarAccountOpen.toString()"
                        aria-controls="sidebar-account-dropdown"
                        class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-right transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/15 text-sm font-bold text-white" aria-hidden="true">{{ mb_substr(trim($adminUser->name), 0, 1) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-bold text-white">{{ $adminUser->name }}</span>
                            <span class="mt-0.5 block text-[11px] text-white/60">حساب کاربری</span>
                        </span>
                        <x-admin.nav-icon name="chevron-left" size="sm" class="rotate-90 text-white/60 transition-transform motion-reduce:transition-none" x-bind:class="sidebarAccountOpen ? '!-rotate-90' : ''" />
                    </button>
                    <div id="sidebar-account-dropdown"
                        x-show="sidebarAccountOpen" x-cloak
                        x-transition.opacity.duration.150ms
                        class="absolute inset-x-0 bottom-full z-20 mb-2 rounded-xl border border-white/15 bg-[#062F70] p-1.5 shadow-xl shadow-slate-950/25"
                        aria-label="حساب کاربری">
                        <a href="{{ route('admin.profile.edit') }}"
                            x-ref="sidebarAccountProfile"
                            x-on:click="if (window.innerWidth < 1024) sidebarOpen = false"
                            x-on:keydown.arrow-down.prevent="$refs.sidebarAccountLogout.focus()"
                            x-on:keydown.arrow-up.prevent="$refs.sidebarAccountLogout.focus()"
                            @if (request()->routeIs('admin.profile.*')) aria-current="page" @endif
                            class="flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium text-white/90 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                            <x-admin.nav-icon name="user-round" size="sm" />
                            <span>حساب کاربری من</span>
                        </a>
                        <form method="POST" action="{{ route('admin.logout') }}" class="mt-1 border-t border-white/10 pt-1">
                            @csrf
                            <button type="submit" x-ref="sidebarAccountLogout"
                                x-on:keydown.arrow-down.prevent="$refs.sidebarAccountProfile.focus()"
                                x-on:keydown.arrow-up.prevent="$refs.sidebarAccountProfile.focus()"
                                class="flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-right text-[13px] font-medium text-white/90 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                                <x-admin.nav-icon name="log-out" size="sm" />
                                <span>خروج از حساب</span>
                            </button>
                        </form>
                    </div>
                </div>
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

                    <div class="relative flex-1" x-data="{ focused: false }">
                        <div class="relative">
                            <svg class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                            </svg>
                            <input
                                x-ref="adminSearch"
                                x-model="searchQuery"
                                @input.debounce.300ms="doSearch()"
                                @focus="searchOpen = true; focused = true"
                                @click.outside="setTimeout(() => { searchOpen = false; focused = false }, 150)"
                                @keydown="searchKeydown($event)"
                                type="text"
                                placeholder="جستجوی صفحات، مشتری، VM، IP..."
                                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-4 pr-10 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-[#0069FF] focus:bg-white focus:outline-none md:max-w-md"
                            />
                            {{-- <div class="absolute left-3 top-1/2 hidden -translate-y-1/2 gap-1 text-[11px] font-black text-slate-400 md:flex" x-show="!searchQuery" x-transition>
                                <kbd class="rounded border border-slate-200 px-1.5 py-0.5">Ctrl</kbd>
                                <kbd class="rounded border border-slate-200 px-1.5 py-0.5">K</kbd>
                            </div> --}}
                        </div>
                        <div
                            x-ref="searchDropdown"
                            x-show="searchOpen && searchQuery.length >= 2"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="absolute left-0 top-full z-50 mt-2 w-full min-w-[320px] max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl md:left-auto md:right-0"
                        >
                            <template x-if="searchLoading">
                                <div class="flex items-center justify-center gap-2 px-4 py-6 text-sm text-slate-400">
                                    <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    جستجو...
                                </div>
                            </template>
                            <template x-if="!searchLoading && searchResults.length === 0 && searchQuery.length >= 2">
                                <div class="px-4 py-6 text-center text-sm text-slate-400">نتیجه‌ای یافت نشد</div>
                            </template>
                            <template x-if="!searchLoading && searchResults.length > 0">
                                <div class="max-h-80 overflow-y-auto py-2">
                                    <template x-for="(group, gIdx) in searchResults" :key="gIdx">
                                        <div>
                                            <div class="flex items-center gap-2 px-4 py-2">
                                                <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path :d="group.icon" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <span class="text-xs font-bold text-slate-400" x-text="group.label"></span>
                                                <span class="text-xs text-slate-300" x-text="group.items.length"></span>
                                            </div>
                                            <template x-for="(item, iIdx) in group.items" :key="iIdx">
                                                <a
                                                    :href="item.url"
                                                    :data-active="searchItemIdx(gIdx, iIdx) === activeIndex ? '' : null"
                                                    class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-slate-50"
                                                    :class="{ 'bg-[#EBF3FF]': searchItemIdx(gIdx, iIdx) === activeIndex }"
                                                >
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-2">
                                                            <span class="truncate text-sm font-bold text-slate-900" x-html="highlight(item.title, searchQuery)"></span>
                                                            <span x-show="item.badge" x-text="item.badge" class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-black" :class="item.badgeClass"></span>
                                                        </div>
                                                        <p class="mt-0.5 truncate text-xs text-slate-400" x-html="highlight(item.subtitle, searchQuery)"></p>
                                                    </div>
                                                    <svg class="size-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round"/></svg>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div x-show="!searchLoading && searchResults.length > 0" class="border-t border-slate-100 px-4 py-2.5">
                                <p class="text-[11px] text-slate-400"><kbd class="rounded border border-slate-200 px-1 py-0.5 font-bold">↑↓</kbd> ناوبری · <kbd class="rounded border border-slate-200 px-1 py-0.5 font-bold">Enter</kbd> انتخاب · <kbd class="rounded border border-slate-200 px-1 py-0.5 font-bold">Esc</kbd> بستن</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <x-notification-center
                            :feed-url="route('admin.notifications.index')"
                            :mark-read-url-template="route('admin.notifications.read', ['notification' => '__NOTIFICATION__'])"
                            :mark-all-read-url="route('admin.notifications.mark-all-read')"
                            :dismiss-url-template="route('admin.notifications.dismiss', ['notification' => '__NOTIFICATION__'])"
                            :clear-read-url="route('admin.notifications.clear-read')"
                            :unread-count="$adminUnreadNotificationsCount"
                            variant="round"
                        />

                        <button
                            type="button"
                            @click="profileOpen = !profileOpen; searchOpen = false; notificationsOpen = false"
                            class="flex size-10 items-center justify-center rounded-full border border-slate-200 bg-[#0069FF] text-xs font-black text-white transition hover:bg-[#0050D0]"
                            aria-label="پروفایل"
                        >
                            ا
                        </button>
                    </div>
                </div>
            </header>

            <div
                x-cloak
                x-show="profileOpen"
                x-transition
                @click.away="profileOpen = false"
                class="absolute end-4 top-16 z-50 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl md:end-8"
            >
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-full bg-[#0069FF] text-sm font-black text-white">ا</span>
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ $adminUser?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $adminUser?->role?->label() }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-2">
                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        <svg class="size-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M12 8V5 M12 16v3 M8 12H5 M16 12h3 M9.2 9.2l-1.8-1.8 M14.8 14.8l1.8 1.8 M9.2 14.8l-1.8 1.8 M14.8 9.2l1.8-1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        حساب من
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-right text-sm font-bold text-red-600 transition hover:bg-red-50">
                            <svg class="size-[18px] text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            خروج از حساب
                        </button>
                    </form>
                </div>
            </div>

            @yield('content')

            @adminRoute('admin.virtual-machines.create')
            <div
                x-cloak
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
                            @adminAbility('billing.read')
                            <p class="text-left text-lg font-black text-[#0069FF]">۴۹۰٬۰۰۰<br><span class="text-xs text-slate-500">تومان / ماه</span></p>
                            @endadminAbility
                        </div>
                    </div>
                    <a href="{{ route('admin.virtual-machines.create') }}" class="mt-5 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white hover:bg-[#0050D0]">
                        ادامه ساخت ماشین
                    </a>
                </div>
            </div>
            @endadminRoute

        </main>
    </div>
</body>
</html>
