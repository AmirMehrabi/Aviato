<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>آویاتو | ماشین ابری آماده اتصال</title>
    <meta name="description" content="آویاتو ماشین‌های ابری سریع، قابل مدیریت و آماده اتصال برای تیم‌های وب، محصول و عملیات فراهم می‌کند.">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-hidden bg-[#F7F8FA] text-slate-950">
    <div class="relative min-h-screen overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[720px] bg-[radial-gradient(circle_at_18%_16%,rgba(16,93,82,0.22),transparent_34%),radial-gradient(circle_at_82%_12%,rgba(14,165,233,0.18),transparent_30%),linear-gradient(180deg,#F7F8FA_0%,#EEF6F3_55%,#F7F8FA_100%)]"></div>
        <div class="pointer-events-none absolute right-[-140px] top-28 h-80 w-80 rounded-full border-[36px] border-[#105D52]/10"></div>
        <div class="pointer-events-none absolute left-[-90px] top-[520px] h-64 w-64 rounded-full bg-sky-300/20 blur-3xl"></div>

        <header class="relative z-20 px-4 pt-5 md:px-8 lg:px-10">
            <nav class="mx-auto flex max-w-7xl items-center justify-between rounded-[1.35rem] border border-white/80 bg-white/75 px-4 py-3 shadow-sm shadow-slate-200/70 backdrop-blur-xl md:px-5">
                <a href="/" class="flex items-center gap-3" aria-label="صفحه اصلی آویاتو">
                    <span class="grid size-11 place-items-center rounded-xl bg-[#105D52] text-lg font-black text-white shadow-lg shadow-[#105D52]/20">آ</span>
                    <span>
                        <span class="block text-lg font-black">آویاتو</span>
                        <span class="block text-xs font-bold text-slate-500">زیرساخت ابری شما</span>
                    </span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-bold text-slate-600 lg:flex">
                    <a href="#plans" class="transition hover:text-[#105D52]">پلن‌ها</a>
                    <a href="#features" class="transition hover:text-[#105D52]">امکانات</a>
                    <a href="#regions" class="transition hover:text-[#105D52]">موقعیت‌ها</a>
                    <a href="#process" class="transition hover:text-[#105D52]">مسیر ساخت</a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="hidden rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#105D52]/30 hover:text-[#105D52] sm:inline-flex">مشاهده داشبورد</a>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#105D52] px-4 py-2.5 text-sm font-black text-white shadow-lg shadow-[#105D52]/20 transition hover:bg-[#0D4C44]">
                        ساخت ماشین
                        <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </nav>
        </header>

        <main class="relative z-10">
            <section class="px-4 pb-16 pt-14 md:px-8 md:pb-24 md:pt-20 lg:px-10">
                <div class="mx-auto grid max-w-7xl items-center gap-12 xl:grid-cols-[minmax(0,0.92fr)_minmax(560px,1.08fr)]">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#105D52]/15 bg-white/80 px-3 py-2 text-sm font-black text-[#105D52] shadow-sm">
                            <span class="size-2.5 rounded-full bg-emerald-500 shadow-[0_0_0_6px_rgba(16,185,129,0.12)]"></span>
                            تحویل ماشین آماده اتصال در کمتر از یک دقیقه
                        </div>
                        <h1 class="mt-7 max-w-4xl text-5xl font-black leading-[1.18] tracking-[-0.04em] text-slate-950 md:text-7xl md:leading-[1.12] xl:text-[5.6rem]">
                            سرور ابری بسازید؛ نه صف، نه پیچیدگی، نه حدس.
                        </h1>
                        <p class="mt-6 max-w-2xl text-lg font-medium leading-9 text-slate-600 md:text-xl md:leading-10">
                            آویاتو برای تیم‌هایی ساخته شده که ماشین مجازی پایدار، منابع شفاف، IP آماده، بکاپ روزانه، فایروال و کنترل هزینه را در یک پنل تمیز می‌خواهند.
                        </p>
                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#105D52] px-7 py-4 text-base font-black text-white shadow-2xl shadow-[#105D52]/25 transition hover:-translate-y-0.5 hover:bg-[#0D4C44]">
                                شروع ساخت ماشین
                                <svg class="size-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                            <a href="#features" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white/85 px-7 py-4 text-base font-black text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:border-[#105D52]/30 hover:text-[#105D52]">دیدن امکانات</a>
                        </div>
                        <div class="mt-10 grid max-w-2xl grid-cols-3 gap-3">
                            <div class="rounded-2xl border border-white bg-white/75 p-4 shadow-sm">
                                <p class="text-2xl font-black text-[#105D52]">۳</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-slate-500">موقعیت ایران و خارج</p>
                            </div>
                            <div class="rounded-2xl border border-white bg-white/75 p-4 shadow-sm">
                                <p class="text-2xl font-black text-[#105D52]">NVMe</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-slate-500">دیسک سریع برای وب و API</p>
                            </div>
                            <div class="rounded-2xl border border-white bg-white/75 p-4 shadow-sm">
                                <p class="text-2xl font-black text-[#105D52]">۲۴.۰۴</p>
                                <p class="mt-1 text-xs font-bold leading-6 text-slate-500">Ubuntu آماده نصب</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-3xl xl:max-w-none">
                        <div class="absolute -right-5 top-7 z-10 hidden rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl shadow-slate-300/40 lg:block">
                            <p class="text-xs font-black text-slate-500">هزینه امروز</p>
                            <p class="mt-1 text-xl font-black text-amber-600">۱۸۶٬۰۰۰ تومان</p>
                        </div>
                        <div class="absolute -left-1 bottom-28 z-10 hidden rounded-2xl bg-[#105D52] p-4 text-white shadow-2xl shadow-[#105D52]/30 md:block">
                            <p class="text-xs font-bold text-white/70">ماشین آماده اتصال</p>
                            <p class="mt-1 font-black">web-prod-01 روشن است</p>
                        </div>

                        <div class="relative rounded-[2.2rem] border border-slate-300 bg-slate-950 p-3 shadow-[0_35px_90px_rgba(15,23,42,0.28)]">
                            <div class="overflow-hidden rounded-[1.55rem] bg-[#F7F8FA] ring-1 ring-white/10">
                                <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="size-3 rounded-full bg-rose-400"></span>
                                        <span class="size-3 rounded-full bg-amber-400"></span>
                                        <span class="size-3 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <div class="rounded-full bg-slate-100 px-4 py-1.5 text-xs font-black text-slate-500">dashboard.aviato.cloud</div>
                                    <span class="size-7 rounded-lg bg-[#105D52]"></span>
                                </div>

                                <div class="grid min-h-[390px] grid-cols-[150px_minmax(0,1fr)] bg-[#F7F8FA] md:grid-cols-[190px_minmax(0,1fr)]">
                                    <aside class="hidden border-l border-slate-200 bg-white p-4 md:block">
                                        <div class="mb-6 flex items-center gap-2">
                                            <span class="grid size-9 place-items-center rounded-lg bg-[#105D52] text-sm font-black text-white">آ</span>
                                            <div>
                                                <p class="text-sm font-black">آویاتو</p>
                                                <p class="text-[10px] text-slate-400">پنل سرورها</p>
                                            </div>
                                        </div>
                                        <div class="space-y-2 text-xs font-bold">
                                            <div class="rounded-lg bg-[#E8F3F0] px-3 py-3 text-[#105D52]">داشبورد</div>
                                            <div class="rounded-lg px-3 py-3 text-slate-500">ماشین‌ها</div>
                                            <div class="rounded-lg px-3 py-3 text-slate-500">بکاپ</div>
                                            <div class="rounded-lg px-3 py-3 text-slate-500">شبکه</div>
                                        </div>
                                    </aside>
                                    <div class="min-w-0 p-4 md:p-5">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-bold text-slate-400">خوش آمدید، امیر</p>
                                                <h2 class="text-lg font-black md:text-2xl">داشبورد سرورهای شما</h2>
                                            </div>
                                            <span class="rounded-lg bg-[#105D52] px-3 py-2 text-xs font-black text-white">ساخت ماشین</span>
                                        </div>

                                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <div class="flex items-center justify-between gap-4">
                                                <div>
                                                    <p class="text-lg font-black">اولین ماشین ابری خود را بسازید</p>
                                                    <p class="mt-2 text-xs leading-6 text-slate-500">دیتاسنتر، سیستم‌عامل و منابع را انتخاب کنید.</p>
                                                </div>
                                                <div class="hidden rounded-xl bg-[#F1F7F5] p-3 text-center text-xs font-black text-[#105D52] sm:block">کمتر از<br>۱ دقیقه</div>
                                            </div>
                                            <div class="mt-4 grid grid-cols-3 gap-2 text-[10px] font-bold text-slate-500">
                                                <div class="rounded-xl bg-[#F1F7F5] p-3 text-[#105D52]">تهران / شیراز</div>
                                                <div class="rounded-xl bg-slate-50 p-3">۲ vCPU / ۴GB</div>
                                                <div class="rounded-xl bg-slate-50 p-3">IP آماده</div>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                            @foreach ([['۳','ماشین فعال'], ['۳۸٪','میانگین CPU'], ['۱.۸TB','ترافیک'], ['۱۸۶K','هزینه']] as $stat)
                                                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                                                    <p class="text-lg font-black text-[#105D52]">{{ $stat[0] }}</p>
                                                    <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $stat[1] }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
                                            <div class="flex items-center justify-between border-b border-slate-100 p-3">
                                                <p class="text-sm font-black">ماشین‌های فعلی</p>
                                                <p class="text-[10px] font-bold text-slate-400">روزانه</p>
                                            </div>
                                            <div class="divide-y divide-slate-100 text-xs">
                                                @foreach ([['web-prod-01','تهران ۱','۴۲٪'], ['db-main','شیراز ۱','۳۱٪'], ['staging-api','فرانکفورت','۱۲٪']] as $machine)
                                                    <div class="grid grid-cols-[1fr_0.8fr_0.55fr] gap-2 px-3 py-3">
                                                        <span class="font-black text-slate-800">{{ $machine[0] }}</span>
                                                        <span class="text-slate-500">{{ $machine[1] }}</span>
                                                        <span class="font-black text-emerald-600">{{ $machine[2] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mx-auto h-7 w-2/5 rounded-b-3xl bg-slate-900"></div>
                        <div class="mx-auto h-4 w-3/5 rounded-full bg-slate-300/70 blur-sm"></div>
                    </div>
                </div>
            </section>

            <section id="features" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl">
                    <div class="max-w-3xl">
                        <p class="text-sm font-black text-[#105D52]">چرا برای فروش ماشین مجازی جواب می‌دهد؟</p>
                        <h2 class="mt-3 text-3xl font-black leading-tight tracking-[-0.03em] md:text-5xl">صفحه‌ای که فقط زیبا نیست؛ اعتماد به زیرساخت را می‌فروشد.</h2>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['title' => 'تحویل سریع', 'body' => 'پیام اصلی روی زمان رسیدن به IP، رمز و ماشین آماده اتصال تمرکز دارد؛ همان چیزی که خریدار VPS می‌خواهد.'],
                            ['title' => 'شفافیت هزینه', 'body' => 'نمای مصرف اعتبار، هزینه امروز و منابع فعال نشان می‌دهد کاربر بعد از خرید کنترل مالی دارد.'],
                            ['title' => 'عملیات کامل', 'body' => 'ماشین‌ها، بکاپ، شبکه، فایروال و فعالیت‌ها همان قابلیت‌های مهم داشبورد را قبل از ثبت‌نام نشان می‌دهند.'],
                            ['title' => 'اعتماد بصری', 'body' => 'رنگ سبز برند، کارت‌های سفید، وضعیت فعال و مانیتور واقعی از داشبورد حس محصول آماده و قابل اتکا می‌سازند.'],
                        ] as $feature)
                            <article class="group rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-[#105D52]/25 hover:shadow-xl hover:shadow-slate-200/70">
                                <div class="grid size-12 place-items-center rounded-2xl bg-[#E8F3F0] text-[#105D52] transition group-hover:bg-[#105D52] group-hover:text-white">
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
                    <div class="rounded-[2rem] bg-slate-950 p-7 text-white md:p-9">
                        <p class="text-sm font-black text-emerald-300">پلن پیشنهادی آویاتو</p>
                        <h2 class="mt-4 text-4xl font-black leading-tight tracking-[-0.03em]">برای شروع پروژه وب، منابع درست را از اول انتخاب کنید.</h2>
                        <p class="mt-5 leading-8 text-slate-300">داشبورد، پلن ۲ هسته، ۴ گیگ رم و Ubuntu 24.04 را برای بیشتر پروژه‌های وب پیشنهاد می‌دهد؛ همین پیشنهاد در صفحه خانه به پیام فروش تبدیل شده است.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach ([
                            ['name' => 'شروع', 'price' => '۴۹۰٬۰۰۰', 'spec' => '۲ vCPU / ۴GB RAM / ۸۰GB NVMe', 'tone' => 'border-[#105D52] bg-[#F1F7F5]'],
                            ['name' => 'رشد', 'price' => '۹۸۰٬۰۰۰', 'spec' => '۴ vCPU / ۸GB RAM / بکاپ روزانه', 'tone' => 'border-slate-200 bg-white'],
                            ['name' => 'عملیات', 'price' => 'سفارشی', 'spec' => 'منابع اختصاصی / شبکه و فایروال', 'tone' => 'border-slate-200 bg-white'],
                        ] as $plan)
                            <article class="rounded-[1.6rem] border {{ $plan['tone'] }} p-6 shadow-sm">
                                <p class="text-sm font-black text-[#105D52]">{{ $plan['name'] }}</p>
                                <p class="mt-4 text-3xl font-black">{{ $plan['price'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">تومان / ماه</p>
                                <p class="mt-6 min-h-14 text-sm font-bold leading-7 text-slate-600">{{ $plan['spec'] }}</p>
                                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex w-full justify-center rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-[#105D52]">انتخاب پلن</a>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="regions" class="px-4 py-16 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl rounded-[2.2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-9">
                    <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-[#105D52]">موقعیت نزدیک به کاربر</p>
                            <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">از تهران و شیراز تا فرانکفورت؛ ماشین را همان‌جا بسازید که محصول نفس می‌کشد.</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ([['تهران ۱','برای کاربران ایران'], ['شیراز ۱','پشتیبان داخلی'], ['فرانکفورت','مسیر بین‌المللی']] as $region)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
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
                            <article class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm">
                                <span class="absolute -left-4 -top-8 text-8xl font-black text-[#105D52]/10">{{ $step[0] }}</span>
                                <p class="text-sm font-black text-[#105D52]">گام {{ $step[0] }}</p>
                                <h3 class="mt-4 text-2xl font-black">{{ $step[1] }}</h3>
                                <p class="mt-3 leading-8 text-slate-600">{{ $step[2] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="px-4 pb-20 pt-8 md:px-8 lg:px-10">
                <div class="mx-auto max-w-7xl overflow-hidden rounded-[2.4rem] bg-[#105D52] p-8 text-white shadow-2xl shadow-[#105D52]/20 md:p-12">
                    <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="text-sm font-black text-emerald-200">اکنون وقت ساختن است</p>
                            <h2 class="mt-3 max-w-3xl text-4xl font-black leading-tight tracking-[-0.03em] md:text-6xl">ماشین مجازی بعدی شما باید قبل از تمام شدن قهوه آماده باشد.</h2>
                        </div>
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-7 py-4 text-base font-black text-[#105D52] shadow-xl transition hover:-translate-y-0.5 hover:bg-slate-50">ورود به داشبورد و ساخت ماشین</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
