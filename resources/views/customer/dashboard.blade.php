<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پنل مشتریان</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="overflow-x-hidden bg-[#F5F7FB] text-slate-950">
    @php
        $customerInitial = mb_substr($customer->name ?: 'ک', 0, 1);
        $vmRows = [
            ['name' => 'web-prod-01', 'ip' => '185.143.232.18', 'region' => 'تهران ۱', 'plan' => '۲ vCPU / ۴GB', 'status' => 'روشن', 'statusClass' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500', 'cpu' => '۴۲٪', 'ram' => '۶۱٪', 'cost' => '۴۹۰٬۰۰۰'],
            ['name' => 'db-main', 'ip' => '185.143.232.41', 'region' => 'شیراز ۱', 'plan' => '۴ vCPU / ۸GB', 'status' => 'روشن', 'statusClass' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500', 'cpu' => '۳۱٪', 'ram' => '۷۴٪', 'cost' => '۹۸۰٬۰۰۰'],
            ['name' => 'staging-api', 'ip' => '49.13.88.104', 'region' => 'فرانکفورت', 'plan' => '۱ vCPU / ۲GB', 'status' => 'آماده', 'statusClass' => 'bg-blue-50 text-blue-700', 'dot' => 'bg-blue-500', 'cpu' => '۱۲٪', 'ram' => '۳۳٪', 'cost' => '۲۹۰٬۰۰۰'],
        ];
        $notifications = [
            ['title' => 'بکاپ web-prod-01 آماده شد', 'body' => 'آخرین بکاپ روزانه بدون خطا ذخیره شد.', 'tone' => 'bg-blue-500'],
            ['title' => 'مصرف RAM ماشین db-main بالا است', 'body' => 'اگر این روند ادامه دارد پلن رشد را بررسی کنید.', 'tone' => 'bg-amber-500'],
            ['title' => 'کیف پول برای ۹ روز کافی است', 'body' => 'شارژ خودکار هنوز فعال نشده است.', 'tone' => 'bg-red-500'],
        ];
        $plans = [
            ['name' => 'شروع', 'spec' => '۱ vCPU / ۲GB RAM / ۴۰GB NVMe', 'price' => '۲۹۰٬۰۰۰'],
            ['name' => 'وب', 'spec' => '۲ vCPU / ۴GB RAM / ۸۰GB NVMe', 'price' => '۴۹۰٬۰۰۰'],
            ['name' => 'رشد', 'spec' => '۴ vCPU / ۸GB RAM / بکاپ روزانه', 'price' => '۹۸۰٬۰۰۰'],
        ];
        $images = ['Ubuntu 24.04 LTS', 'Debian 12', 'Rocky Linux 9'];
        $regions = ['تهران ۱', 'شیراز ۱', 'فرانکفورت'];
    @endphp

    <div
        x-data="{
            sidebarOpen: false,
            createOpen: false,
            notificationsOpen: false,
            walletOpen: false,
            profileOpen: false,
            searchOpen: false,
            createStep: 1,
            selectedImage: 'Ubuntu 24.04 LTS',
            selectedRegion: 'تهران ۱',
            selectedPlan: 'وب',
            closePanels() {
                this.notificationsOpen = false;
                this.walletOpen = false;
                this.profileOpen = false;
                this.searchOpen = false;
            },
            openCreate() {
                this.createOpen = true;
                this.closePanels();
            }
        }"
        @keydown.window.escape="closePanels(); createOpen = false; sidebarOpen = false"
        class="min-h-screen lg:flex"
    >
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-30 bg-slate-950/35 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="fixed inset-y-0 right-0 z-40 hidden w-72 translate-x-full flex-col bg-[#061A33] px-3 py-4 text-white shadow-2xl shadow-[#061A33]/30 transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between px-2">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-lg bg-[#2563EB] text-lg font-black text-white shadow-lg shadow-blue-950/30">آ</span>
                    <span>
                        <span class="block text-base font-black text-white">آویاتو</span>
                        <span class="block text-xs text-blue-100/60">پنل مشتریان</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-lg border border-white/10 text-blue-100/80 transition hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="بستن منو"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="mt-7 space-y-5 text-sm font-bold">
                @php
                    $groups = [
                        'اصلی' => [
                            ['label' => 'داشبورد', 'active' => true, 'count' => null, 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
                            ['label' => 'ساخت سریع VM', 'active' => false, 'count' => null, 'icon' => 'M12 5v14M5 12h14'],
                        ],
                        'زیرساخت' => [
                            ['label' => 'ماشین‌ها', 'active' => false, 'count' => '۳', 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                            ['label' => 'بکاپ‌ها', 'active' => false, 'count' => '۲', 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                            ['label' => 'شبکه و IP', 'active' => false, 'count' => null, 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4'],
                        ],
                        'مالی' => [
                            ['label' => 'کیف پول', 'active' => false, 'count' => null, 'icon' => 'M4 7h16v12H4V7Zm13 4h3v4h-3a2 2 0 0 1 0-4Z'],
                            ['label' => 'صورتحساب', 'active' => false, 'count' => '۱', 'icon' => 'M7 4h10v16H7V4Zm3 4h4m-4 4h4m-4 4h2'],
                        ],
                        'پشتیبانی' => [
                            ['label' => 'تیکت‌ها', 'active' => false, 'count' => null, 'icon' => 'M4 5h16v11H7l-3 3V5Zm5 5h6m-6 3h4'],
                            ['label' => 'تنظیمات', 'active' => false, 'count' => null, 'icon' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0-5v3m0 12v3M3 12h3m12 0h3'],
                        ],
                    ];
                @endphp

                @foreach ($groups as $group => $items)
                    <div>
                        <p class="px-3 text-[11px] font-black text-blue-100/40">{{ $group }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($items as $item)
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-right transition {{ $item['active'] ? 'bg-[#F5F7FB] text-[#061A33]' : 'text-blue-100/70 hover:bg-[#082B55] hover:text-white' }}"
                                    @click="{{ $item['label'] === 'ساخت سریع VM' ? 'openCreate()' : 'sidebarOpen = false' }}"
                                >
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                        <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if($item['count'])
                                        <span class="rounded-md bg-white/10 px-2 py-0.5 text-[11px] font-black {{ $item['active'] ? 'bg-[#061A33]/10 text-[#061A33]' : 'text-blue-100' }}">{{ $item['count'] }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="mt-auto rounded-lg border border-white/10 bg-white/[0.07] p-4">
                <p class="text-xs font-black text-blue-100/60">کیف پول</p>
                <p class="mt-2 text-xl font-black text-white">{{ $wallets->format($wallet->balance) }}</p>
                <div class="mt-3 h-1.5 rounded-full bg-white/10">
                    <div class="h-1.5 rounded-full {{ $wallet->balance < 0 ? 'bg-red-500' : 'bg-[#2563EB]' }}" style="width: 68%"></div>
                </div>
                <button type="button" class="mt-4 w-full rounded-lg bg-white px-4 py-2 text-sm font-black text-[#061A33] transition hover:bg-blue-50">
                    افزایش اعتبار
                </button>
            </div>
        </aside>

        <main class="w-full min-w-0 flex-1">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm shadow-slate-200/60 backdrop-blur md:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 lg:hidden"
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
                            type="search"
                            placeholder="جستجو در VM، IP، فاکتور، تیکت..."
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-20 pr-11 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            @focus="searchOpen = true"
                        >
                        <div class="pointer-events-none absolute inset-y-0 left-2 hidden items-center gap-1 text-[11px] font-bold text-slate-500 sm:flex">
                            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 shadow-sm">/</kbd>
                        </div>
                        <div
                            x-cloak
                            x-show="searchOpen"
                            x-transition
                            class="absolute right-0 top-full z-30 mt-2 w-full max-w-2xl rounded-lg border border-slate-200 bg-white p-2 shadow-xl shadow-slate-950/10"
                        >
                            <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-right text-sm font-black text-slate-800 hover:bg-blue-50">جستجو در ماشین‌ها و IPها</button>
                            <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-right text-sm font-black text-slate-800 hover:bg-blue-50">رفتن به صورتحساب‌ها</button>
                            <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-right text-sm font-black text-slate-800 hover:bg-blue-50">باز کردن تیکت پشتیبانی</button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="hidden shrink-0 items-center gap-2 rounded-lg bg-[#2563EB] px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-[#1D4ED8] sm:inline-flex"
                        @click="openCreate()"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                        </svg>
                        Create VM
                    </button>

                    <div class="relative shrink-0" @click.outside="notificationsOpen = false">
                        <button
                            type="button"
                            class="relative grid size-11 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50"
                            @click="notificationsOpen = !notificationsOpen; walletOpen = false; profileOpen = false; searchOpen = false"
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
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="font-black text-slate-900">اعلان‌ها</p>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @foreach ($notifications as $notification)
                                    <div class="flex gap-3 p-4">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $notification['tone'] }}"></span>
                                        <div>
                                            <p class="text-sm font-black text-slate-900">{{ $notification['title'] }}</p>
                                            <p class="mt-1 text-xs leading-6 text-slate-500">{{ $notification['body'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="relative shrink-0" @click.outside="walletOpen = false">
                        <button
                            type="button"
                            class="hidden h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-right transition hover:border-blue-200 hover:bg-blue-50 md:flex"
                            @click="walletOpen = !walletOpen; notificationsOpen = false; profileOpen = false; searchOpen = false"
                        >
                            <span class="text-xs font-black text-slate-500">Wallet</span>
                            <span class="text-sm font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</span>
                            <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div
                            x-cloak
                            x-show="walletOpen"
                            x-transition
                            class="absolute left-0 top-full z-30 mt-2 w-80 rounded-lg border border-slate-200 bg-white p-4 text-right shadow-xl shadow-slate-950/10"
                        >
                            <p class="text-sm font-black text-slate-500">موجودی کیف پول</p>
                            <p class="mt-2 text-2xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                            <p class="mt-2 text-xs font-bold text-slate-500">وضعیت: {{ $wallet->is_locked ? 'قفل شده' : 'فعال' }}</p>
                            <button type="button" class="mt-4 w-full rounded-lg bg-[#2563EB] px-4 py-2.5 text-sm font-black text-white hover:bg-[#1D4ED8]">
                                افزایش اعتبار
                            </button>
                        </div>
                    </div>

                    <div class="relative shrink-0" @click.outside="profileOpen = false">
                        <button
                            type="button"
                            class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-slate-700 transition hover:border-blue-200 hover:bg-blue-50"
                            @click="profileOpen = !profileOpen; notificationsOpen = false; walletOpen = false; searchOpen = false"
                            aria-label="پروفایل"
                        >
                            <span class="grid size-8 place-items-center rounded-md bg-[#061A33] text-sm font-black text-white">{{ $customerInitial }}</span>
                            <span class="hidden max-w-24 truncate text-sm font-black text-slate-900 xl:block">{{ $customer->name }}</span>
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
                                <p class="font-black text-slate-900">{{ $customer->name }}</p>
                                <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $customer->email ?: $customer->phone ?: 'customer account' }}</p>
                            </div>
                            <div class="p-2">
                                <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-right text-sm font-bold text-slate-700 hover:bg-blue-50">تنظیمات حساب</button>
                                <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-right text-sm font-bold text-slate-700 hover:bg-blue-50">کلیدهای SSH</button>
                                <form method="POST" action="{{ route('customer.logout', [], false) }}" class="mt-1 border-t border-slate-100 pt-2">
                                    @csrf
                                    <button class="w-full rounded-lg px-3 py-2.5 text-right text-sm font-black text-red-600 transition hover:bg-red-50">
                                        خروج از پنل
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="px-4 py-6 md:px-6 lg:px-8">
                <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="min-w-0 space-y-6">
                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-[#1D4ED8]">سلام {{ $customer->name }}</p>
                                    <h1 class="mt-2 text-2xl font-black text-slate-950 md:text-3xl">ماشین ابری بعدی را در سه قدم بسازید</h1>
                                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">سیستم‌عامل، موقعیت و پلن را انتخاب کنید. باقی مسیر باید واضح و بدون حدس باشد.</p>
                                </div>
                                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2563EB] px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#1D4ED8]" @click="openCreate()">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                    </svg>
                                    Create VM
                                </button>
                            </div>
                            <div class="mt-6 grid gap-3 md:grid-cols-3">
                                @foreach ([['۱', 'انتخاب سیستم‌عامل', 'Ubuntu، Debian یا Rocky'], ['۲', 'انتخاب منابع', 'پلن آماده یا منابع سفارشی'], ['۳', 'دریافت IP', 'اتصال بعد از آماده شدن VM']] as $step)
                                    <div class="rounded-lg bg-slate-50 p-4">
                                        <p class="text-sm font-black text-slate-950">{{ $step[0] }}. {{ $step[1] }}</p>
                                        <p class="mt-2 text-xs leading-6 text-slate-500">{{ $step[2] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            @foreach ([
                                ['label' => 'VM فعال', 'value' => '۳', 'hint' => 'همه آماده اتصال', 'tone' => 'text-[#1D4ED8]'],
                                ['label' => 'هزینه ماهانه', 'value' => '۱.۷۶M', 'hint' => 'تومان تخمینی', 'tone' => 'text-slate-950'],
                                ['label' => 'کیف پول', 'value' => $wallets->format($wallet->balance), 'hint' => $wallet->is_locked ? 'قفل شده' : 'فعال', 'tone' => $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950'],
                                ['label' => 'ترافیک', 'value' => '۱.۸TB', 'hint' => 'از ۵TB ماهانه', 'tone' => 'text-slate-950'],
                                ['label' => 'بکاپ', 'value' => '۲/۳', 'hint' => 'ماشین با بکاپ فعال', 'tone' => 'text-amber-600'],
                            ] as $stat)
                                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                    <p class="text-xs font-black text-slate-500">{{ $stat['label'] }}</p>
                                    <p class="mt-3 truncate text-xl font-black {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                                    <p class="mt-2 text-xs font-bold text-slate-400">{{ $stat['hint'] }}</p>
                                </article>
                            @endforeach
                        </section>

                        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-lg font-black text-slate-950">ماشین‌های شما</h2>
                                    <p class="mt-1 text-sm text-slate-500">وضعیت، مصرف و هزینه هر VM</p>
                                </div>
                                <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-black text-[#1D4ED8]" @click="openCreate()">ساخت VM جدید</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-right text-sm">
                                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                                        <tr>
                                            <th class="px-5 py-4">ماشین</th>
                                            <th class="px-5 py-4">موقعیت</th>
                                            <th class="px-5 py-4">پلن</th>
                                            <th class="px-5 py-4">مصرف</th>
                                            <th class="px-5 py-4">وضعیت</th>
                                            <th class="px-5 py-4">هزینه</th>
                                            <th class="px-5 py-4">اقدام</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($vmRows as $vm)
                                            <tr class="hover:bg-blue-50/40">
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <p class="font-black text-slate-950" dir="ltr">{{ $vm['name'] }}</p>
                                                    <p class="mt-1 font-mono text-xs text-slate-500" dir="ltr">{{ $vm['ip'] }}</p>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['region'] }}</td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['plan'] }}</td>
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <p class="text-xs font-bold text-slate-500">CPU {{ $vm['cpu'] }} / RAM {{ $vm['ram'] }}</p>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black {{ $vm['statusClass'] }}">
                                                        <span class="size-2 rounded-full {{ $vm['dot'] }}"></span>
                                                        {{ $vm['status'] }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 font-black text-slate-900">{{ $vm['cost'] }} <span class="text-xs text-slate-400">تومان</span></td>
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-[#1D4ED8] hover:bg-blue-50">مدیریت</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black text-slate-950">ساخت سریع</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-500">رایج‌ترین انتخاب برای شروع پروژه وب.</p>
                            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                                <p class="font-black text-[#0B2550]">Ubuntu 24.04 در تهران ۱</p>
                                <p class="mt-2 text-sm text-slate-600">۲ vCPU / ۴GB RAM / ۸۰GB NVMe</p>
                                <p class="mt-3 text-lg font-black text-[#1D4ED8]">۴۹۰٬۰۰۰ <span class="text-xs text-slate-500">تومان / ماه</span></p>
                            </div>
                            <button type="button" class="mt-4 w-full rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-black text-white hover:bg-[#1D4ED8]" @click="openCreate()">
                                ساخت با این پیشنهاد
                            </button>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h2 class="font-black text-slate-950">کیف پول</h2>
                                <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $wallet->is_locked ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
                            </div>
                            <p class="mt-4 text-2xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                            <p class="mt-2 text-sm text-slate-500">هزینه تخمینی ۹ روز آینده: ۵۲۰٬۰۰۰ تومان</p>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black text-slate-950">آخرین تراکنش‌ها</h2>
                            <div class="mt-4 space-y-3">
                                @forelse($transactions as $transaction)
                                    <div class="rounded-lg border border-slate-200 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-black text-slate-900">{{ $transaction->description }}</p>
                                            <span class="shrink-0 text-xs font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $wallets->format($transaction->amount) }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $transaction->created_at?->format('Y/m/d H:i') }}</p>
                                    </div>
                                @empty
                                    <div class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">هنوز تراکنشی ثبت نشده است.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black text-slate-950">فعالیت اخیر</h2>
                            <div class="mt-4 space-y-4">
                                @foreach (['بکاپ روزانه web-prod-01 ساخته شد.', 'IP ماشین staging-api آماده اتصال است.', 'مصرف RAM در db-main به ۷۴٪ رسید.'] as $activity)
                                    <div class="flex gap-3">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full bg-[#2563EB]"></span>
                                        <p class="text-sm leading-7 text-slate-600">{{ $activity }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </aside>
                </section>
            </div>
        </main>

        <div
            x-cloak
            x-show="createOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 grid place-items-center bg-slate-950/45 p-4"
        >
            <div
                x-show="createOpen"
                x-transition
                @click.outside="createOpen = false"
                class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl"
            >
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">ساخت VM جدید</h2>
                        <p class="mt-2 text-sm text-slate-500">سه انتخاب ساده کافی است.</p>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600" @click="createOpen = false" aria-label="بستن">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="grid gap-0 md:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="border-b border-slate-200 bg-slate-50 p-4 md:border-b-0 md:border-l">
                        @foreach ([1 => 'سیستم‌عامل', 2 => 'موقعیت', 3 => 'پلن'] as $step => $label)
                            <button type="button" class="mb-2 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-right text-sm font-black transition" :class="createStep === {{ $step }} ? 'bg-[#061A33] text-white' : 'text-slate-600 hover:bg-white'" @click="createStep = {{ $step }}">
                                <span class="grid size-6 place-items-center rounded-md text-xs" :class="createStep === {{ $step }} ? 'bg-white/15' : 'bg-slate-200'">{{ $step }}</span>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="p-5">
                        <div x-show="createStep === 1">
                            <h3 class="font-black text-slate-950">سیستم‌عامل را انتخاب کنید</h3>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                @foreach ($images as $image)
                                    <button type="button" class="rounded-lg border p-4 text-right transition" :class="selectedImage === '{{ $image }}' ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100' : 'border-slate-200 hover:bg-slate-50'" @click="selectedImage = '{{ $image }}'">
                                        <span class="block font-black text-slate-950">{{ $image }}</span>
                                        <span class="mt-2 block text-xs text-slate-500">آماده نصب سریع</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="createStep === 2">
                            <h3 class="font-black text-slate-950">موقعیت دیتاسنتر را انتخاب کنید</h3>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                @foreach ($regions as $region)
                                    <button type="button" class="rounded-lg border p-4 text-right transition" :class="selectedRegion === '{{ $region }}' ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100' : 'border-slate-200 hover:bg-slate-50'" @click="selectedRegion = '{{ $region }}'">
                                        <span class="block font-black text-slate-950">{{ $region }}</span>
                                        <span class="mt-2 block text-xs text-slate-500">IP آماده اتصال</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="createStep === 3">
                            <h3 class="font-black text-slate-950">پلن مناسب را انتخاب کنید</h3>
                            <div class="mt-4 grid gap-3 md:grid-cols-3">
                                @foreach ($plans as $plan)
                                    <button type="button" class="rounded-lg border p-4 text-right transition" :class="selectedPlan === '{{ $plan['name'] }}' ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100' : 'border-slate-200 hover:bg-slate-50'" @click="selectedPlan = '{{ $plan['name'] }}'">
                                        <span class="block font-black text-slate-950">{{ $plan['name'] }}</span>
                                        <span class="mt-2 block min-h-10 text-xs leading-5 text-slate-500">{{ $plan['spec'] }}</span>
                                        <span class="mt-3 block font-black text-[#1D4ED8]">{{ $plan['price'] }} تومان</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-black text-slate-950">خلاصه انتخاب</p>
                            <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-3">
                                <p>OS: <span class="font-black text-slate-950" x-text="selectedImage"></span></p>
                                <p>Region: <span class="font-black text-slate-950" x-text="selectedRegion"></span></p>
                                <p>Plan: <span class="font-black text-slate-950" x-text="selectedPlan"></span></p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700" @click="createStep = Math.max(1, createStep - 1)">
                                قبلی
                            </button>
                            <div class="flex gap-3">
                                <button type="button" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700" @click="createOpen = false">انصراف</button>
                                <button type="button" class="rounded-lg bg-[#2563EB] px-5 py-3 text-sm font-black text-white hover:bg-[#1D4ED8]" @click="createStep < 3 ? createStep++ : createOpen = false" x-text="createStep < 3 ? 'ادامه' : 'ثبت درخواست ساخت'"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
