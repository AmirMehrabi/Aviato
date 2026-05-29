@extends('layouts.marketing')

@section('title', 'آویاتو | خرید سرور مجازی')
@section('description', 'خرید VPS آویاتو با دیسک NVMe، IP اختصاصی، قیمت مشخص و پشتیبانی فارسی برای راه اندازی سایت، فروشگاه و اپلیکیشن.')

@php
    $activePage = 'home';

    $marketingBundles = ($bundles ?? collect())->values();
    $heroBundle = $marketingBundles->get(1) ?? $marketingBundles->first();
    $recommendedIndex = min(1, max(0, $marketingBundles->count() - 1));

    $planMeta = [
        ['label' => 'شروع آسان', 'use' => 'سایت، وردپرس و پروژه های کوچک'],
        ['label' => 'پیشنهاد ما', 'use' => 'فروشگاه، اپلیکیشن و سرویس های در حال رشد'],
        ['label' => 'برای کارهای جدی تر', 'use' => 'دیتابیس، پردازش و سایت های پربازدید'],
        ['label' => 'منابع بیشتر', 'use' => 'تیم ها و پروژه هایی که قدرت بیشتری می خواهند'],
    ];

    $differenceRows = [
        ['title' => 'خرید ساده و سریع', 'body' => 'پلن ها را می بینید، منابع و قیمت را مقایسه می کنید و بعد سرور خود را می سازید.'],
        ['title' => 'قیمت و منابع مشخص', 'body' => 'CPU، RAM، دیسک NVMe، تعداد IP و هزینه ماهانه قبل از خرید برای شما مشخص است.'],
        ['title' => 'مناسب برای شروع کار', 'body' => 'با IP اختصاصی، دیسک سریع، بکاپ، فایروال و پشتیبانی فارسی راحت تر سرویس خود را راه اندازی می کنید.'],
    ];

    $useCases = [
        ['title' => 'سایت و وردپرس', 'body' => 'برای سایت شرکتی، وبلاگ، فروشگاه و پنل های مدیریتی.'],
        ['title' => 'اپلیکیشن و API', 'body' => 'برای سرویس هایی که باید همیشه آنلاین باشند و منابع مشخص داشته باشند.'],
        ['title' => 'دیتابیس و پردازش', 'body' => 'برای دیتابیس های سبک، کارهای پس زمینه و پردازش های روزمره.'],
        ['title' => 'تست و تمرین', 'body' => 'برای ساخت محیط تست، بررسی نسخه جدید و جدا کردن پروژه ها از هم.'],
    ];

    $steps = [
        ['number' => '01', 'title' => 'پلن را انتخاب کنید', 'body' => 'منابع و قیمت هر VPS را ببینید و پلنی را انتخاب کنید که به کار شما می آید.'],
        ['number' => '02', 'title' => 'ثبت نام و پرداخت کنید', 'body' => 'حساب کاربری بسازید، کیف پول را شارژ کنید و سفارش را در پنل مشتری ثبت کنید.'],
        ['number' => '03', 'title' => 'وارد سرور شوید', 'body' => 'بعد از آماده شدن سرور، IP و اطلاعات اتصال در پنل نمایش داده می شود.'],
    ];

    $operations = [
        ['title' => 'دیسک NVMe', 'body' => 'سرعت بهتر برای سایت، فروشگاه، دیتابیس و کارهای روزمره.'],
        ['title' => 'IP اختصاصی', 'body' => 'برای دامنه، SSL، اتصال مستقیم و جدا نگه داشتن سرویس ها.'],
        ['title' => 'بکاپ و فایروال', 'body' => 'برای کم کردن ریسک و کنترل دسترسی های مهم سرور.'],
        ['title' => 'پشتیبانی فارسی', 'body' => 'برای انتخاب پلن، شروع کار و حل مشکل های رایج سرور مجازی.'],
    ];

    $faqs = [
        ['q' => 'بعد از خرید، سرور چه زمانی آماده می شود؟', 'a' => 'بعد از ثبت سفارش و پرداخت، ساخت VPS به صورت خودکار شروع می شود. وقتی سرور آماده شد، IP و اطلاعات اتصال را در پنل مشتری می بینید.'],
        ['q' => 'برای سایت یا اپلیکیشنم کدام پلن بهتر است؟', 'a' => 'اگر تازه شروع کرده اید، معمولا یک پلن کوچک تر کافی است. برای فروشگاه، سایت پربازدید یا اپلیکیشنی که کاربر زیادی دارد، بهتر است پلنی انتخاب کنید که کمی فضای رشد هم داشته باشد.'],
        ['q' => 'آیا دسترسی کامل به سرور دارم؟', 'a' => 'بله. سرور با دسترسی مدیریتی تحویل داده می شود و می توانید وب سرور، دیتابیس، Docker و ابزارهای مورد نیاز خودتان را نصب کنید.'],
        ['q' => 'هزینه ها چطور پرداخت می شوند؟', 'a' => 'قیمت هر پلن قبل از سفارش مشخص است و پرداخت از کیف پول مشتری انجام می شود. قبل از خرید، منابع و هزینه ماهانه را می بینید.'],
        ['q' => 'اگر بعدا منابع بیشتری لازم داشته باشم چه کنم؟', 'a' => 'لازم نیست از اول بزرگ ترین پلن را بخرید. وقتی پروژه بزرگ تر شد، می توانید پلن مناسب تری انتخاب کنید یا برای انتخاب مسیر بهتر از پشتیبانی کمک بگیرید.'],
        ['q' => 'امنیت و نگهداری سرور با چه کسی است؟', 'a' => 'ما امکانات زیرساختی مثل IP اختصاصی، فایروال و بکاپ را فراهم می کنیم. نصب نرم افزارها، به روزرسانی سیستم عامل و نگهداری اپلیکیشن داخل سرور بر عهده شماست، مگر اینکه سرویس مدیریتی جداگانه داشته باشید.'],
    ];
@endphp

@section('content')
    <section id="top" class="relative isolate overflow-hidden border-b border-sky-100 bg-white px-4 pb-16 pt-12 md:px-8 md:pb-24 md:pt-20 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[76%] bg-[linear-gradient(180deg,#f1f8ff_0%,#ffffff_88%)]"></div>
        <div aria-hidden="true" class="absolute left-[-7rem] top-16 -z-10 h-72 w-72 rounded-full bg-[#0080FF]/10 blur-3xl"></div>
        <div aria-hidden="true" class="absolute right-[-6rem] top-28 -z-10 h-60 w-60 rounded-full bg-sky-200/45 blur-3xl"></div>
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full opacity-[0.35] [background-image:radial-gradient(#93c5fd_1px,transparent_1px)] [background-size:28px_28px]"></div>

        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[minmax(430px,0.92fr)_minmax(0,1.08fr)] lg:items-center">
            <div class="order-2">
                <div class="relative">
                    <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-[#0069FF]/10 blur-2xl"></div>
                    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-2xl shadow-sky-200/70">
                        <div class="flex items-center justify-between gap-4 border-b border-sky-100 bg-slate-50 px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full bg-[#ff5f57] ring-1 ring-black/5"></span>
                                <span class="size-3 rounded-full bg-[#febc2e] ring-1 ring-black/5"></span>
                                <span class="size-3 rounded-full bg-[#28c840] ring-1 ring-black/5"></span>
                            </div>
                            <div class="flex items-center gap-2 rounded-full border border-sky-100 bg-white px-3 py-1.5 text-xs font-bold text-slate-500">
                                <svg class="size-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 7h16M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                aviato.ir/servers/create
                            </div>
                            <div class="h-4 w-16"></div>
                        </div>

                        <div class="relative">
                            <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-[#0069FF]/10 blur-2xl"></div>
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
            </div>

            <div class="order-1">
                <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-normal text-slate-950 md:text-6xl">
                    سرور مجازی سریع،
                    <span class="block text-[#0069FF]">با قیمت مشخص</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-9 text-slate-600 md:text-lg">
                    VPS آویاتو برای راه اندازی سایت، فروشگاه و اپلیکیشن است. پلن ها روشن هستند، قیمت را قبل از خرید می بینید و برای شروع کار پشتیبانی فارسی دارید.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0069FF]/25 transition hover:bg-[#0050D0]">
                        خرید سرور مجازی
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-sky-200 bg-white px-7 py-4 text-base font-black text-slate-800 shadow-sm transition hover:border-[#0069FF] hover:text-[#0069FF]">
                        دیدن پلن ها و قیمت ها
                    </a>
                </div>

                {{-- <div class="mt-10 grid gap-4 border-y border-sky-100 py-6 sm:grid-cols-3">
                    @foreach ([['راه اندازی سریع', 'بعد از ثبت سفارش'], ['NVMe', 'دیسک سریع'], ['قیمت مشخص', 'قبل از پرداخت']] as $metric)
                        <div class="border-r-2 border-[#0069FF] pr-4">
                            <p class="text-2xl font-black text-slate-950">{{ $metric[0] }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-500">{{ $metric[1] }}</p>
                        </div>
                    @endforeach
                </div> --}}
            </div>
        </div>
    </section>

    <section id="plans" class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-black text-[#0069FF]">پلن های VPS</p>
                    <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">پلن مناسب خود را راحت انتخاب کنید.</h2>
                </div>
                <a href="{{ route('pricing') }}" class="inline-flex w-fit rounded-lg border border-sky-200 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-[#0069FF] hover:text-[#0069FF]">همه پلن ها</a>
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

                        <p class="mt-5 min-h-16 text-sm leading-7 text-slate-600">{{ $bundle->description ?: 'VPS آماده برای سایت، فروشگاه، اپلیکیشن و سرویس های آنلاین.' }}</p>

                        <div class="mt-7 grid grid-cols-2 gap-2 text-sm font-black text-slate-700">
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->cpu_cores }} vCPU</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->ram_gb }}GB RAM</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->disk_gb }}GB NVMe</span>
                            <span class="rounded-lg bg-white p-3 ring-1 ring-sky-100" dir="ltr">{{ $bundle->ip_count }} IP</span>
                        </div>

                        <a href="{{ route('customer.register') }}" class="mt-7 inline-flex w-full justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">
                            خرید این پلن
                        </a>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-sky-200 bg-sky-50 p-8 text-center lg:col-span-3">
                        <h3 class="text-xl font-black text-slate-950">فعلا پلنی برای نمایش وجود ندارد.</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">بعد از فعال شدن پلن ها در پنل مدیریت، قیمت ها به صورت خودکار در صفحه خانه و صفحه قیمت ها نمایش داده می شوند.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bg-[#F7FBFF] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
            <div>
                <p class="text-sm font-black text-[#0069FF]">چرا آویاتو؟</p>
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">خرید VPS بدون پیچیدگی اضافه.</h2>
                <p class="mt-5 leading-8 text-slate-600">
                    برای خرید سرور مجازی باید بدانید چه منابعی می گیرید، چقدر پرداخت می کنید و چطور سرور را تحویل می گیرید. ما همین مسیر را ساده کرده ایم.
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
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">برای سایت، فروشگاه، اپلیکیشن و تست.</h2>
                <p class="mt-5 leading-8 text-slate-600">هر پروژه به اندازه خودش منابع می خواهد. پلنی را انتخاب کنید که با نیاز امروز شما هماهنگ است و برای رشد هم جا دارد.</p>
                <a href="{{ route('solutions') }}" class="mt-8 inline-flex rounded-lg border border-sky-200 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-[#0069FF] hover:text-[#0069FF]">دیدن کاربردها</a>
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
                <h2 class="mt-3 text-3xl font-black leading-tight md:text-4xl">از انتخاب پلن تا ورود به سرور، در چند قدم ساده.</h2>
                <p class="mt-5 leading-8 text-sky-100/75">بعد از انتخاب پلن و ثبت سفارش، سرور شما ساخته می شود و اطلاعات اتصال در پنل قرار می گیرد.</p>
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
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">قبل از خرید، همه چیز را واضح ببینید.</h2>
                <p class="mt-5 leading-8 text-slate-600">منابع، دسترسی، پشتیبانی و هزینه ماهانه باید قبل از پرداخت برای شما روشن باشد.</p>
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
                <h2 class="mt-3 text-3xl font-black leading-tight text-slate-950 md:text-4xl">پاسخ چند سوال رایج.</h2>
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
                    <h2 class="max-w-3xl text-3xl font-black leading-tight md:text-4xl">پلن خود را انتخاب کنید و سرور مجازی بسازید.</h2>
                    <p class="mt-4 max-w-2xl leading-8 text-blue-50">اگر آماده خرید هستید، ثبت نام کنید. اگر هنوز مقایسه می کنید، پلن ها و قیمت ها را کامل ببینید.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-7 py-4 text-base font-black text-[#0069FF] transition hover:bg-blue-50">خرید سرور مجازی</a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-7 py-4 text-base font-black text-white transition hover:bg-white/10">دیدن پلن ها و قیمت ها</a>
                </div>
            </div>
        </div>
    </section>
@endsection
