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
        ['name' => 'حسابرو', 'url' => 'https://hesabro.ir', 'logo' => 'assets/images/customers/hesabro-logo.png'],
        ['name' => 'کارخانه نوآوری کرمان', 'url' => 'https://kermanif.ir', 'logo' => 'assets/images/customers/kermanif-logo.png'],
        ['name' => 'مبیت', 'url' => 'https://mobit.ir', 'logo' => 'assets/images/customers/mobit-logo.svg'],
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
        ['number' => '01', 'title' => 'پلن را انتخاب کنید', 'body' => 'منابع، قیمت و تعداد IP را می بینید و پلن مناسب پروژه را انتخاب می کنید.', 'icon' => 'flash-circle.svg'],
        ['number' => '02', 'title' => 'حساب را شارژ کنید', 'body' => 'در پنل مشتری ثبت نام می کنید، کیف پول را شارژ می کنید و سفارش را ثبت می کنید.', 'icon' => 'wallet-add.svg'],
        ['number' => '03', 'title' => 'اطلاعات اتصال را بگیرید', 'body' => 'بعد از آماده شدن ماشین مجازی، IP و مشخصات اتصال در پنل نمایش داده می شود.', 'icon' => 'edit.svg'],
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
    <section id="top" class="landing-hero relative isolate flex min-h-[496px] items-end overflow-hidden px-4 pb-16 pt-28 md:px-8 md:pb-[106px] md:pt-0 lg:px-10">
        <div aria-hidden="true" class="absolute inset-0 -z-20 bg-[#EDF4FE]"></div>
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-cover bg-center" style="background-image: linear-gradient(to bottom, rgba(255,255,255,0) 58%, rgba(255,255,255,.78) 84%, #fff 100%), linear-gradient(90deg, rgba(237,244,254,.18), rgba(237,244,254,.76) 72%), url('{{ asset('assets/images/figma-landing-cloud-hero.png') }}');"></div>

        <div class="mx-auto w-full max-w-[1320px]">
            <div class="mx-auto max-w-[872px] text-center md:ml-auto md:mr-0 md:text-right">
                <h1 class="text-4xl font-bold leading-[1.35] text-[#000C1C] md:text-[44px]">
                    زیرساختی پایدار، برای <span class="text-[#0069FF]">اوجی</span> بی‌پایان
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg leading-8 text-[#000C1C] md:mx-0 md:mt-6 md:max-w-none md:text-[24px] md:leading-[44px]">
                  از اولین راه‌اندازی تا اوج رشد، سرورهایی پایدار برای سرویس‌های همیشه‌روشن.
                </p>
                <div class="mt-4 flex flex-col justify-center gap-3 sm:flex-row md:mt-6 md:justify-start md:gap-4">
                    <a href="#plans" class="inline-flex h-12 items-center justify-center rounded-lg bg-[#0069FF] px-8 text-base font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        مشاهده پلن‌ها
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex h-12 items-center justify-center rounded-lg border border-[#0069FF] bg-white/70 px-8 text-base font-bold text-[#0069FF] backdrop-blur transition hover:bg-white">
                        مشاوره رایگان
                    </a>
                </div>
            </div>
        </div>
    </section>
    

    <section class="landing-customers bg-white px-4 py-8 md:px-8 md:py-0 lg:mt-[18px] lg:px-10">
        <div class="mx-auto max-w-[1320px]">
            <div class="grid gap-6 lg:grid-cols-[424px_852px] lg:items-start lg:gap-11">
                <div class="text-center lg:text-right">
                    <h2 class="text-2xl font-bold leading-9 text-[#000C1C]">
                        همراهان آویاتو
                    </h2>
                    <p class="mt-1 text-lg leading-8 text-[#000C1C]">
                        آویاتو میزبان پروژه هایی است که برای فروش، پشتیبانی، توسعه و کار روزانه به ماشین مجازی پایدار نیاز دارند.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:gap-6">
                    @foreach ($currentCustomers as $customer)
                        <a href="{{ $customer['url'] }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-[167px] flex-col items-center justify-center rounded-2xl border border-[#E0E0E0] bg-white p-6 text-center transition hover:border-[#B9D6FF] hover:shadow-lg hover:shadow-slate-200/50">
                            <span class="flex h-16 w-full items-center justify-center">
                                <img src="{{ asset($customer['logo']) }}" alt="{{ $customer['name'] }}" class="max-h-14 max-w-44 object-contain">
                            </span>
                            <span class="mt-3 text-xl text-slate-950">{{ $customer['name'] }}</span>
                            <span class="text-base text-slate-500" dir="ltr">{{ parse_url($customer['url'], PHP_URL_HOST) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="plans" class="landing-plans bg-white px-4 py-14 md:px-8 md:py-20 lg:mt-[150px] lg:px-10">
        <div class="mx-auto grid max-w-[1320px] gap-8 lg:grid-cols-[449px_minmax(0,861px)] lg:items-start lg:gap-[10px]">
            <div class="text-right lg:pt-10">
                <a href="{{ route('pricing') }}" class="inline-flex flex-row-reverse items-center gap-2 text-[30px] font-bold leading-[44px] text-[#0046AA] md:text-[32px]">
                    <span aria-hidden="true" class="text-2xl">←</span><span>با یک پلن شروع کنید</span>
                </a>
                <p class="mt-4 text-lg leading-8 text-[#000C1C] md:text-xl">منابع، قیمت و مدت استفاده را شفاف می‌بینید تا با خیال راحت، پلنی متناسب با نیاز پروژه‌تان انتخاب کنید.</p>
                <a href="{{ route('pricing') }}" class="mt-5 inline-flex h-11 items-center justify-center rounded-lg border border-[#0069FF] px-6 text-base text-[#0069FF] transition hover:bg-[#EEF5FF]">مشاهده همه پلن‌ها</a>
            </div>

            @if ($marketingBundles->isNotEmpty())
                <div class="grid gap-0">
                    @foreach ($marketingBundles->take(3) as $bundle)
                        @php($isRecommended = $loop->index === $recommendedIndex)
                        <a href="{{ route('pricing') }}" class="grid min-h-[126px] grid-cols-3 items-center gap-3 rounded-lg border border-[#C6DEFF] bg-[#FAFCFF] px-4 py-5 text-right transition hover:border-[#0046AA] {{ $isRecommended ? 'border-2 border-[#0046AA] bg-[#EFEFEF]' : '' }} sm:grid-cols-[1fr_1fr_1fr_auto] sm:gap-5 sm:px-6" dir="rtl">
                            <span class="col-span-3 min-w-0 sm:col-span-1"><strong class="block text-xl font-bold text-[#000C1C] md:text-2xl">{{ $bundle->name }}</strong><span class="mt-1 block text-sm text-[#5A5A5A] md:text-base">{{ $wallets->format($bundle->monthly_price) }} / ماهانه</span></span>
                            <span class="text-center text-base text-[#5A5A5A] md:text-lg"><img src="{{ asset('assets/icons/figma/cpu.svg') }}" alt="" class="mx-auto mb-1 size-6" aria-hidden="true">{{ $bundle->cpu_cores }} vCPU</span>
                            <span class="text-center text-base text-[#5A5A5A] md:text-lg"><img src="{{ asset('assets/icons/figma/ram.svg') }}" alt="" class="mx-auto mb-1 size-6" aria-hidden="true">{{ $bundle->ram_gb }} GB RAM</span>
                            <span class="text-center text-base text-[#5A5A5A] md:text-lg"><img src="{{ asset('assets/icons/figma/disk.svg') }}" alt="" class="mx-auto mb-1 size-6" aria-hidden="true">{{ $bundle->disk_gb }} GB</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-[#C6DEFF] bg-[#FAFCFF] p-8 text-center text-[#5A5A5A]">فعلا پلنی برای نمایش وجود ندارد.</div>
            @endif
        </div>
    </section>

    <section class="landing-usage mt-14 bg-white px-4 lg:mt-[150px] md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-[1320px] gap-8 lg:grid-cols-[422px_minmax(0,872px)] lg:items-start lg:gap-[26px]">
            <div class="text-right lg:pt-1">
                <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">کاربردهای رایج</h2>
                <p class="mt-1 text-[20px] leading-9 text-[#000C1C]">برای راه‌اندازی انواع سرویس‌ها، منابع آماده و پایدار آویاتو را در اختیار دارید.</p>
                <a href="{{ route('solutions') }}" class="mt-4 inline-flex h-11 items-center justify-center rounded-lg border border-[#0069FF] px-6 text-base text-[#0069FF] transition hover:bg-[#EEF5FF]">راهکارهای ما</a>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($useCases as $case)
                    <article dir="rtl" class="flex min-h-[162px] flex-col items-start justify-center rounded-lg border border-[#C6DEFF] bg-[#FAFCFF] px-6 py-3 text-right {{ $loop->last ? 'border-[#003A8E] bg-white' : '' }}">
                        <img src="{{ asset('assets/icons/figma/'.$case['icon']) }}" alt="" class="size-[46px]" aria-hidden="true">
                        <h3 class="mt-1 text-xl font-bold leading-8 text-[#001739]">{{ $case['title'] }}</h3>
                        <p class="mt-1 text-base leading-7 text-[#002355]">مناسب برای شروع و توسعه سرویس‌های آنلاین با منابع پایدار.</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="landing-why mt-14 bg-white px-4 lg:mt-[84px] md:px-8 lg:px-10">
        <div class="mx-auto max-w-[894px] text-right lg:mr-[calc((100%-1320px)/2)]">
            <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">چرا آویاتو؟</h2>
            <div class="mt-2 grid gap-2">
                @foreach ($differenceRows as $row)
                    <article class="min-h-[70px]">
                        <div class="flex items-center justify-start gap-1 text-[#002F71]">
                            <img src="{{ asset('assets/icons/figma/tick-circle.svg') }}" alt="" class="size-6" aria-hidden="true">
                            <h3 class="text-xl font-bold leading-8">{{ $row['title'] }}</h3>
                        </div>
                        <p class="mt-0.5 text-lg leading-8 text-[#002355]">{{ $row['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="landing-before-payment mt-14 bg-white px-4 lg:mt-[150px] md:px-8 lg:px-10">
        <div class="mx-auto max-w-[1320px] text-right">
            <h2 class="text-[26px] font-bold leading-10 text-[#001739] md:text-[28px]">قبل از پرداخت، همه چیز باید شفاف باشد</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
                @foreach ($operations as $item)
                    <article class="px-3 text-center">
                        <h3 class="border-b border-[#003A8E] pb-2 text-xl font-bold leading-8 text-[#001739]">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-base leading-7 text-[#001739]">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="landing-start mt-14 bg-white px-4 lg:mt-[150px] md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[376px] max-w-[1320px] flex-col justify-between gap-6 rounded-[28px] bg-[#003A8E] p-5 text-right text-[#FAFCFF] shadow-lg shadow-[#003A8E]/10 md:p-7">
            <div>
                <h2 class="text-2xl font-bold leading-9">مسیر شروع شما از اینجاست:</h2>
                <div class="mt-3 grid gap-2">
                    @foreach ($steps as $step)
                    <div dir="rtl" class="flex items-center gap-2 text-right text-base leading-7 md:text-lg">
                        <img src="{{ asset('assets/icons/figma/'.$step['icon']) }}" alt="" aria-hidden="true" class="shrink-0">
                        <p class="min-w-0 flex-1"><strong class="font-bold">{{ $step['title'] }}:</strong> {{ $step['body'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-col gap-4 border-t border-[#AACDFF]/70 pt-4 md:flex-row md:items-center md:justify-between">
                <p class="text-base font-bold leading-7 md:text-lg">برای انتخاب پلن مناسب یا شروع سفارش، تیم آویاتو همراه شماست.</p>
                <a href="{{ route('contact') }}" class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#E3EEFF] px-8 text-base text-[#0069FF] transition hover:bg-white">مشاوره رایگان</a>
            </div>
        </div>
    </section>

    @if (! empty($latestPosts))
    <section class="landing-blog mt-14 bg-white px-4 lg:mt-[150px] md:px-8 lg:px-10">
        <div class="mx-auto max-w-[1320px]">
            <div class="flex items-end justify-between gap-5">
                <div class="text-right">
                    <h2 class="text-[28px] font-bold leading-10 text-[#001739]">بلاگ آویاتو</h2>
                    <p class="mt-1 text-lg leading-8 text-[#000C1C]">آخرین مقاله‌ها و راهنماها برای راه‌اندازی سرویس‌های آنلاین.</p>
                </div>
                <a href="{{ route('blog') }}" class="shrink-0 text-base font-bold text-[#0069FF]">همه مقاله‌ها ←</a>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                @foreach ($latestPosts as $post)
                    <a href="{{ route('blog.show', $post['slug']) }}" class="group flex min-h-[270px] flex-col rounded-lg border border-[#C6DEFF] bg-white p-5 text-right transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="flex items-center justify-between gap-3 text-sm text-[#0069FF]">
                            <span>{{ $post['date_display'] }}</span>
                            <span>{{ $post['category'] }}</span>
                        </div>

                        <h3 class="mt-5 text-lg font-bold leading-8 text-[#001739] transition group-hover:text-[#2C67C9] md:text-xl">
                            {{ $post['title'] }}
                        </h3>

                        <p class="mt-3 flex-1 text-base leading-7 text-[#002355]">
                            {{ $post['excerpt'] }}
                        </p>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-sm text-slate-500">
                            <span>{{ $post['reading_time'] }} مطالعه</span>
                            <span class="text-[#0069FF]">ادامه مطلب ←</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

@endsection
