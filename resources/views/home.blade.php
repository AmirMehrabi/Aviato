@extends('layouts.marketing')

@section('title', 'آویاتو | از زمین تا ابر، با اطمینان')
@section('description', 'خرید ماشین مجازی آویاتو با دیسک NVMe، IP اختصاصی، قیمت روشن و پشتیبانی فارسی برای راه اندازی سایت، فروشگاه و اپلیکیشن.')

@php
    $activePage = 'home';

    $marketingBundles = ($bundles ?? collect())->values();
    $recommendedIndex = min(1, max(0, $marketingBundles->count() - 1));

    $planMeta = [
        ['label' => 'شروع سبک', 'use' => 'برای سایت، وردپرس و پروژه های کوچک'],
        ['label' => 'انتخاب پیشنهادی', 'use' => 'برای فروشگاه، اپلیکیشن و سرویس های در حال رشد'],
        ['label' => 'منابع بیشتر', 'use' => 'برای دیتابیس، پردازش و سایت های پربازدید'],
        ['label' => 'برای تیم ها', 'use' => 'برای پروژه هایی که به ظرفیت بالاتری نیاز دارند'],
    ];

    $currentCustomers = [
        ['name' => 'مبیت', 'url' => 'https://mobit.ir', 'logo' => 'assets/images/customers/mobit-logo.svg'],
        ['name' => 'حسابرو', 'url' => 'https://hesabro.ir', 'logo' => 'assets/images/customers/hesabro-logo.png'],
        ['name' => 'کارخانه نوآوری کرمان', 'url' => 'https://kermanif.ir', 'logo' => 'assets/images/customers/kermanif-logo.png'],
    ];

    $differenceRows = [
        ['title' => 'خرید بدون ابهام', 'body' => 'قبل از پرداخت می دانید چه منابعی می گیرید، هزینه چقدر است و سفارش از کجا پیگیری می شود.'],
        ['title' => 'شروع قابل پیش بینی', 'body' => 'پس از ثبت سفارش، ساخت ماشین مجازی شروع می شود و اطلاعات اتصال داخل پنل مشتری قرار می گیرد.'],
        ['title' => 'پشتیبانی قابل فهم', 'body' => 'برای انتخاب پلن، شروع کار و رفع سوال های رایج، با تیم فارسی زبان در ارتباط هستید.'],
    ];

    $useCases = [
        ['title' => 'سایت و وردپرس', 'icon' => 'global-edit.svg'],
        ['title' => 'اپلیکیشن و API', 'icon' => 'bag-happy.svg'],
        ['title' => 'دیتابیس و پردازش', 'icon' => 'data-2.svg'],
        ['title' => 'تست و توسعه', 'icon' => 'cloud-connection.svg'],
    ];

    $steps = [
        ['number' => '01', 'title' => 'پلن را انتخاب کنید', 'body' => 'منابع، قیمت و تعداد IP را می بینید و پلن مناسب پروژه را انتخاب می کنید.'],
        ['number' => '02', 'title' => 'حساب را شارژ کنید', 'body' => 'در پنل مشتری ثبت نام می کنید، کیف پول را شارژ می کنید و سفارش را ثبت می کنید.'],
        ['number' => '03', 'title' => 'اطلاعات اتصال را بگیرید', 'body' => 'بعد از آماده شدن ماشین مجازی، IP و مشخصات اتصال در پنل نمایش داده می شود.'],
    ];

    $operations = [
        ['title' => 'دیسک NVMe', 'body' => 'برای بارگذاری سریع تر سایت، فروشگاه، دیتابیس و ابزارهای کاری.'],
        ['title' => 'IP اختصاصی', 'body' => 'برای دامنه، SSL، اتصال مستقیم و جدا نگه داشتن سرویس ها.'],
        ['title' => 'بکاپ و فایروال', 'body' => 'برای کاهش ریسک و کنترل دسترسی های مهم ماشین مجازی.'],
        ['title' => 'پشتیبانی فارسی', 'body' => 'برای انتخاب پلن، شروع کار و پاسخ به سوال های فنی رایج.'],
    ];

    $faqs = [
        ['q' => 'بعد از خرید، ماشین مجازی چه زمانی آماده می شود؟', 'a' => 'بعد از ثبت سفارش و پرداخت، ساخت ماشین مجازی به صورت خودکار شروع می شود. وقتی سرور آماده شد، IP و اطلاعات اتصال را در پنل مشتری می بینید.'],
        ['q' => 'برای سایت یا اپلیکیشنم کدام پلن بهتر است؟', 'a' => 'اگر تازه شروع کرده اید، معمولا یک پلن کوچک تر کافی است. برای فروشگاه، سایت پربازدید یا اپلیکیشنی که کاربر زیادی دارد، بهتر است پلنی انتخاب کنید که کمی فضای رشد هم داشته باشد.'],
        ['q' => 'آیا دسترسی کامل به سرور دارم؟', 'a' => 'بله. سرور با دسترسی مدیریتی تحویل داده می شود و می توانید وب سرور، دیتابیس، Docker و ابزارهای مورد نیاز خودتان را نصب کنید.'],
        ['q' => 'هزینه ها چطور پرداخت می شوند؟', 'a' => 'قیمت هر پلن قبل از سفارش مشخص است و پرداخت از کیف پول مشتری انجام می شود. قبل از خرید، منابع و هزینه ماهانه را می بینید.'],
        ['q' => 'اگر بعدا منابع بیشتری لازم داشته باشم چه کنم؟', 'a' => 'لازم نیست از اول بزرگ ترین پلن را بخرید. وقتی پروژه بزرگ تر شد، می توانید پلن مناسب تری انتخاب کنید یا برای انتخاب مسیر بهتر از پشتیبانی کمک بگیرید.'],
        ['q' => 'امنیت و نگهداری سرور با چه کسی است؟', 'a' => 'ما امکانات زیرساختی مثل IP اختصاصی، فایروال و بکاپ را فراهم می کنیم. نصب نرم افزارها، به روزرسانی سیستم عامل و نگهداری اپلیکیشن داخل سرور بر عهده شماست، مگر اینکه سرویس مدیریتی جداگانه داشته باشید.'],
    ];
@endphp

@section('body_class', 'figma-marketing landing-page bg-white')

@section('content')
    <section id="top" class="relative isolate flex min-h-[496px] items-center overflow-hidden px-4 pb-12 pt-28 md:px-8 md:pt-44 lg:px-10">
        <div aria-hidden="true" class="absolute inset-0 -z-20 bg-[#EDF4FE]"></div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-cover bg-center" style="background-image: linear-gradient(90deg, rgba(237,244,254,.18), rgba(237,244,254,.76) 72%), url('{{ asset('assets/images/figma-landing-cloud-hero.png') }}');"></div>

        <div class="mx-auto w-full max-w-7xl">
            <div class="mx-auto max-w-[872px] text-center md:mx-0 md:text-right">
                <h1 class="text-3xl font-bold leading-[1.5] text-[#000C1C] md:text-[32px]">
                    زیرساختی پایدار، برای <span class="text-[#0069FF]">اوجی</span> بی‌پایان
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-[#000C1C] md:mx-0 md:text-2xl md:leading-[44px]">
                  از اولین راه‌اندازی تا اوج رشد، سرورهایی پایدار برای سرویس‌های همیشه‌روشن.
                </p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row md:justify-start">
                    <a href="#plans" class="inline-flex h-12 items-center justify-center rounded-lg bg-[#0069FF] px-8 text-sm font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        مشاهده پلن
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex h-12 items-center justify-center rounded-lg border border-[#0069FF] bg-white/70 px-8 text-sm font-bold text-[#0069FF] backdrop-blur transition hover:bg-white">
                        مشاوره رایگان
                    </a>
                </div>
            </div>
        </div>
    </section>
    

    <section class="bg-white px-4 py-5 md:px-8 md:py-[18px] lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-start">
                <div class="text-center lg:text-right">
                    <h2 class="text-2xl font-bold leading-9 text-[#000C1C]">
                        همراهان آویاتو
                    </h2>
                    <p class="mt-1 leading-8 text-[#000C1C]">
                        آویاتو میزبان پروژه هایی است که برای فروش، پشتیبانی، توسعه و کار روزانه به ماشین مجازی پایدار نیاز دارند.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($currentCustomers as $customer)
                        <a href="{{ $customer['url'] }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-[167px] flex-col items-center justify-center rounded-2xl border border-[#E0E0E0] bg-white p-6 text-center transition hover:border-[#B9D6FF] hover:shadow-lg hover:shadow-slate-200/50">
                            <span class="flex h-16 w-full items-center justify-center">
                                <img src="{{ asset($customer['logo']) }}" alt="{{ $customer['name'] }}" class="max-h-14 max-w-44 object-contain">
                            </span>
                            <span class="mt-5 text-lg font-bold text-slate-950">{{ $customer['name'] }}</span>
                            <span class="mt-2 text-sm font-bold text-slate-500" dir="ltr">{{ parse_url($customer['url'], PHP_URL_HOST) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="plans" class="bg-[#F5F8FD] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold text-[#2C67C9]">پلن های ماشین مجازی</p>
                    <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">با یک پلن روشن شروع کنید.</h2>
                    <p class="mt-4 leading-8 text-slate-600">منابع و هزینه ماهانه قبل از خرید کاملا مشخص است؛ انتخاب پلن نباید پیچیده باشد.</p>
                </div>
                <a href="{{ route('pricing') }}" class="inline-flex w-fit rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition-[transform,background-color,border-color,color,box-shadow] hover:border-[#B9D6FF] hover:bg-[#F7FBFF] hover:text-[#2C67C9] active:scale-[0.96]">همه پلن ها</a>
            </div>

            @forelse ($marketingBundles as $bundle)
                @php
                    $meta = $planMeta[$loop->index] ?? $planMeta[3];
                    $isRecommended = $loop->index === $recommendedIndex;
                @endphp

                {{-- Mobile: card-style stacked layout --}}
                <div class="mt-6 rounded-[1.75rem] border bg-white p-5 shadow-sm md:hidden {{ $isRecommended ? 'border-[#B9D6FF] bg-[#F7FBFF]' : 'border-slate-200' }}">
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-bold text-[#2C67C9]">{{ $meta['label'] }}</p>
                        @if ($isRecommended)
                            <span class="rounded-full bg-[#EEF5FF] px-3 py-1 text-xs font-bold text-[#2C67C9]">پیشنهادی</span>
                        @endif
                    </div>
                    <h3 class="mt-2 text-2xl text-slate-950">{{ $bundle->name }}</h3>
                    <p class="mt-1 min-h-6 text-sm font-bold text-slate-500">{{ $meta['use'] }}</p>

                    <div class="mt-5 border-y border-slate-100 py-4">
                        <p class="text-3xl text-slate-950">{{ $wallets->format($bundle->monthly_price) }}<span class="text-sm font-bold text-slate-400">/ماهانه</span></p>
                        <p class="mt-1 text-lg text-slate-950">{{ $wallets->format(round($bundle->monthly_price / 720)) }}<span class="text-sm font-bold text-slate-400">/ساعتی</span></p>
                    </div>

                    <p class="mt-4 min-h-10 text-sm leading-7 text-slate-600">{{ $bundle->description ?: 'ماشین مجازی آماده برای سایت، فروشگاه، اپلیکیشن و سرویس های آنلاین.' }}</p>

                    <div class="mt-5 grid grid-cols-3 gap-2" dir="ltr">
                        <div class="flex min-w-0 flex-col items-center rounded-2xl bg-white px-2 py-4 text-center ring-1 ring-slate-100">
                            <span class="grid size-12 place-items-center rounded-xl bg-[#EEF5FF]">
                                <img src="{{ asset('assets/icons/cpu-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                            </span>
                            <strong class="mt-3 text-base font-bold text-slate-950">{{ $bundle->cpu_cores }}</strong>
                            <span class="mt-0.5 text-[11px] font-bold text-slate-500">vCPU</span>
                        </div>
                        <div class="flex min-w-0 flex-col items-center rounded-2xl bg-white px-2 py-4 text-center ring-1 ring-slate-100">
                            <span class="grid size-12 place-items-center rounded-xl bg-[#EEF5FF]">
                                <img src="{{ asset('assets/icons/ram-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                            </span>
                            <strong class="mt-3 text-base font-bold text-slate-950">{{ $bundle->ram_gb }} GB</strong>
                            <span class="mt-0.5 text-[11px] font-bold text-slate-500">RAM</span>
                        </div>
                        <div class="flex min-w-0 flex-col items-center rounded-2xl bg-white px-2 py-4 text-center ring-1 ring-slate-100">
                            <span class="grid size-12 place-items-center rounded-xl bg-[#EEF5FF]">
                                <img src="{{ asset('assets/icons/ssd-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                            </span>
                            <strong class="mt-3 text-base font-bold text-slate-950">{{ $bundle->disk_gb }} GB</strong>
                            <span class="mt-0.5 text-[11px] font-bold text-slate-500">NVMe</span>
                        </div>
                    </div>
                    <a href="{{ route('customer.register') }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white transition-[transform,background-color,border-color,color,box-shadow] hover:bg-[#0050D0] active:scale-[0.96]">خرید این پلن</a>
                </div>
            @empty
                <div class="mt-10 rounded-[1.75rem] border border-dashed border-slate-200 bg-[#F7FBFF] p-8 text-center">
                    <h3 class="text-xl text-slate-950">فعلا پلنی برای نمایش وجود ندارد.</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">بعد از فعال شدن پلن ها در پنل مدیریت، قیمت ها به صورت خودکار در صفحه خانه و صفحه قیمت ها نمایش داده می شوند.</p>
                </div>
            @endforelse

            {{-- Desktop: table layout --}}
            @if ($marketingBundles->isNotEmpty())
                <div class="mt-10 hidden md:block">
                    <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-lg shadow-slate-200/60">
                        <table class="w-full border-separate border-spacing-y-1 text-sm" dir="rtl">
                            <thead class="text-right text-xs font-bold text-slate-400">
                                <tr>
                                    <th scope="col" class="px-6 pb-2 pt-1">پلن</th>
                                    <th scope="col" class="px-5 pb-2 pt-1 text-center">CPU</th>
                                    <th scope="col" class="px-5 pb-2 pt-1 text-center">RAM</th>
                                    <th scope="col" class="px-5 pb-2 pt-1 text-center">NVMe</th>
                                    <th scope="col" class="px-6 pb-2 pt-1 text-center">ماهانه</th>
                                    <th scope="col" class="px-6 pb-2 pt-1 text-center">ساعتی</th>
                                    <th scope="col" class="px-6 pb-2 pt-1 text-center">شروع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($marketingBundles as $bundle)
                                    @php
                                        $meta = $planMeta[$loop->index] ?? $planMeta[3];
                                        $isRecommended = $loop->index === $recommendedIndex;
                                    @endphp
                                    <tr class="transition {{ $isRecommended ? 'bg-slate-50' : 'hover:bg-slate-50/60' }}">
                                        <td class="px-6 py-6">
                                            <div class="flex items-center gap-3">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <p class="font-bold text-slate-950">{{ $bundle->name }}</p>
                                                        @if ($isRecommended)
                                                            <span class="shrink-0 rounded-full bg-[#EEF5FF] px-3 py-1 text-xs font-bold text-[#2C67C9]">پیشنهادی</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-0.5 text-xs text-slate-500">{{ $meta['use'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-5" dir="ltr">
                                            <div class="flex items-center justify-center gap-3">
                                                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-[#EEF5FF] ring-1 ring-[#DCEAFF]">
                                                    <img src="{{ asset('assets/icons/cpu-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                                                </span>
                                                <span class="text-left"><strong class="block text-lg font-bold text-slate-950">{{ $bundle->cpu_cores }}</strong><span class="text-[11px] font-bold text-slate-500">vCPU</span></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-5" dir="ltr">
                                            <div class="flex items-center justify-center gap-3">
                                                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-[#EEF5FF] ring-1 ring-[#DCEAFF]">
                                                    <img src="{{ asset('assets/icons/ram-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                                                </span>
                                                <span class="text-left"><strong class="block text-lg font-bold text-slate-950">{{ $bundle->ram_gb }} GB</strong><span class="text-[11px] font-bold text-slate-500">RAM</span></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-5" dir="ltr">
                                            <div class="flex items-center justify-center gap-3">
                                                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-[#EEF5FF] ring-1 ring-[#DCEAFF]">
                                                    <img src="{{ asset('assets/icons/ssd-icon.svg') }}" alt="" class="size-7" aria-hidden="true">
                                                </span>
                                                <span class="text-left"><strong class="block text-lg font-bold text-slate-950">{{ $bundle->disk_gb }} GB</strong><span class="text-[11px] font-bold text-slate-500">NVMe</span></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <p class="text-lg font-bold text-slate-950">{{ $wallets->format($bundle->monthly_price) }}<span class="text-xs font-bold text-slate-400">/ماهانه</span></p>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <p class="text-lg font-bold text-slate-950">{{ $wallets->format(round($bundle->monthly_price / 720)) }}<span class="text-xs font-bold text-slate-400">/ساعتی</span></p>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-xs font-bold text-white transition-[transform,background-color,border-color,color,box-shadow] hover:bg-[#0050D0] active:scale-[0.96]">خرید پلن</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-slate-500">
                                           فعلا پلنی برای نمایش وجود ندارد.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-[894px] text-right">
            <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">چرا آویاتو؟</h2>
            <div class="mt-4 grid gap-6">
                @foreach ($differenceRows as $row)
                    <article>
                        <div class="flex items-center justify-start gap-1 text-[#002F71]">
                            <img src="{{ asset('assets/icons/figma/tick-circle.svg') }}" alt="" class="size-6" aria-hidden="true">
                            <h3 class="text-xl font-bold leading-[30px]">{{ $row['title'] }}</h3>
                        </div>
                        <p class="mt-1 text-xl leading-9 text-[#002355]">{{ $row['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-[1320px] gap-10 lg:grid-cols-[422px_1fr] lg:items-start">
            <div class="text-right">
                <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">کاربردهای رایج</h2>
                <p class="mt-1 text-[22px] leading-10 text-[#000C1C]">از یک سایت ساده تا سرویس‌های فنی‌تر، می‌توانید با منابع مشخص شروع کنید و بعد متناسب با رشد پروژه تصمیم بگیرید.</p>
                <a href="{{ route('solutions') }}" class="mt-5 inline-flex h-12 items-center justify-center rounded-lg border border-[#0069FF] px-8 text-sm text-[#0069FF] transition hover:bg-[#EEF5FF]">راهکارهای ما</a>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($useCases as $case)
                    <article class="flex min-h-[170px] flex-col items-end justify-center rounded-2xl border border-[#C6DEFF] bg-[#FAFCFF] px-6 py-3 text-right {{ $loop->last ? 'border-[#003A8E] bg-white' : '' }}">
                        <img src="{{ asset('assets/icons/figma/'.$case['icon']) }}" alt="" class="size-[46px]" aria-hidden="true">
                        <h3 class="mt-1 text-2xl font-bold leading-9 text-[#001739]">{{ $case['title'] }}</h3>
                        <p class="mt-1 text-lg leading-8 text-[#002355]">منابع، قیمت و تعداد IP را می‌بینید و پلن مناسب پروژه را انتخاب می‌کنید.</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-[1320px] rounded-[28px] border border-[#AACDFF] bg-[#002F71] px-6 py-8 text-right text-[#FAFCFF] md:px-16 md:py-6">
            <h2 class="text-[28px] font-bold leading-[42px]">مسیر شروع شما از اینجاست:</h2>
            <div class="mt-5 grid gap-4">
                @foreach ($steps as $step)
                    <p class="text-lg leading-8 md:text-[22px] md:leading-10"><strong class="font-bold">{{ $step['title'] }}:</strong> {{ $step['body'] }}</p>
                @endforeach
            </div>
            <div class="mt-6 flex flex-col gap-4 border-t border-[#AACDFF]/70 pt-5 md:flex-row md:items-center md:justify-between">
                <p class="text-xl font-bold leading-9">اگر آماده خرید هستید، ثبت نام کنید. اگر هنوز در حال مقایسه هستید، پلن‌ها و قیمت‌ها را کامل ببینید.</p>
                <a href="{{ route('contact') }}" class="inline-flex h-14 shrink-0 items-center justify-center rounded-lg bg-[#E3EEFF] px-10 text-sm text-[#0069FF] transition hover:bg-white">مشاوره</a>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-[1320px] text-right">
            <div class="max-w-[1120px] mr-auto">
                <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">قبل از پرداخت همه چیز باید قابل فهم باشد</h2>
                <p class="mt-1 text-[22px] leading-10 text-[#000C1C]">از یک سایت ساده تا سرویس‌های فنی‌تر، می‌توانید با منابع مشخص شروع کنید و بعد متناسب با رشد پروژه تصمیم بگیرید.</p>
            </div>
            <div class="mt-8 grid gap-7 md:grid-cols-4">
                @foreach ($operations as $item)
                    <article class="text-center">
                        <h3 class="border-b border-[#003A8E] pb-2 text-2xl font-bold leading-9 text-[#001739]">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-lg leading-8 text-[#001739]">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-5xl">
            <div class="text-center">
                <p class="text-sm font-bold text-[#2C67C9]">سوالات قبل از خرید</p>
                <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">پاسخ چند سوال رایج.</h2>
            </div>

            <div class="mt-10 divide-y divide-slate-100 rounded-[1.75rem] border border-slate-200 bg-white">
                @foreach ($faqs as $faq)
                    <details class="group p-5 open:bg-white md:p-6" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-right text-lg text-slate-950">
                            {{ $faq['q'] }}
                            <span class="grid size-8 shrink-0 place-items-center rounded-2xl bg-[#EEF5FF] text-[#2C67C9] transition group-open:rotate-45">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                            </span>
                        </summary>
                        <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    @if (! empty($latestPosts))
    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold text-[#2C67C9]"> بلاگ آویاتو</p>
                    <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">مقاله‌ها و راهنماها.</h2>
                    <p class="mt-4 leading-8 text-slate-600">مقالاتی درباره زیرساخت ابری، انتخاب سرور مجازی و مدیریت سرویس‌های آنلاین.</p>
                </div>
                <a href="{{ route('blog') }}" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition-[transform,background-color,border-color,color,box-shadow] hover:border-[#B9D6FF] hover:bg-[#F7FBFF] hover:text-[#2C67C9] active:scale-[0.96]">
                    همه مقاله‌ها
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach ($latestPosts as $post)
                    <a href="{{ route('blog.show', $post['slug']) }}" class="group flex flex-col rounded-[1.75rem] border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:border-[#B9D6FF] hover:shadow-xl hover:shadow-slate-200/60">
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="rounded-full bg-[#EEF5FF] px-3 py-1 font-bold text-[#2C67C9]">{{ $post['category'] }}</span>
                            <span>{{ $post['date_display'] }}</span>
                        </div>

                        <h3 class="mt-4 text-lg font-black leading-tight text-slate-950 transition group-hover:text-[#2C67C9]">
                            {{ $post['title'] }}
                        </h3>

                        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600">
                            {{ $post['excerpt'] }}
                        </p>

                        <div class="mt-5 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-400">{{ $post['reading_time'] }} مطالعه</span>
                            <span class="inline-flex items-center gap-1 text-sm font-bold text-[#2C67C9] transition group-hover:gap-2">
                                مطالعه
                                <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="bg-white px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-[#EEF5FF] p-7 text-slate-950 shadow-[0_24px_90px_rgba(148,163,184,0.18)] md:p-12">
            <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="max-w-3xl text-3xl leading-tight md:text-4xl">برای شروع، یک پلن کافی است.</h2>
                    <p class="mt-4 max-w-2xl leading-8 text-slate-600">اگر آماده خرید هستید، ثبت نام کنید. اگر هنوز در حال مقایسه هستید، پلن ها و قیمت ها را کامل ببینید.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-xl bg-[#4C86E8] px-7 py-4 text-base font-bold text-white transition-[transform,background-color,border-color,color,box-shadow] hover:bg-[#3E76D6] active:scale-[0.96]">خرید ماشین مجازی</a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-7 py-4 text-base font-bold text-slate-700 transition-[transform,background-color,border-color,color,box-shadow] hover:border-[#B9D6FF] hover:bg-[#F7FBFF] hover:text-[#2C67C9] active:scale-[0.96]">دیدن پلن ها و قیمت ها</a>
                </div>
            </div>
        </div>
    </section>
@endsection
