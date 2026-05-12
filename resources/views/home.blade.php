<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>آویاتو | سرور ابری در کمتر از یک دقیقه</title>
    <meta name="description" content="ماشین مجازی سریع با دیسک NVMe، IP اختصاصی، بکاپ روزانه و پشتیبانی فارسی. بدون قرارداد، پرداخت ساعتی.">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 5rem;
        }
    </style>
</head>
<body class="overflow-x-hidden bg-[#F5F7FB] text-slate-950">
    <div class="min-h-screen overflow-hidden">
        <header
            x-data="{ scrolled: false }"
            x-init="scrolled = window.scrollY > 16; window.addEventListener('scroll', () => scrolled = window.scrollY > 16, { passive: true })"
            :class="scrolled ? 'h-12 shadow-md shadow-slate-200/60' : 'h-16'"
            class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/90 backdrop-blur transition-all duration-200"
        >
            <nav class="mx-auto flex h-full max-w-7xl items-center justify-between gap-4 px-4 md:px-8 lg:px-10">
                <a href="#top" class="flex items-center gap-2.5" aria-label="آویاتو">
                    <span :class="scrolled ? 'size-8' : 'size-9'" class="grid place-items-center rounded-lg bg-[#0069FF] text-sm font-black text-white transition-all">آ</span>
                    <span :class="scrolled ? 'text-sm' : 'text-base'" class="font-black transition-all">آویاتو</span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-bold text-slate-600 lg:flex">
                    <a href="#top" class="transition hover:text-[#0069FF]">خانه</a>
                    <a href="#features" class="transition hover:text-[#0069FF]">راهکارها</a>
                    <a href="#plans" class="transition hover:text-[#0069FF]">قیمت‌گذاری</a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('customer.login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:text-[#0069FF] sm:inline-flex">ورود</a>
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-black text-white shadow-sm transition hover:bg-[#0050D0]">
                        ثبت‌نام
                        <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </nav>
        </header>

        <div class="h-16" aria-hidden="true"></div>

        <main>
            <section id="top" class="relative overflow-hidden bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-20 pt-16 md:px-8 md:pb-28 md:pt-24 lg:px-10">
                <div aria-hidden="true" class="pointer-events-none absolute -top-32 right-1/2 size-[680px] translate-x-1/2 rounded-full bg-[#0069FF]/8 blur-3xl"></div>
                <div class="relative mx-auto max-w-4xl text-center">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#B8D6FF] bg-white px-4 py-1.5 text-xs font-black text-[#0050D0] shadow-sm">
                        <span class="size-2 rounded-full bg-[#0069FF]"></span>
                        ساخت سرور در کمتر از ۶۰ ثانیه
                    </div>
                    <h1 class="mt-6 text-4xl font-black leading-[1.15] tracking-tight text-slate-950 md:text-6xl md:leading-[1.1]">
                        اولین سرور ابری شما،<br>
                        <span class="text-[#0069FF]">آماده در یک دقیقه.</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-9 text-slate-600 md:text-xl">
                        ماشین مجازی سریع روی دیسک NVMe، با IP اختصاصی، بکاپ روزانه و پشتیبانی فارسی. بدون قرارداد، ساعتی حساب می‌شود و هر وقت بخواهید لغوش می‌کنید.
                    </p>
                    <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('customer.register') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-7 py-4 text-base font-black text-white shadow-lg shadow-[#0069FF]/25 transition hover:bg-[#0050D0] sm:w-auto">
                            ساخت حساب و دریافت سرور
                            <svg class="size-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <a href="#plans" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-7 py-4 text-base font-black text-slate-800 transition hover:border-[#B8D6FF] hover:text-[#0069FF] sm:w-auto">
                            دیدن قیمت‌ها
                        </a>
                    </div>
                    <div class="mx-auto mt-12 grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                        @foreach ([
                            ['title' => 'تحویل فوری', 'body' => 'IP و رمز در کمتر از ۶۰ ثانیه'],
                            ['title' => 'پرداخت ساعتی', 'body' => 'فقط زمان روشن بودن حساب می‌شود'],
                            ['title' => 'پشتیبانی فارسی', 'body' => 'پاسخ تیم فنی در همان روز کاری'],
                        ] as $trust)
                            <div class="flex items-center justify-center gap-2 text-sm font-bold text-slate-600">
                                <svg class="size-5 shrink-0 text-[#0069FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span><span class="font-black text-slate-900">{{ $trust['title'] }}</span> — {{ $trust['body'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="features" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="max-w-3xl">
                        <p class="text-sm font-black text-[#0069FF]">چرا آویاتو</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-normal md:text-5xl">همه چیزی که برای راه‌اندازی پروژه‌تان لازم دارید</h2>
                        <p class="mt-4 text-lg leading-9 text-slate-600">ابزارهای حرفه‌ای، بدون پیچیدگی. روی محصول‌تان تمرکز کنید، زیرساخت با ما.</p>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['title' => 'سرعت واقعی', 'body' => 'دیسک NVMe، شبکه ۱۰ گیگ و CPU نسل جدید برای وب، API و دیتابیس.'],
                            ['title' => 'کنترل کامل هزینه', 'body' => 'صورتحساب ساعتی، اعتبار قابل شارژ و سقف مصرف برای جلوگیری از غافلگیری.'],
                            ['title' => 'امنیت پیش‌فرض', 'body' => 'بکاپ روزانه خودکار، فایروال، snapshot دستی و IP اختصاصی برای هر ماشین.'],
                            ['title' => 'پشتیبانی فارسی', 'body' => 'تیم فنی فارسی‌زبان روی تیکت و چت، پاسخ سریع در ساعات کاری.'],
                        ] as $feature)
                            <article class="group rounded-lg border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-slate-200/70">
                                <div class="grid size-12 place-items-center rounded-lg bg-[#EBF3FF] text-[#0069FF] transition group-hover:bg-[#0069FF] group-hover:text-white">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <h3 class="mt-5 text-xl font-black">{{ $feature['title'] }}</h3>
                                <p class="mt-3 text-sm leading-8 text-slate-600">{{ $feature['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="plans" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.78fr_1.22fr]">
                    <div class="rounded-2xl bg-[#0069FF] p-7 text-white shadow-xl shadow-[#0069FF]/10 md:p-9">
                        <p class="text-sm font-black text-blue-100">پلن پیشنهادی</p>
                        <h2 class="mt-4 text-4xl font-black leading-tight tracking-normal">از کجا شروع کنید؟</h2>
                        <p class="mt-5 leading-8 text-blue-50/85">اگر اولین سرور خود را می‌سازید، پلن «شروع» پاسخگوی بیشتر وب‌سایت‌ها و API‌های کوچک تا متوسط است. هر زمان نیاز داشتید، با یک کلیک ارتقاء می‌دهید.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([
                            ['name' => 'شروع', 'price' => '۴۹۰٬۰۰۰', 'spec' => '۲ vCPU / ۴GB RAM / ۸۰GB NVMe', 'tone' => 'border-[#B8D6FF] bg-[#EBF3FF]'],
                            ['name' => 'رشد', 'price' => '۹۸۰٬۰۰۰', 'spec' => '۴ vCPU / ۸GB RAM / بکاپ روزانه', 'tone' => 'border-slate-200 bg-white'],
                            ['name' => 'عملیات', 'price' => 'سفارشی', 'spec' => 'منابع اختصاصی / شبکه و فایروال', 'tone' => 'border-slate-200 bg-white'],
                        ] as $plan)
                            <article class="rounded-lg border p-5 shadow-sm {{ $plan['tone'] }}">
                                <p class="text-sm font-black text-[#0069FF]">{{ $plan['name'] }}</p>
                                <p class="mt-4 text-3xl font-black">{{ $plan['price'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">تومان / ماه</p>
                                <p class="mt-6 min-h-14 text-sm font-bold leading-7 text-slate-600">{{ $plan['spec'] }}</p>
                                <a href="{{ route('customer.register') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">انتخاب پلن</a>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="regions" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-9">
                    <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-[#0069FF]">موقعیت نزدیک به کاربر</p>
                            <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">سرور را همان‌جا بسازید که کاربرانتان هستند</h2>
                            <p class="mt-4 leading-8 text-slate-600">از تهران و شیراز تا فرانکفورت — لیتانسی کم، سرعت بالا.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ([['تهران ۱','برای کاربران ایران'], ['شیراز ۱','پشتیبان داخلی'], ['فرانکفورت','مسیر بین‌المللی']] as $region)
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-xl font-black text-slate-950">{{ $region[0] }}</p>
                                    <p class="mt-2 text-sm font-bold text-slate-500">{{ $region[1] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section id="process" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-10 max-w-3xl">
                        <p class="text-sm font-black text-[#0069FF]">مسیر ساخت</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">سه گام تا سرور آماده</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([['۱','موقعیت را انتخاب کنید','تهران، شیراز یا دیتاسنتر خارجی را براساس کاربر نهایی مشخص کنید.'], ['۲','منابع و سیستم‌عامل','vCPU، RAM، NVMe و Ubuntu، Debian یا Rocky را انتخاب کنید.'], ['۳','وصل شوید و مدیریت کنید','IP، وضعیت، مصرف منابع، بکاپ و هزینه از داشبورد در دسترس است.']] as $step)
                            <article class="relative overflow-hidden rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                                <span class="absolute -left-4 -top-8 text-8xl font-black text-blue-100">{{ $step[0] }}</span>
                                <p class="relative text-sm font-black text-[#0069FF]">گام {{ $step[0] }}</p>
                                <h3 class="relative mt-4 text-2xl font-black">{{ $step[1] }}</h3>
                                <p class="relative mt-3 leading-8 text-slate-600">{{ $step[2] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="px-4 pb-20 pt-8 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#0069FF] p-8 text-white shadow-2xl shadow-[#0069FF]/20 md:p-12">
                    <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-blue-100">اکنون وقت ساختن است</p>
                            <h2 class="mt-3 max-w-3xl text-4xl font-black leading-tight tracking-normal md:text-6xl">سرور بعدی شما باید قبل از تمام شدن قهوه آماده باشد.</h2>
                        </div>
                        <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#0069FF] shadow-xl transition hover:bg-blue-50">ثبت‌نام و ساخت سرور</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
            <section class="bg-[#061A33] px-4 pb-16 pt-14 text-white md:px-8 md:pb-20 md:pt-18 lg:px-10">
                <div class="mx-auto grid max-w-7xl items-center gap-12 xl:grid-cols-[minmax(0,0.9fr)_minmax(560px,1.1fr)]">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/[0.07] px-3 py-2 text-sm font-black text-blue-100">
                            <span class="size-2.5 rounded-full bg-blue-400 shadow-[0_0_0_6px_rgba(96,165,250,0.16)]"></span>
                            تحویل ماشین آماده اتصال در کمتر از یک دقیقه
                        </div>
                        <h1 class="mt-7 max-w-4xl text-5xl font-black leading-[1.18] tracking-normal text-white md:text-7xl md:leading-[1.12] xl:text-[5.4rem]">
                            سرور ابری بسازید؛ ساده، سریع، قابل کنترل.
                        </h1>
                        <p class="mt-6 max-w-2xl text-lg font-medium leading-9 text-blue-100/75 md:text-xl md:leading-10">
                            آویاتو برای تیم‌هایی ساخته شده که ماشین مجازی پایدار، منابع شفاف، IP آماده، بکاپ روزانه، فایروال و کنترل هزینه را در یک پنل تمیز می‌خواهند.
                        </p>
                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2563EB] px-7 py-4 text-base font-black text-white shadow-lg shadow-blue-950/25 transition hover:bg-[#1D4ED8]">
                                شروع ساخت ماشین
                                <svg class="size-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                            <a href="#features" class="inline-flex items-center justify-center rounded-lg border border-white/15 bg-white/[0.07] px-7 py-4 text-base font-black text-white transition hover:bg-white/12">دیدن امکانات</a>
                        </div>
                        <div class="mt-10 grid max-w-2xl grid-cols-3 gap-3">
                            <div class="rounded-lg border border-white/10 bg-white/[0.07] p-4">
                                <p class="text-2xl font-black text-blue-300">۳</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-blue-100/65">موقعیت ایران و خارج</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/[0.07] p-4">
                                <p class="text-2xl font-black text-blue-300">NVMe</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-blue-100/65">دیسک سریع برای وب و API</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/[0.07] p-4">
                                <p class="text-2xl font-black text-blue-300">۲۴.۰۴</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-blue-100/65">Ubuntu آماده نصب</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-3xl xl:max-w-none">
                        <div class="absolute -right-5 top-7 z-10 hidden rounded-lg border border-slate-200 bg-white p-4 text-slate-950 shadow-xl shadow-blue-950/20 lg:block">
                            <p class="text-xs font-black text-slate-500">هزینه امروز</p>
                            <p class="mt-1 text-xl font-black text-amber-600">۱۸۶٬۰۰۰ تومان</p>
                        </div>
                        <div class="absolute -left-1 bottom-28 z-10 hidden rounded-lg bg-[#2563EB] p-4 text-white shadow-2xl shadow-blue-950/30 md:block">
                            <p class="text-xs font-bold text-white/75">ماشین آماده اتصال</p>
                            <p class="mt-1 font-black">web-prod-01 روشن است</p>
                        </div>

                        <div class="relative rounded-2xl border border-white/15 bg-slate-950 p-3 shadow-[0_35px_90px_rgba(0,0,0,0.34)]">
                            <div class="overflow-hidden rounded-xl bg-[#F5F7FB] ring-1 ring-white/10">
                                <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="size-3 rounded-full bg-rose-400"></span>
                                        <span class="size-3 rounded-full bg-amber-400"></span>
                                        <span class="size-3 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <div class="rounded-lg bg-slate-100 px-4 py-1.5 text-xs font-black text-slate-500">dashboard.aviato.cloud</div>
                                    <span class="size-7 rounded-lg bg-[#2563EB]"></span>
                                </div>

                                <div class="grid min-h-[430px] grid-cols-[150px_minmax(0,1fr)] bg-[#F5F7FB] md:grid-cols-[190px_minmax(0,1fr)]">
                                    <aside class="hidden border-l border-white/10 bg-[#061A33] p-4 text-white md:block">
                                        <div class="mb-6 flex items-center gap-2">
                                            <span class="grid size-9 place-items-center rounded-lg bg-[#2563EB] text-sm font-black text-white">آ</span>
                                            <div>
                                                <p class="text-sm font-black">آویاتو</p>
                                                <p class="text-[10px] text-blue-100/55">کنسول ابر</p>
                                            </div>
                                        </div>
                                        <div class="space-y-2 text-xs font-bold">
                                            <div class="rounded-lg bg-white px-3 py-3 text-[#061A33]">داشبورد</div>
                                            <div class="rounded-lg px-3 py-3 text-blue-100/65">ماشین‌ها</div>
                                            <div class="rounded-lg px-3 py-3 text-blue-100/65">مشتریان</div>
                                            <div class="rounded-lg px-3 py-3 text-blue-100/65">Proxmox</div>
                                            <div class="rounded-lg px-3 py-3 text-blue-100/65">صورتحساب</div>
                                        </div>
                                    </aside>
                                    <div class="min-w-0 p-4 md:p-5">
                                        <div class="flex items-center gap-3">
                                            <div class="relative min-w-0 flex-1">
                                                <div class="h-10 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-400">جستجو در مشتری، VM، IP...</div>
                                                <div class="absolute left-2 top-2 hidden gap-1 text-[10px] font-black text-slate-400 sm:flex">
                                                    <span class="rounded border border-slate-200 px-1">Ctrl K</span>
                                                    <span class="rounded border border-slate-200 px-1">/</span>
                                                </div>
                                            </div>
                                            <span class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500">!</span>
                                            <span class="grid size-10 place-items-center rounded-lg bg-[#061A33] text-xs font-black text-white">ا</span>
                                        </div>

                                        <div class="mt-5 flex items-center justify-between gap-4">
                                            <div>
                                                <p class="text-xs font-bold text-slate-400">نمای مدیریتی</p>
                                                <h2 class="text-lg font-black text-slate-950 md:text-2xl">داشبورد عملیات VM</h2>
                                            </div>
                                            <span class="rounded-lg bg-[#2563EB] px-3 py-2 text-xs font-black text-white">VM جدید</span>
                                        </div>

                                        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                            @foreach ([['۱۲۸','VM فعال','text-[#1D4ED8]'], ['۷','صف ساخت','text-amber-600'], ['۱۸','ریسک مالی','text-red-600'], ['۲.۸M','درآمد','text-slate-950']] as $stat)
                                                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                                    <p class="text-lg font-black {{ $stat[2] }}">{{ $stat[0] }}</p>
                                                    <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $stat[1] }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="mt-4 rounded-lg border border-slate-200 bg-white shadow-sm">
                                            <div class="flex items-center justify-between border-b border-slate-100 p-3">
                                                <p class="text-sm font-black text-slate-950">ماشین‌های نیازمند توجه</p>
                                                <p class="text-[10px] font-bold text-slate-400">روزانه</p>
                                            </div>
                                            <div class="divide-y divide-slate-100 text-xs">
                                                @foreach ([['staging-api','Provisioning','text-amber-600'], ['db-main','مصرف بالا','text-red-600'], ['web-prod-01','روشن','text-emerald-600']] as $machine)
                                                    <div class="grid grid-cols-[1fr_0.75fr_0.65fr] gap-2 px-3 py-3">
                                                        <span class="font-black text-slate-800" dir="ltr">{{ $machine[0] }}</span>
                                                        <span class="text-slate-500">تهران ۱</span>
                                                        <span class="font-black {{ $machine[2] }}">{{ $machine[1] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <p class="text-xs font-black text-slate-500">ظرفیت تهران ۱</p>
                                                <div class="mt-3 h-2 rounded-full bg-slate-100">
                                                    <div class="h-2 rounded-full bg-amber-500" style="width: 87%"></div>
                                                </div>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                <p class="text-xs font-black text-slate-500">ریسک مالی</p>
                                                <p class="mt-2 text-sm font-black text-red-600">۲ VM نزدیک توقف</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mx-auto h-7 w-2/5 rounded-b-3xl bg-slate-950"></div>
                        <div class="mx-auto h-4 w-3/5 rounded-full bg-blue-950/25 blur-sm"></div>
                    </div>
                </div>
            </section>

            <section id="features" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="max-w-3xl">
                        <p class="text-sm font-black text-[#1D4ED8]">چرا برای فروش ماشین مجازی جواب می‌دهد؟</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-normal md:text-5xl">صفحه‌ای که فقط زیبا نیست؛ اعتماد به زیرساخت را می‌فروشد.</h2>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['title' => 'تحویل سریع', 'body' => 'پیام اصلی روی زمان رسیدن به IP، رمز و ماشین آماده اتصال تمرکز دارد؛ همان چیزی که خریدار VPS می‌خواهد.'],
                            ['title' => 'شفافیت هزینه', 'body' => 'نمای مصرف اعتبار، هزینه امروز و منابع فعال نشان می‌دهد کاربر بعد از خرید کنترل مالی دارد.'],
                            ['title' => 'عملیات کامل', 'body' => 'ماشین‌ها، بکاپ، شبکه، فایروال و فعالیت‌ها همان قابلیت‌های مهم داشبورد را قبل از ثبت‌نام نشان می‌دهند.'],
                            ['title' => 'اعتماد بصری', 'body' => 'رنگ‌های آبی و سرمه‌ای، کارت‌های سفید و مانیتور واقعی از داشبورد حس محصول آماده و قابل اتکا می‌سازند.'],
                        ] as $feature)
                            <article class="group rounded-lg border border-slate-200 bg-white p-6 shadow-sm transition hover:border-blue-200 hover:shadow-lg hover:shadow-slate-200/70">
                                <div class="grid size-12 place-items-center rounded-lg bg-blue-50 text-[#2563EB] transition group-hover:bg-[#2563EB] group-hover:text-white">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <h3 class="mt-5 text-xl font-black">{{ $feature['title'] }}</h3>
                                <p class="mt-3 text-sm leading-8 text-slate-600">{{ $feature['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="plans" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.78fr_1.22fr]">
                    <div class="rounded-2xl bg-[#061A33] p-7 text-white shadow-xl shadow-blue-950/10 md:p-9">
                        <p class="text-sm font-black text-blue-300">پلن پیشنهادی آویاتو</p>
                        <h2 class="mt-4 text-4xl font-black leading-tight tracking-normal">برای شروع پروژه وب، منابع درست را از اول انتخاب کنید.</h2>
                        <p class="mt-5 leading-8 text-blue-100/70">داشبورد، پلن ۲ هسته، ۴ گیگ رم و Ubuntu 24.04 را برای بیشتر پروژه‌های وب پیشنهاد می‌دهد؛ همین پیشنهاد در صفحه خانه به پیام فروش تبدیل شده است.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([
                            ['name' => 'شروع', 'price' => '۴۹۰٬۰۰۰', 'spec' => '۲ vCPU / ۴GB RAM / ۸۰GB NVMe', 'tone' => 'border-blue-200 bg-blue-50'],
                            ['name' => 'رشد', 'price' => '۹۸۰٬۰۰۰', 'spec' => '۴ vCPU / ۸GB RAM / بکاپ روزانه', 'tone' => 'border-slate-200 bg-white'],
                            ['name' => 'عملیات', 'price' => 'سفارشی', 'spec' => 'منابع اختصاصی / شبکه و فایروال', 'tone' => 'border-slate-200 bg-white'],
                        ] as $plan)
                            <article class="rounded-lg border {{ $plan['tone'] }} p-6 shadow-sm">
                                <p class="text-sm font-black text-[#1D4ED8]">{{ $plan['name'] }}</p>
                                <p class="mt-4 text-3xl font-black">{{ $plan['price'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">تومان / ماه</p>
                                <p class="mt-6 min-h-14 text-sm font-bold leading-7 text-slate-600">{{ $plan['spec'] }}</p>
                                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-[#061A33] px-4 py-3 text-sm font-black text-white transition hover:bg-[#082B55]">انتخاب پلن</a>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="regions" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-9">
                    <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-[#1D4ED8]">موقعیت نزدیک به کاربر</p>
                            <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">از تهران و شیراز تا فرانکفورت؛ ماشین را همان‌جا بسازید که محصول نفس می‌کشد.</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ([['تهران ۱','برای کاربران ایران'], ['شیراز ۱','پشتیبان داخلی'], ['فرانکفورت','مسیر بین‌المللی']] as $region)
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-xl font-black text-slate-950">{{ $region[0] }}</p>
                                    <p class="mt-2 text-sm font-bold text-slate-500">{{ $region[1] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section id="process" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([['۱','موقعیت را انتخاب کنید','تهران، شیراز یا دیتاسنتر خارجی را براساس کاربر نهایی مشخص کنید.'], ['۲','منابع و سیستم‌عامل','vCPU، RAM، NVMe و Ubuntu، Debian یا Rocky را انتخاب کنید.'], ['۳','وصل شوید و مدیریت کنید','IP، وضعیت، مصرف منابع، بکاپ و هزینه از داشبورد در دسترس است.']] as $step)
                            <article class="relative overflow-hidden rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                                <span class="absolute -left-4 -top-8 text-8xl font-black text-blue-100">{{ $step[0] }}</span>
                                <p class="text-sm font-black text-[#1D4ED8]">گام {{ $step[0] }}</p>
                                <h3 class="mt-4 text-2xl font-black">{{ $step[1] }}</h3>
                                <p class="mt-3 leading-8 text-slate-600">{{ $step[2] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="px-4 pb-20 pt-8 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#061A33] p-8 text-white shadow-2xl shadow-blue-950/20 md:p-12">
                    <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-blue-300">اکنون وقت ساختن است</p>
                            <h2 class="mt-3 max-w-3xl text-4xl font-black leading-tight tracking-normal md:text-6xl">ماشین مجازی بعدی شما باید قبل از تمام شدن قهوه آماده باشد.</h2>
                        </div>
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#061A33] shadow-xl transition hover:bg-blue-50">ورود به داشبورد و ساخت ماشین</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
