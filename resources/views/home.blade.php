@extends('layouts.marketing')

@section('title', 'آویاتو | خرید Droplet ابری سریع با NVMe')
@section('description', 'Droplet ابری آویاتو را در کمتر از یک دقیقه بسازید. NVMe، IP اختصاصی، بکاپ، پرداخت شفاف و پشتیبانی فارسی برای VPSهای آماده production.')

@php
    $activePage = 'home';

    $droplets = [
        [
            'name' => 'Starter',
            'label' => 'شروع سریع',
            'price' => '۴۹۰٬۰۰۰',
            'use' => 'لندینگ، وردپرس، API سبک',
            'cpu' => '۲ vCPU',
            'ram' => '۴GB RAM',
            'disk' => '۸۰GB NVMe',
            'tone' => 'border-slate-200 bg-white',
        ],
        [
            'name' => 'Growth',
            'label' => 'پیشنهاد خرید',
            'price' => '۹۸۰٬۰۰۰',
            'use' => 'SaaS، فروشگاه، production',
            'cpu' => '۴ vCPU',
            'ram' => '۸GB RAM',
            'disk' => '۱۶۰GB NVMe',
            'tone' => 'border-[#0080FF] bg-[#EAF4FF] shadow-xl shadow-[#0080FF]/10',
        ],
        [
            'name' => 'Scale',
            'label' => 'برای بار جدی',
            'price' => '۲٬۴۵۰٬۰۰۰',
            'use' => 'دیتابیس، صف، سرویس پرترافیک',
            'cpu' => '۸ vCPU',
            'ram' => '۱۶GB RAM',
            'disk' => '۳۲۰GB NVMe',
            'tone' => 'border-slate-200 bg-white',
        ],
    ];

    $proofPoints = [
        ['title' => 'NVMe برای پاسخ سریع', 'body' => 'دیسک سریع برای وب اپ، دیتابیس و سرویس هایی که latency برایشان مهم است.'],
        ['title' => 'هزینه قابل پیش بینی', 'body' => 'پلن روشن، کیف پول اعتباری و امکان ارتقا بدون قراردادهای طولانی.'],
        ['title' => 'امنیت آماده شروع', 'body' => 'IP اختصاصی، فایروال، بکاپ و شبکه خصوصی برای مسیر production.'],
        ['title' => 'پشتیبانی فارسی', 'body' => 'وقتی خرید می کنید، تیم فارسی زبان برای راه اندازی و رفع مشکل کنار شماست.'],
    ];

    $steps = [
        ['step' => '۱', 'title' => 'ثبت نام کنید', 'body' => 'حساب مشتری بسازید و کیف پول را برای شروع شارژ کنید.'],
        ['step' => '۲', 'title' => 'سیستم عامل را انتخاب کنید', 'body' => 'Ubuntu، Debian، Rocky یا ایمیج آماده مورد نیازتان را بردارید.'],
        ['step' => '۳', 'title' => 'Droplet را بسازید', 'body' => 'منابع، موقعیت و نام سرور را انتخاب کنید؛ IP و دسترسی آماده می شود.'],
        ['step' => '۴', 'title' => 'با SSH وصل شوید', 'body' => 'رمز یا کلید SSH را بردارید و سرویس را روی سرور جدید اجرا کنید.'],
    ];

    $useCases = [
        ['title' => 'میزبانی Laravel و WordPress', 'body' => 'برای سایت های سریع با IP اختصاصی، فضای NVMe و دسترسی کامل root.'],
        ['title' => 'SaaS و API production', 'body' => 'برای سرویس هایی که نیاز به منابع مشخص، ارتقای سریع و هزینه قابل کنترل دارند.'],
        ['title' => 'دیتابیس و Worker', 'body' => 'برای نودهای دیتابیس، صف، پردازش پس زمینه و سرویس های داخلی.'],
        ['title' => 'محیط توسعه و staging', 'body' => 'برای تست release، ساخت sandbox و اجرای نسخه های جدا از production.'],
    ];

    $faqs = [
        ['q' => 'بعد از خرید چقدر طول می کشد سرور آماده شود؟', 'a' => 'هدف ما آماده سازی در کمتر از ۶۰ ثانیه است؛ بعد از ساخت، IP و اطلاعات اتصال در پنل مشتری نمایش داده می شود.'],
        ['q' => 'برای شروع باید قرارداد بلندمدت ببندم؟', 'a' => 'خیر. مدل خرید بر پایه پلن های شفاف و کیف پول اعتباری است و هر زمان نیاز داشتید می توانید منابع را تغییر دهید.'],
        ['q' => 'کدام سیستم عامل ها قابل انتخاب هستند؟', 'a' => 'ایمیج های آماده مثل Ubuntu، Debian و Rocky در پنل ساخت VPS نمایش داده می شوند و با cloud-init آماده تحویل هستند.'],
        ['q' => 'اگر برای انتخاب پلن مطمئن نباشم چه کنم؟', 'a' => 'از پلن Growth برای بیشتر پروژه های production شروع کنید یا از صفحه قیمت گذاری جزئیات پلن ها را بررسی کنید.'],
    ];
@endphp

@section('content')
    <section id="top" class="relative isolate overflow-hidden bg-white px-4 pb-16 pt-14 md:px-8 md:pb-24 md:pt-22 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[72%] bg-[linear-gradient(180deg,#EAF4FF_0%,#FFFFFF_86%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.95fr)_minmax(420px,1.05fr)] lg:items-center">
            <div>
                <h1 class="max-w-4xl text-4xl font-black leading-[1.25] tracking-normal text-slate-950 md:text-6xl">
                    Droplet ابری بخرید؛
                    <span class="block text-[#0069FF]">در کمتر از یک دقیقه آنلاین شوید.</span>
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-9 text-slate-600 md:text-xl">
                    VPS سریع با دیسک NVMe، IP اختصاصی، بکاپ، شبکه خصوصی و پشتیبانی فارسی. مناسب لانچ محصول، فروشگاه، API و هر پروژه ای که همین امروز باید اجرا شود.
                </p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0069FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0069FF]/25 transition hover:bg-[#0050D0]">
                        خرید و ساخت Droplet
                        <svg class="size-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-7 py-4 text-base font-black text-slate-800 shadow-sm transition hover:border-[#0080FF] hover:text-[#0069FF]">
                        مقایسه قیمت ها
                    </a>
                </div>
                <div class="mt-10 grid gap-3 sm:grid-cols-3">
                    @foreach ([['۶۰ ثانیه', 'تحویل هدف گذاری شده'], ['NVMe', 'دیسک سریع برای production'], ['فارسی', 'پشتیبانی فروش و فنی']] as $metric)
                        <div class="border-r-2 border-[#0080FF] pr-4">
                            <p class="text-2xl font-black text-slate-950">{{ $metric[0] }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-500">{{ $metric[1] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative">
                <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-[#0080FF]/10 blur-2xl"></div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 shadow-2xl shadow-slate-900/20">
                    <div class="flex items-center justify-between border-b border-white/10 bg-[#07172D] px-5 py-4 text-white">
                        <div>
                            <p class="text-xs font-black text-[#8FC7FF]">Create Droplet</p>
                            <p class="mt-1 text-lg font-black">aviato-growth-01</p>
                        </div>
                        <span class="rounded-md bg-emerald-400/15 px-3 py-1 text-xs font-black text-emerald-200">Ready</span>
                    </div>
                    <div class="grid gap-0 md:grid-cols-[1fr_230px]">
                        <div class="space-y-4 bg-white p-5">
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ([['Ubuntu', '22.04 LTS'], ['Tehran', 'Region 1'], ['4 vCPU', 'Dedicated'], ['160GB', 'NVMe']] as $item)
                                    <div class="rounded-lg border border-slate-200 bg-[#F7FBFF] p-4">
                                        <p class="text-base font-black text-slate-950">{{ $item[0] }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-500">{{ $item[1] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="rounded-lg border border-[#B8D6FF] bg-[#EAF4FF] p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm font-black text-slate-700">Growth Droplet</span>
                                    <span class="rounded-md bg-[#0069FF] px-3 py-1 text-xs font-black text-white">پیشنهادی</span>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs font-black text-slate-700">
                                    <span class="rounded-md bg-white px-2 py-3">۴ CPU</span>
                                    <span class="rounded-md bg-white px-2 py-3">۸GB RAM</span>
                                    <span class="rounded-md bg-white px-2 py-3">Backup</span>
                                </div>
                            </div>
                        </div>
                        <aside class="bg-[#07172D] p-5 text-white">
                            <p class="text-sm font-black text-[#8FC7FF]">خلاصه خرید</p>
                            <div class="mt-5 space-y-4 text-sm">
                                <div class="flex justify-between gap-4"><span class="text-slate-300">پلن</span><span class="font-black">Growth</span></div>
                                <div class="flex justify-between gap-4"><span class="text-slate-300">IP عمومی</span><span class="font-black">۱ عدد</span></div>
                                <div class="flex justify-between gap-4"><span class="text-slate-300">بکاپ</span><span class="font-black">روزانه</span></div>
                            </div>
                            <div class="mt-8 border-t border-white/10 pt-5">
                                <p class="text-xs font-bold text-slate-300">هزینه ماهانه</p>
                                <p class="mt-2 text-3xl font-black">۹۸۰٬۰۰۰</p>
                                <p class="mt-1 text-xs text-slate-400">تومان / ماه</p>
                            </div>
                            <a href="{{ route('customer.register') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-[#0080FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0069FF]">شروع خرید</a>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="droplets" class="bg-white px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-black text-[#0069FF]">انتخاب سریع Droplet</p>
                    <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">با یک پلن روشن شروع کنید، بعدا ارتقا دهید</h2>
                </div>
                <a href="{{ route('pricing') }}" class="inline-flex w-fit items-center justify-center rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:border-[#0080FF] hover:text-[#0069FF]">دیدن همه قیمت ها</a>
            </div>
            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @foreach ($droplets as $droplet)
                    <article class="relative rounded-xl border p-6 {{ $droplet['tone'] }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-black text-[#0069FF]">{{ $droplet['label'] }}</p>
                                <h3 class="mt-2 text-3xl font-black text-slate-950">{{ $droplet['name'] }}</h3>
                            </div>
                            <span class="rounded-md bg-slate-950 px-3 py-1 text-xs font-black text-white">{{ $droplet['use'] }}</span>
                        </div>
                        <p class="mt-6 text-4xl font-black text-slate-950">{{ $droplet['price'] }}</p>
                        <p class="mt-1 text-sm font-bold text-slate-500">تومان / ماه</p>
                        <div class="mt-7 grid grid-cols-3 gap-2 text-center text-xs font-black text-slate-700">
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $droplet['cpu'] }}</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $droplet['ram'] }}</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-slate-200">{{ $droplet['disk'] }}</span>
                        </div>
                        <a href="{{ route('customer.register') }}" class="mt-7 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">انتخاب و ساخت</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#06162E] px-4 py-20 text-white md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
            <div>
                <p class="text-sm font-black text-[#8FC7FF]">چرا از آویاتو بخرید؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">زیرساختی که مانع لانچ محصول نمی شود</h2>
                <p class="mt-5 leading-8 text-slate-300">صفحه خرید باید تصمیم را آسان کند: منابع روشن، تحویل سریع، مسیر اتصال مشخص و تیمی که وقتی مشکلی پیش آمد پاسخ می دهد.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($proofPoints as $point)
                    <article class="rounded-xl border border-white/10 bg-white/[0.04] p-5">
                        <div class="grid size-10 place-items-center rounded-lg bg-[#0080FF] text-white">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3 class="mt-5 text-xl font-black">{{ $point['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-300">{{ $point['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">مسیر خرید</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">از ثبت نام تا SSH، بدون رفت و برگشت اضافی</h2>
            </div>
            <div class="mt-10 grid gap-4 md:grid-cols-4">
                @foreach ($steps as $item)
                    <article class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span class="absolute -left-3 -top-7 text-8xl font-black text-[#EAF4FF]">{{ $item['step'] }}</span>
                        <p class="relative text-sm font-black text-[#0069FF]">گام {{ $item['step'] }}</p>
                        <h3 class="relative mt-4 text-xl font-black text-slate-950">{{ $item['title'] }}</h3>
                        <p class="relative mt-3 text-sm leading-7 text-slate-600">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#0069FF]">برای چه کاری می خرید؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">Droplet مناسب برای کارهای واقعی تیم شما</h2>
                <p class="mt-5 leading-8 text-slate-600">به جای خرید سرور مبهم، از use case شروع کنید. اگر پروژه رشد کرد، منابع را از پنل ارتقا دهید.</p>
                <a href="{{ route('solutions') }}" class="mt-7 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-[#0080FF] hover:text-[#0069FF]">دیدن راهکارها</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($useCases as $case)
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-slate-200/70">
                        <h3 class="text-xl font-black text-slate-950">{{ $case['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $case['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#07172D] px-4 py-20 text-white md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#8FC7FF]">اطمینان قبل از پرداخت</p>
                <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">برای production طراحی شده، نه فقط تست کوتاه</h2>
                <p class="mt-5 max-w-3xl leading-8 text-slate-300">IP اختصاصی، بکاپ، فایروال و شبکه خصوصی کمک می کنند اولین Droplet را با خیال راحت تر وارد کار واقعی کنید. جزئیات هر منبع و هزینه در پنل مشتری قابل مشاهده است.</p>
            </div>
            <div class="grid gap-3">
                @foreach ([['IP اختصاصی', 'برای DNS، SSL و اتصال مستقیم'], ['بکاپ', 'برای برگشت از خطاهای انسانی و تغییرات ناخواسته'], ['شبکه خصوصی', 'برای ارتباط امن تر بین سرویس ها'], ['فایروال', 'برای محدود کردن دسترسی های حساس']] as $item)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-white/10 bg-white/[0.04] px-5 py-4">
                        <span class="font-black">{{ $item[0] }}</span>
                        <span class="text-sm text-slate-300">{{ $item[1] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
            <div class="rounded-xl bg-[#EAF4FF] p-6 md:p-8">
                <p class="text-sm font-black text-[#0069FF]">قیمت گذاری بدون غافلگیری</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950">قبل از خرید، هزینه را واضح ببینید</h2>
                <p class="mt-5 leading-8 text-slate-600">از پلن آماده شروع کنید، کیف پول را شارژ کنید و در صورت رشد پروژه منابع را افزایش دهید. مسیر خرید برای تیم هایی ساخته شده که نمی خواهند درگیر مذاکره و قرارداد طولانی شوند.</p>
                <a href="{{ route('pricing') }}" class="mt-7 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">بررسی قیمت ها</a>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ([['پلن روشن', 'CPU، RAM، دیسک و IP قبل از خرید مشخص است.'], ['ارتقای سریع', 'با رشد پروژه، پلن بزرگ تر انتخاب می کنید.'], ['کیف پول', 'پرداخت و مصرف از پنل مشتری قابل پیگیری است.']] as $item)
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-black text-slate-950">{{ $item[0] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $item[1] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-5xl">
            <div class="text-center">
                <p class="text-sm font-black text-[#0069FF]">سوالات قبل از خرید</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-5xl">تصمیم آخر را راحت تر بگیرید</h2>
            </div>
            <div class="mt-10 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white shadow-sm">
                @foreach ($faqs as $faq)
                    <details class="group p-5 open:bg-white md:p-6" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-right text-lg font-black text-slate-950">
                            {{ $faq['q'] }}
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#EAF4FF] text-[#0069FF] transition group-open:rotate-45">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                            </span>
                        </summary>
                        <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 pb-20 pt-4 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-2xl bg-[#0069FF] p-7 text-white shadow-2xl shadow-[#0069FF]/20 md:p-12">
            <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="max-w-3xl text-3xl font-black leading-tight md:text-5xl">اولین Droplet خود را همین حالا بسازید.</h2>
                    <p class="mt-4 max-w-2xl leading-8 text-blue-50">ثبت نام کنید، پلن را انتخاب کنید و قبل از اینکه مسیر لانچ کند شود، سرور آماده تحویل بگیرید.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#0069FF] shadow-xl transition hover:bg-blue-50">خرید Droplet</a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-7 py-4 text-base font-black text-white transition hover:bg-white/10">دیدن قیمت ها</a>
                </div>
            </div>
        </div>
    </section>
@endsection
