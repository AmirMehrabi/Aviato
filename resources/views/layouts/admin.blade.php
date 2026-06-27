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
    @php
        $adminUser = auth('admin')->user();
        $adminNotifications = $adminUser?->notifications()->latest()->limit(5)->get() ?? collect();
        $adminNotificationsCount = $adminUser?->notifications()->count() ?? 0;
        $adminUnreadNotificationsCount = $adminUser?->unreadNotifications()->count() ?? 0;
        $adminNotificationItems = $adminNotifications->map(fn ($notification): array => [
            'id' => $notification->id,
            'title' => data_get($notification->data, 'title', 'اعلان'),
            'body' => data_get($notification->data, 'body', ''),
            'url' => data_get($notification->data, 'url', route('admin.tickets.index')),
            'read' => (bool) $notification->read_at,
        ])->values()->all();
    @endphp
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('adminLayout', (config = {}) => ({
            sidebarOpen: false,
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
        x-data="adminLayout({ notificationUnreadCount: {{ $adminUnreadNotificationsCount }} })"
        @admin-notification-unread-changed.window="notificationUnreadCount = $event.detail.count"
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
                                ['label' => 'داشبورد', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'M3 3h8v8H3V3Zm10 0h8v8h-8V3ZM3 13h8v8H3v-8Zm10 0h8v8h-8v-8Z'],
                            ],
                        ],
                        [
                            'label' => 'مدیریت',
                            'items' => [
                                ['label' => 'مشتریان', 'route' => 'admin.customers.index', 'active' => request()->routeIs('admin.customers.*'), 'icon' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                                ['label' => 'فضاهای کاری', 'route' => 'admin.projects.index', 'active' => request()->routeIs('admin.projects.*'), 'icon' => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z'],
                            ],
                        ],
                        [
                            'label' => 'زیرساخت',
                            'items' => [
                                ['label' => 'Proxmox', 'route' => 'admin.proxmox-servers.index', 'active' => request()->routeIs('admin.proxmox-servers.*'), 'icon' => 'M4 6h16v4H4V6Zm0 5h16v4H4v-4Zm0 5h16v4H4v-4Z'],
                                ['label' => 'Hetzner', 'route' => 'admin.hetzner-accounts.index', 'active' => request()->routeIs('admin.hetzner-accounts.*'), 'icon' => 'M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10Z'],
                                ['label' => 'Locations', 'route' => 'admin.infrastructure-locations.index', 'active' => request()->routeIs('admin.infrastructure-locations.*'), 'icon' => 'M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
                                ['label' => 'ماشین‌ها', 'route' => 'admin.virtual-machines.index', 'active' => request()->routeIs('admin.virtual-machines.*'), 'icon' => 'M4 5h16v11H4V5Zm4 14h8'],
                                ['label' => 'Cloud Images', 'route' => 'admin.cloud-images.index', 'active' => request()->routeIs('admin.cloud-images.*'), 'icon' => 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm3 10 2.5-3 2 2 2.5-3 4 4H7Z'],
                                ['label' => 'IP Pools', 'route' => 'admin.ip-pools.index', 'active' => request()->routeIs('admin.ip-pools.*'), 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M3 12h18'],
                            ],
                        ],
                        [
                            'label' => 'پشتیبانی',
                            'items' => [
                                ['label' => 'تیکت‌ها', 'route' => 'admin.tickets.index', 'active' => request()->routeIs('admin.tickets.*') || request()->routeIs('admin.support-teams.*') || request()->routeIs('admin.ticket-categories.*'), 'icon' => 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-5 4V5Z'],
                            ],
                        ],
                        [
                            'label' => 'صورتحساب',
                            'items' => [
                                ['label' => 'قیمت منابع', 'route' => 'admin.billing.rates.index', 'active' => request()->routeIs('admin.billing.rates.*'), 'icon' => 'M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z'],
                                ['label' => 'باندل‌ها', 'route' => 'admin.billing.bundles.index', 'active' => request()->routeIs('admin.billing.bundles.*'), 'icon' => 'M16.5 9.4 12 2 7.5 9.4 M3 9.4h18v12H3V9.4Z M7.5 2v7.4 M16.5 2v7.4'],
                                ['label' => 'فروشندگان', 'route' => 'admin.resellers.index', 'active' => request()->routeIs('admin.resellers.*'), 'icon' => 'M3 9l1.5-5h15L21 9M3 9v12h18V9M9 21v-6h6v6M9 9V5h6v4'],
                            ],
                        ],
                        [
                            'label' => 'سیستم',
                            'items' => [
                                ['label' => 'تنظیمات', 'route' => 'admin.settings.edit', 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v3m0-9V6m7.07 2.93-2.12 2.12M6.05 14.95l-2.12 2.12m0-12.14 2.12 2.12m8.9 4.9-2.12 2.12'],
                            ],
                        ],
                    ];
                @endphp
                @foreach ($navGroups as $group)
                    @if (isset($group['label']))
                        <div>
                            <span class="block border-t border-white/15 px-3 pb-1 pt-3 text-[11px] font-black uppercase tracking-wider text-white/60">{{ $group['label'] }}</span>
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'bg-[#004DB3] text-white shadow-sm' : 'text-white/80 hover:bg-white/15 hover:text-white' }}"
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
                                class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'bg-[#004DB3] text-white shadow-sm' : 'text-white/80 hover:bg-white/15 hover:text-white' }}"
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
                                placeholder="جستجو در مشتری، VM، IP..."
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
                        <button
                            type="button"
                            @click="notificationsOpen = !notificationsOpen; searchOpen = false; profileOpen = false"
                            class="relative flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
                            aria-label="اعلان‌ها"
                        >
                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span
                                x-cloak
                                x-show="notificationUnreadCount > 0"
                                class="absolute start-1.5 top-1.5 size-2 rounded-full bg-[#0069FF] ring-2 ring-white"
                            ></span>
                        </button>

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
                x-show="notificationsOpen"
                x-transition
                @click.away="notificationsOpen = false"
                class="absolute end-4 top-16 z-50 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl md:end-8"
            >
                <div
                    x-data="adminNotificationDropdown({
                        items: @js($adminNotificationItems),
                        unreadCount: {{ $adminUnreadNotificationsCount }},
                        markReadUrlTemplate: @js(route('admin.notifications.read', ['notification' => '__NOTIFICATION__'])),
                        markAllReadUrl: @js(route('admin.notifications.mark-all-read')),
                        csrf: @js(csrf_token()),
                    })"
                    class="flex h-full flex-col"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <h3 class="text-sm font-black text-slate-900">اعلان‌ها</h3>
                    <span
                        class="inline-flex items-center justify-center rounded-full bg-[#0069FF] px-2 py-0.5 text-[10px] font-black text-white"
                        x-text="unreadCount"
                        x-show="unreadCount > 0"
                    ></span>
                    </div>
                    <div class="max-h-72 overflow-y-auto p-2">
                        <template x-for="notification in items" :key="notification.id">
                            <div class="flex items-start gap-3 rounded-lg p-3 transition hover:bg-slate-50">
                                <button
                                    type="button"
                                    class="mt-1 size-2 shrink-0 rounded-full"
                                    :class="notification.read ? 'bg-slate-300' : 'bg-[#0069FF]'"
                                    x-show="!notification.read"
                                    :aria-label="`خواندن اعلان ${notification.title}`"
                                    @click.stop.prevent="markRead(notification.id)"
                                ></button>
                                <span
                                    class="mt-1 size-2 shrink-0 rounded-full bg-slate-300"
                                    x-show="notification.read"
                                ></span>
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-right"
                                    @click.prevent.stop="openNotification(notification)"
                                >
                                    <p class="text-sm font-bold text-slate-900" x-text="notification.title"></p>
                                    <p class="mt-0.5 text-xs leading-5 text-slate-500" x-text="notification.body"></p>
                                </button>
                                <button
                                    type="button"
                                    class="mt-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]"
                                    x-show="!notification.read"
                                    :disabled="markingIds[notification.id]"
                                    @click.stop.prevent="markRead(notification.id)"
                                >
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>خواندم</span>
                                </button>
                            </div>
                        </template>
                        <template x-if="items.length === 0">
                            <p class="px-3 py-8 text-center text-sm font-bold text-slate-400">اعلان جدیدی وجود ندارد.</p>
                        </template>
                    </div>
                    <div class="border-t border-slate-100 px-5 py-3" x-show="unreadCount > 0">
                        <button
                            type="button"
                            class="w-full text-center text-xs font-bold text-[#0069FF] transition hover:text-[#0050D0] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="markingAll"
                            @click.prevent="markAllRead()"
                        >خواندن همه</button>
                    </div>
                </div>
            </div>

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
                            <p class="text-sm font-black text-slate-900">امیر حسینی</p>
                            <p class="text-xs text-slate-500">مدیر سیستم</p>
                        </div>
                    </div>
                </div>
                <div class="p-2">
                    <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        <svg class="size-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v3m0-9V6m7.07 2.93-2.12 2.12M6.05 14.95l-2.12 2.12m0-12.14 2.12 2.12m8.9 4.9-2.12 2.12" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        تنظیمات
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
