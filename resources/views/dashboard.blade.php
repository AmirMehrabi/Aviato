<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>داشبورد آویاتو</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            class="fixed inset-y-0 right-0 z-40 hidden w-72 translate-x-full flex-col border-l border-slate-200 bg-white px-5 py-5 shadow-xl transition-transform duration-200 lg:static lg:flex lg:translate-x-0 lg:shadow-none"
            :class="{ '!flex translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-[#105D52] text-lg font-black text-white">آ</span>
                    <span>
                        <span class="block text-lg font-black">آویاتو</span>
                        <span class="block text-xs text-slate-500">پنل مدیریت سرورها</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600 lg:hidden"
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
                        ['label' => 'داشبورد', 'active' => true, 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-11h6V4h-6v5Z'],
                        ['label' => 'ماشین‌ها', 'active' => false, 'icon' => 'M5 7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7H5V7Zm0 7h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3Zm4 3h6'],
                        ['label' => 'تصاویر و بکاپ', 'active' => false, 'icon' => 'M5 5h14v14H5V5Zm3 10 2.5-3 2 2.3L15 11l3 4H8Z'],
                        ['label' => 'شبکه و فایروال', 'active' => false, 'icon' => 'M12 3v4m0 10v4M4.9 7.1l2.8 2.8m8.6 8.6 2.8 2.8M3 12h4m10 0h4M4.9 16.9l2.8-2.8m8.6-8.6 2.8-2.8'],
                        ['label' => 'صورتحساب', 'active' => false, 'icon' => 'M7 4h10v16H7V4Zm3 4h4m-4 4h4m-4 4h2'],
                        ['label' => 'پشتیبانی', 'active' => false, 'icon' => 'M12 4a7 7 0 0 0-7 7v3a3 3 0 0 0 3 3h1v-6H6a6 6 0 1 1 12 0h-3v6h1a3 3 0 0 0 3-3v-3a7 7 0 0 0-7-7Z'],
                    ];
                @endphp
                @foreach ($navItems as $item)
                    <a
                        href="#"
                        class="flex items-center gap-3 rounded-lg px-3 py-3 transition {{ $item['active'] ? 'bg-[#E8F3F0] text-[#105D52]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
                    >
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-bold">اعتبار کیف پول</p>
                <p class="mt-2 text-2xl font-black">۲٬۴۵۰٬۰۰۰ تومان</p>
                <button type="button" class="mt-4 w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800">
                    افزایش اعتبار
                </button>
            </div>
        </aside>

        <main class="w-full min-w-0 flex-1 overflow-x-hidden">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-[#F7F8FA]/90 px-4 py-4 backdrop-blur md:px-8 lg:px-10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="باز کردن منو"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-500">خوش آمدید، امیر</p>
                            <h1 class="text-lg font-black leading-8 md:text-2xl">داشبورد سرورهای شما</h1>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 sm:justify-start">
                        <button type="button" class="hidden rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 sm:block">
                            مستندات
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#105D52] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#0D4C44]"
                            @click="createOpen = true"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            ساخت ماشین
                        </button>
                    </div>
                </div>
            </header>

            <div class="px-4 py-6 md:px-8 lg:px-10">
                <section class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.55fr)]">
                    <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="max-w-2xl min-w-0">
                                <h2 class="text-xl font-black leading-9 md:text-3xl md:leading-tight">اولین ماشین ابری خود را بسازید</h2>
                                <p class="mt-3 leading-8 text-slate-600">
                                    با چند انتخاب ساده، دیتاسنتر، سیستم‌عامل و پلن را مشخص کنید. آویاتو ماشین آماده اتصال را در کمتر از یک دقیقه به شما تحویل می‌دهد.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#105D52] px-5 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-[#0D4C44] sm:w-auto"
                                @click="createOpen = true"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                                شروع ساخت ماشین
                            </button>
                        </div>
                        <div class="mt-6 grid gap-3 md:grid-cols-3">
                            <div class="rounded-lg bg-[#F1F7F5] p-4">
                                <p class="text-sm font-bold text-[#105D52]">۱. انتخاب موقعیت</p>
                                <p class="mt-2 text-sm leading-7 text-slate-600">تهران، شیراز یا دیتاسنتر خارجی</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-sm font-bold text-slate-800">۲. انتخاب منابع</p>
                                <p class="mt-2 text-sm leading-7 text-slate-600">پردازنده، رم، دیسک و ترافیک</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-sm font-bold text-slate-800">۳. اتصال سریع</p>
                                <p class="mt-2 text-sm leading-7 text-slate-600">دریافت IP، رمز و وضعیت آماده</p>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="font-black">سلامت حساب</h2>
                            <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">فعال</span>
                        </div>
                        <div class="mt-5 space-y-4">
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span class="font-bold">مصرف اعتبار ماهانه</span>
                                    <span class="text-slate-500">۶۴٪</span>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-[#105D52]" style="width: 64%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span class="font-bold">ظرفیت منابع فعال</span>
                                    <span class="text-slate-500">۴۱٪</span>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-sky-500" style="width: 41%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 rounded-lg border border-dashed border-slate-300 p-4">
                            <p class="text-sm font-bold">پیشنهاد آویاتو</p>
                            <p class="mt-2 text-sm leading-7 text-slate-600">برای شروع، پلن ۲ هسته، ۴ گیگ رم و Ubuntu 24.04 مناسب بیشتر پروژه‌های وب است.</p>
                        </div>
                    </div>
                </section>

                <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @php
                        $stats = [
                            ['label' => 'ماشین فعال', 'value' => '۳', 'change' => '۲ ماشین آماده اتصال', 'color' => 'text-[#105D52]'],
                            ['label' => 'میانگین CPU', 'value' => '۳۸٪', 'change' => '۱۲٪ کمتر از دیروز', 'color' => 'text-sky-600'],
                            ['label' => 'مصرف ترافیک', 'value' => '۱.۸ ترابایت', 'change' => 'از ۵ ترابایت ماهانه', 'color' => 'text-violet-600'],
                            ['label' => 'هزینه امروز', 'value' => '۱۸۶٬۰۰۰', 'change' => 'تومان تا این لحظه', 'color' => 'text-amber-600'],
                        ];
                    @endphp
                    @foreach ($stats as $stat)
                        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-bold text-slate-500">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-2xl font-black {{ $stat['color'] }}">{{ $stat['value'] }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ $stat['change'] }}</p>
                        </article>
                    @endforeach
                </section>

                <section class="mt-6 grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-lg font-black">ماشین‌های فعلی</h2>
                                <p class="mt-1 text-sm text-slate-500">نمای کلی وضعیت، مصرف و موقعیت ماشین‌ها</p>
                            </div>
                            <div class="flex rounded-lg bg-slate-100 p-1 text-sm font-bold">
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'روزانه' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'روزانه'">روزانه</button>
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'هفتگی' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'هفتگی'">هفتگی</button>
                                <button type="button" class="rounded-md px-3 py-2 transition" :class="period === 'ماهانه' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="period = 'ماهانه'">ماهانه</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-right text-sm">
                                <thead class="bg-slate-50 text-xs font-black text-slate-500">
                                    <tr>
                                        <th class="px-5 py-4">نام ماشین</th>
                                        <th class="px-5 py-4">موقعیت</th>
                                        <th class="px-5 py-4">منابع</th>
                                        <th class="px-5 py-4">CPU</th>
                                        <th class="px-5 py-4">وضعیت</th>
                                        <th class="px-5 py-4">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @php
                                        $machines = [
                                            ['name' => 'web-prod-01', 'region' => 'تهران ۱', 'plan' => '۲ vCPU / ۴GB', 'cpu' => '۴۲٪', 'status' => 'روشن', 'ip' => '185.143.232.18'],
                                            ['name' => 'db-main', 'region' => 'شیراز ۱', 'plan' => '۴ vCPU / ۸GB', 'cpu' => '۳۱٪', 'status' => 'روشن', 'ip' => '185.143.232.41'],
                                            ['name' => 'staging-api', 'region' => 'فرانکفورت', 'plan' => '۱ vCPU / ۲GB', 'cpu' => '۱۲٪', 'status' => 'آماده', 'ip' => '49.13.88.104'],
                                        ];
                                    @endphp
                                    @foreach ($machines as $machine)
                                        <tr class="hover:bg-slate-50">
                                            <td class="whitespace-nowrap px-5 py-4 font-black text-slate-900">{{ $machine['name'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $machine['region'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $machine['plan'] }}</td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span class="font-bold text-slate-800">{{ $machine['cpu'] }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <span class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                                    <span class="size-2 rounded-full bg-emerald-500"></span>
                                                    {{ $machine['status'] }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-slate-600">{{ $machine['ip'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="min-w-0 space-y-6">
                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h2 class="font-black">مصرف منابع</h2>
                                <span class="text-xs font-bold text-slate-400" x-text="period"></span>
                            </div>
                            <div class="mt-6 flex h-44 items-end gap-2 rounded-lg bg-slate-50 px-3 py-3">
                                @foreach ([46, 64, 38, 78, 55, 42, 69, 51, 84, 61, 48, 73] as $bar)
                                    <div class="flex h-full min-w-3 flex-1 items-end rounded-md bg-slate-200/70">
                                        <div class="w-full rounded-md bg-[#105D52]" style="height: {{ $bar }}%; min-height: 16px"></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 flex justify-between text-xs font-bold text-slate-400">
                                <span>ابتدا</span>
                                <span>اکنون</span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-black">فعالیت‌های اخیر</h2>
                            <div class="mt-5 space-y-4">
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 rounded-full bg-emerald-500"></span>
                                    <p class="text-sm leading-7 text-slate-600"><span class="font-bold text-slate-900">db-main</span> با موفقیت ری‌استارت شد.</p>
                                </div>
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 rounded-full bg-sky-500"></span>
                                    <p class="text-sm leading-7 text-slate-600">بکاپ روزانه ماشین <span class="font-bold text-slate-900">web-prod-01</span> ساخته شد.</p>
                                </div>
                                <div class="flex gap-3">
                                    <span class="mt-1 size-2.5 rounded-full bg-amber-500"></span>
                                    <p class="text-sm leading-7 text-slate-600">هشدار مصرف CPU برای <span class="font-bold text-slate-900">staging-api</span> ثبت شد.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

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
