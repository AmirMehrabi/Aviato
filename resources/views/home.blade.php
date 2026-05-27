@extends('layouts.marketing')

@section('title', 'آویاتو | خرید VPS ابری سریع و شفاف')
@section('description', 'VPS ابری آویاتو با دیسک NVMe، IP اختصاصی، منابع شفاف، قیمت قابل پیش بینی و پشتیبانی فارسی برای اجرای سریع سرویس ها.')

@php
    $activePage = 'home';

    $marketingBundles = ($bundles ?? collect())->values();
    $heroBundle = $marketingBundles->get(1) ?? $marketingBundles->first();
    $recommendedIndex = min(1, max(0, $marketingBundles->count() - 1));

    $planMeta = [
        ['label' => 'شروع سریع', 'use' => 'سایت، وردپرس، API سبک'],
        ['label' => 'پیشنهاد خرید', 'use' => 'SaaS، فروشگاه، production'],
        ['label' => 'بار پایدار', 'use' => 'دیتابیس، worker، سرویس پرترافیک'],
        ['label' => 'منابع بیشتر', 'use' => 'تیم های عملیاتی و پروژه های سنگین'],
    ];

    $differenceRows = [
        ['title' => 'مسیر خرید کوتاه تر', 'body' => 'به جای کاتالوگ شلوغ سرویس های ابری، روی انتخاب VPS، منابع، قیمت و ساخت سرور تمرکز می کنید.'],
        ['title' => 'منابع و قیمت شفاف', 'body' => 'CPU، RAM، دیسک NVMe، IP و هزینه ماهانه قبل از خرید روشن است و از باندل های فعال پنل خوانده می شود.'],
        ['title' => 'آماده برای اجرای واقعی', 'body' => 'IP اختصاصی، دیسک سریع، بکاپ، فایروال و پشتیبانی فارسی کمک می کند سرویس را زودتر به production برسانید.'],
    ];

    $useCases = [
        ['title' => 'Laravel و WordPress', 'body' => 'برای سایت، پنل، فروشگاه و API با IP اختصاصی و دسترسی کامل.'],
        ['title' => 'SaaS و API', 'body' => 'برای سرویس هایی که به منابع مشخص، هزینه قابل کنترل و ارتقای سریع نیاز دارند.'],
        ['title' => 'Database و Worker', 'body' => 'برای نودهای پردازشی، صف، دیتابیس سبک و سرویس های داخلی تیم.'],
        ['title' => 'Staging و تست', 'body' => 'برای محیط های جدا، تست release و ساخت sandbox بدون درگیری با زیرساخت اصلی.'],
    ];

    $steps = [
        ['number' => '01', 'title' => 'پلن را انتخاب کنید', 'body' => 'منابع هر VPS قبل از خرید مشخص است؛ از کارت های همین صفحه یا قیمت ها شروع کنید.'],
        ['number' => '02', 'title' => 'حساب را آماده کنید', 'body' => 'ثبت نام کنید، کیف پول را شارژ کنید و خرید را در پنل مشتری ادامه دهید.'],
        ['number' => '03', 'title' => 'با SSH وصل شوید', 'body' => 'بعد از ساخت، IP و اطلاعات اتصال در پنل نمایش داده می شود.'],
    ];

    $operations = [
        ['title' => 'NVMe', 'body' => 'دیسک سریع برای وب اپ، دیتابیس و پردازش های روزمره.'],
        ['title' => 'IP اختصاصی', 'body' => 'برای DNS، SSL، اتصال مستقیم و جداسازی سرویس.'],
        ['title' => 'بکاپ و فایروال', 'body' => 'برای کاهش ریسک خطای انسانی و محدود کردن دسترسی های حساس.'],
        ['title' => 'پشتیبانی فارسی', 'body' => 'برای انتخاب پلن، شروع کار و رفع مشکل های رایج VPS.'],
    ];

    $faqs = [
        ['q' => 'آویاتو چه تفاوتی با ابرهای عمومی بزرگ دارد؟', 'a' => 'تمرکز این صفحه روی خرید VPS است. منابع مشخص، قیمت روشن و مسیر خرید کوتاه دارید، نه فهرست طولانی سرویس های نامرتبط.'],
        ['q' => 'آیا قیمت ها دستی در صفحه نوشته شده اند؟', 'a' => 'خیر. پلن های این صفحه از باندل های فعال پنل مدیریت خوانده می شوند و با تغییر قیمت یا منابع، نمایش عمومی هم به روز می شود.'],
        ['q' => 'برای شروع باید قرارداد بلندمدت داشته باشم؟', 'a' => 'خیر. خرید بر پایه پلن های مشخص و کیف پول اعتباری انجام می شود تا شروع کار ساده و قابل کنترل باشد.'],
        ['q' => 'اگر ندانم کدام پلن مناسب است چه کنم؟', 'a' => 'از پلن پیشنهادی شروع کنید یا در صفحه قیمت ها منابع را مقایسه کنید. انتخاب بهتر باید بر اساس مصرف واقعی CPU، RAM و دیسک باشد.'],
    ];
@endphp

@section('content')
    <section id="top" class="relative isolate overflow-hidden border-b border-sky-100 bg-white px-4 pb-16 pt-12 md:px-8 md:pb-24 md:pt-20 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[76%] bg-[linear-gradient(180deg,#f1f8ff_0%,#ffffff_88%)]"></div>
        <div aria-hidden="true" class="absolute left-[-7rem] top-16 -z-10 h-72 w-72 rounded-full bg-[#0080FF]/10 blur-3xl"></div>
        <div aria-hidden="true" class="absolute right-[-6rem] top-28 -z-10 h-60 w-60 rounded-full bg-sky-200/45 blur-3xl"></div>
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full opacity-[0.35] [background-image:radial-gradient(#93c5fd_1px,transparent_1px)] [background-size:28px_28px]"></div>

        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[minmax(430px,0.92fr)_minmax(0,1.08fr)] lg:items-center">
            <div class="order-2 lg:order-1">
                <div class="relative">
                    <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-[#0069FF]/10 blur-2xl"></div>
                    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-2xl shadow-sky-200/70">
                        <div class="flex items-center justify-between gap-4 border-b border-sky-100 bg-slate-950 px-5 py-4 text-white">
                            <div>
                                <p class="text-xs font-black text-sky-300">Create VPS</p>
                                <p class="mt-1 text-lg font-black">aviato-cloud-01</p>
                            </div>
                            <span class="rounded-md bg-emerald-400/15 px-3 py-1 text-xs font-black text-emerald-200">Ready</span>
                        </div>

                        <div class="grid gap-0 md:grid-cols-[1fr_220px]">
                            <div class="space-y-4 bg-white p-5">
                                <div class="grid grid-cols-2 gap-3">
                                    @foreach ([['Ubuntu', '22.04 LTS'], ['Tehran', 'Region 1'], [($heroBundle?->cpu_cores ?? 4) . ' vCPU', 'Dedicated'], [$heroBundle ? $heroBundle->disk_gb . 'GB' : 'NVMe', 'NVMe']] as $item)
                                        <div class="rounded-lg border border-sky-100 bg-sky-50/70 p-4">
                                            <p class="text-base font-black text-slate-950" dir="ltr">{{ $item[0] }}</p>
                                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $item[1] }}</p>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="rounded-xl border border-[#B8D6FF] bg-[#EAF4FF] p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-black text-[#0069FF]">پلن پیشنهادی</p>
                                            <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $heroBundle?->name ?? 'VPS آماده' }}</h2>
                                        </div>
                                        <span class="rounded-md bg-[#0069FF] px-3 py-1 text-xs font-black text-white">پیشنهادی</span>
                                    </div>
                                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs font-black text-slate-700">
                                        <span class="rounded-md bg-white px-2 py-3" dir="ltr">{{ $heroBundle?->cpu_cores ?? '4' }} CPU</span>
                                        <span class="rounded-md bg-white px-2 py-3" dir="ltr">{{ $heroBundle?->ram_gb ?? '8' }}GB RAM</span>
                                        <span class="rounded-md bg-white px-2 py-3" dir="ltr">{{ $heroBundle?->ip_count ?? 1 }} IP</span>
                                    </div>
                                </div>
                            </div>

                            <aside class="bg-slate-950 p-5 text-white">
                                <p class="text-sm font-black text-sky-300">خلاصه خرید</p>
                                <div class="mt-5 space-y-4 text-sm">
                                    <div class="flex justify-between gap-4"><span class="text-slate-300">پلن</span><span class="font-black">{{ $heroBundle?->name ?? 'Cloud VPS' }}</span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-300">IP عمومی</span><span class="font-black">{{ $heroBundle?->ip_count ?? 1 }} عدد</span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-300">دیسک</span><span class="font-black" dir="ltr">{{ $heroBundle ? $heroBundle->disk_gb . 'GB' : 'NVMe' }}</span></div>
                                </div>
                                <div class="mt-8 border-t border-white/10 pt-5">
                                    <p class="text-xs font-bold text-slate-300">هزینه ماهانه</p>
                                    <p class="mt-2 text-3xl font-black">{{ $heroBundle ? $wallets->format($heroBundle->monthly_price) : 'مشاهده قیمت' }}</p>
                                </div>
                                <a href="{{ route('customer.register') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ساخت سرور</a>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-normal text-slate-950 md:text-6xl">
                    VPS ابری سریع،
                    <span class="block text-[#0069FF]">شفاف و آماده اجرا</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-9 text-slate-600 md:text-lg">
                    سرور مجازی با دیسک NVMe، IP اختصاصی، قیمت قابل پیش بینی و پشتیبانی فارسی. برای تیم هایی که می خواهند سریع خرید کنند و سرویس را اجرا کنند.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0069FF]/25 transition hover:bg-[#0050D0]">
                        شروع خرید VPS
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-sky-200 bg-white px-7 py-4 text-base font-black text-slate-800 shadow-sm transition hover:border-[#0069FF] hover:text-[#0069FF]">
                        مشاهده قیمت ها
                    </a>
                </div>

                <div class="mt-10 grid gap-4 border-y border-sky-100 py-6 sm:grid-cols-3">
                    @foreach ([['زیر ۶۰ ثانیه', 'هدف تحویل سرور'], ['NVMe', 'دیسک سریع برای اجرا'], ['قیمت روشن', 'قبل از ثبت سفارش']] as $metric)
                        <div class="border-r-2 border-[#0069FF] pr-4">
                            <p class="text-2xl font-black text-slate-950">{{ $metric[0] }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-500">{{ $metric[1] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="plans" class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-black text-[#0069FF]">پلن های VPS</p>
                    <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">منابع را ببینید، قیمت را بدانید، سرور را بسازید.</h2>
                    <p class="mt-4 leading-8 text-slate-600">کارت ها از باندل های فعال پنل مدیریت خوانده می شوند؛ قیمت و منابع دستی و نمایشی نیستند.</p>
                </div>
                <a href="{{ route('pricing') }}" class="inline-flex w-fit rounded-lg border border-sky-200 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-[#0069FF] hover:text-[#0069FF]">همه قیمت ها</a>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @forelse ($marketingBundles as $bundle)
                    @php
                        $meta = $planMeta[$loop->index] ?? $planMeta[3];
                        $isRecommended = $loop->index === $recommendedIndex;
                    @endphp
                    <article class="relative rounded-2xl border bg-white p-6 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-sky-100 {{ $isRecommended ? 'border-[#0069FF] bg-[#F7FBFF] shadow-xl shadow-[#0069FF]/10' : 'border-sky-100' }}">
                        @if ($isRecommended)
                            <span class="absolute left-5 top-5 rounded-md bg-[#0069FF] px-3 py-1 text-xs font-black text-white">پیشنهادی</span>
                        @endif

                        <p class="text-sm font-black text-[#0069FF]">{{ $meta['label'] }}</p>
                        <h3 class="mt-3 text-3xl font-black text-slate-950">{{ $bundle->name }}</h3>
                        <p class="mt-2 min-h-7 text-sm font-bold text-slate-500">{{ $meta['use'] }}</p>

                        <div class="mt-7 border-y border-sky-100 py-5">
                            <p class="text-4xl font-black text-slate-950">{{ $wallets->format($bundle->monthly_price) }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-500">ماهانه</p>
                        </div>

                        <p class="mt-5 min-h-16 text-sm leading-7 text-slate-600">{{ $bundle->description ?: 'VPS آماده برای اجرای سرویس های وب، API و محیط production.' }}</p>

                        <div class="mt-7 grid grid-cols-2 gap-2 text-sm font-black text-slate-700">
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->cpu_cores }} vCPU</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->ram_gb }}GB RAM</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->disk_gb }}GB NVMe</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->ip_count }} IP</span>
                        </div>

                        <a href="{{ route('customer.register') }}" class="mt-7 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">
                            انتخاب این پلن
                        </a>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-sky-200 bg-sky-50 p-8 text-center lg:col-span-3">
                        <h3 class="text-xl font-black text-slate-950">هنوز پلن فعالی برای نمایش منتشر نشده است.</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">بعد از فعال کردن باندل ها در پنل مدیریت، قیمت ها به صورت خودکار در صفحه خانه و قیمت گذاری نمایش داده می شوند.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
            <div>
                <p class="text-sm font-black text-[#0069FF]">چرا آویاتو؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">خرید VPS بدون کاتالوگ شلوغ ابرهای عمومی.</h2>
                <p class="mt-5 leading-8 text-slate-600">
                    تصمیم خرید باید کوتاه باشد: پلن، منابع، قیمت و ساخت سرور. جزئیات اضافه فقط وقتی لازم است که به اجرای سرویس کمک کند.
                </p>
            </div>

            <div class="grid gap-4">
                @foreach ($differenceRows as $row)
                    <article class="rounded-2xl border border-sky-100 bg-white p-6 shadow-sm shadow-sky-100">
                        <h3 class="text-xl font-black text-slate-950">{{ $row['title'] }}</h3>
                        <p class="mt-3 text-sm leading-8 text-slate-600">{{ $row['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
            <div>
                <p class="text-sm font-black text-[#0069FF]">برای چه کاری می خرید؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">VPS مناسب برای کارهای واقعی تیم شما.</h2>
                <p class="mt-5 leading-8 text-slate-600">از سایت و API تا staging و worker، پلن باید با مصرف واقعی تیم هماهنگ باشد.</p>
                <a href="{{ route('solutions') }}" class="mt-8 inline-flex rounded-lg border border-sky-200 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-[#0069FF] hover:text-[#0069FF]">دیدن راهکارها</a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($useCases as $case)
                    <article class="rounded-2xl border border-sky-100 bg-white p-6 shadow-sm shadow-sky-100 transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-sky-100">
                        <div class="mb-5 grid size-10 place-items-center rounded-lg bg-[#EAF4FF] text-[#0069FF]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3 class="text-xl font-black text-slate-950">{{ $case['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $case['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#06152B] px-4 py-20 text-white md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-black text-sky-300">مسیر خرید</p>
                <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">از انتخاب پلن تا SSH، سه مرحله روشن.</h2>
                <p class="mt-5 leading-8 text-sky-100/75">فرآیند خرید باید زمان تیم را کم کند، نه اینکه قبل از اجرای سرویس یک پروژه جانبی بسازد.</p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-3">
                @foreach ($steps as $step)
                    <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-6">
                        <p class="text-sm font-black text-sky-300">{{ $step['number'] }}</p>
                        <h3 class="mt-7 text-2xl font-black text-white">{{ $step['title'] }}</h3>
                        <p class="mt-4 text-sm leading-8 text-sky-100/75">{{ $step['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.75fr_1.25fr]">
            <div>
                <p class="text-sm font-black text-[#0069FF]">اطمینان قبل از پرداخت</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">چیزهایی که قبل از خرید باید مشخص باشند.</h2>
                <p class="mt-5 leading-8 text-slate-600">خرید VPS برای production یعنی منابع، دسترسی، پشتیبانی و هزینه باید قبل از پرداخت قابل فهم باشد.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($operations as $item)
                    <article class="rounded-2xl border border-sky-100 bg-[#F7FBFF] p-6">
                        <h3 class="text-xl font-black text-slate-950">{{ $item['title'] }}</h3>
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-5xl">
            <div class="text-center">
                <p class="text-sm font-black text-[#0069FF]">سوالات قبل از خرید</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">تصمیم آخر را راحت تر بگیرید.</h2>
            </div>

            <div class="mt-10 divide-y divide-sky-100 rounded-2xl border border-sky-100 bg-white shadow-sm shadow-sky-100">
                @foreach ($faqs as $faq)
                    <details class="group p-5 open:bg-white md:p-6" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-right text-lg font-black text-slate-950">
                            {{ $faq['q'] }}
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#EAF4FF] text-[#0069FF] transition group-open:rotate-45">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                            </span>
                        </summary>
                        <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#0069FF] p-7 text-white shadow-2xl shadow-[#0069FF]/20 md:p-12">
            <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="max-w-3xl text-3xl font-black leading-tight md:text-5xl">VPS را انتخاب کنید و سرور را وارد کار واقعی کنید.</h2>
                    <p class="mt-4 max-w-2xl leading-8 text-blue-50">اگر تصمیم خرید روشن است، ثبت نام کنید. اگر هنوز مقایسه می کنید، قیمت ها و منابع را کامل ببینید.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#0069FF] transition hover:bg-blue-50">شروع خرید VPS</a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-7 py-4 text-base font-black text-white transition hover:bg-white/10">مشاهده قیمت ها</a>
                </div>
            </div>
        </div>
    </section>
@endsection
