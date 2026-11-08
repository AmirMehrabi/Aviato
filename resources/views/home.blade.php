@extends('layouts.marketing')

@section('title', 'آویاتو | از زمین تا ابر، با اطمینان')
@section('description', 'سرور ابری، ثبت دامنه، مدیریت DNS و میزبانی تجهیزات؛ زیرساخت مورد نیاز پروژه شما در آویاتو.')

@php
    $activePage = 'home';

    $marketingBundles = ($bundles ?? collect())->values();
    $recommendedIndex = $marketingBundles->count() > 1 ? 1 : null;

    $currentCustomers = collect([
        ['name' => 'حسابرو', 'url' => 'https://hesabro.com', 'logo' => 'assets/images/customers/hesabro-logo.png', 'width' => 139, 'height' => 34],
        ['name' => 'کارخانه نوآوری کرمان', 'url' => 'https://kermanif.ir', 'logo' => 'assets/images/customers/kermanif-logo.png', 'width' => 82, 'height' => 82],
        ['name' => 'مبیت', 'url' => 'https://mobit.ir', 'logo' => 'assets/images/customers/mobit-logo.svg', 'width' => 918, 'height' => 256],
        ['name' => 'آوینوکس', 'url' => 'https://avinox.ir/', 'logo' => 'assets/images/customers/avinox-logo.svg', 'width' => 1255, 'height' => 159],
        ['name' => 'پی‌بای', 'url' => 'https://paybuy.ir/', 'logo' => 'assets/images/customers/paybuy-logo.svg', 'width' => 32, 'height' => 32],
    ])->random(3)->sortKeys()->values();

    $differenceRows = [
        ['title' => 'خرید بدون ابهام', 'body' => 'قبل از پرداخت می دانید چه منابعی می گیرید، هزینه چقدر است و سفارش از کجا پیگیری می شود.'],
        ['title' => 'شروع قابل پیش بینی', 'body' => 'پس از ثبت سفارش، ساخت ماشین مجازی شروع می شود و اطلاعات اتصال داخل پنل مشتری قرار می گیرد.'],
        ['title' => 'پشتیبانی قابل فهم', 'body' => 'برای انتخاب پلن، شروع کار و رفع سوال های رایج، با تیم فنی در ارتباط هستید.'],
    ];

@endphp

@section('body_class', 'figma-marketing landing-page bg-white')

@push('preloads')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" as="image" type="image/webp" href="{{ Vite::asset('resources/images/landing-cloud-hero-mobile.webp') }}" media="(max-width: 767px)" fetchpriority="high">
    <link rel="preload" as="image" type="image/webp" href="{{ Vite::asset('resources/images/landing-cloud-hero.webp') }}" media="(min-width: 768px)" fetchpriority="high">
@endpush

@section('content')
    <section id="top" class="landing-hero relative isolate flex min-h-[496px] items-end overflow-hidden px-4 pb-16 pt-28 md:px-8 md:pb-[106px] md:pt-0 lg:px-10">
        <div aria-hidden="true" class="absolute inset-0 -z-20 bg-[#EDF4FE]"></div>
        <picture aria-hidden="true" class="absolute inset-0 -z-10">
            <source media="(max-width: 767px)" srcset="{{ Vite::asset('resources/images/landing-cloud-hero-mobile.webp') }}" type="image/webp">
            <img src="{{ Vite::asset('resources/images/landing-cloud-hero.webp') }}" alt="" width="1916" height="821" fetchpriority="high" class="h-full w-full object-cover object-center">
        </picture>
        <div aria-hidden="true" class="absolute inset-0 -z-10" style="background-image: linear-gradient(to bottom, rgba(255,255,255,0) 58%, rgba(255,255,255,.78) 84%, #fff 100%), linear-gradient(90deg, rgba(237,244,254,.18), rgba(237,244,254,.76) 72%);"></div>

        <div class="mx-auto w-full max-w-[1320px]">
            <div class="mx-auto max-w-[872px] text-center md:ml-auto md:mr-0 md:text-right">
                <h1 class="text-4xl font-bold leading-[1.35] text-[#000C1C] md:text-[44px]">
                    زیرساختی پایدار، برای <span class="text-[#0069FF]">اوجی</span> بی‌پایان
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg leading-8 text-[#000C1C] md:mx-0 md:mt-6 md:max-w-none md:text-[24px] md:leading-[44px]">
                  از اولین راه‌اندازی تا اوج رشد، زیرساختی پایدار برای سرویس‌های همیشه‌روشن.
                </p>
                <div class="mt-4 flex flex-col justify-center gap-3 sm:flex-row md:mt-6 md:justify-start md:gap-4">
                    <a href="#services" class="inline-flex h-12 items-center justify-center rounded-lg bg-[#0069FF] px-8 text-base font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        انتخاب سرویس
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
            <div class="grid gap-6 lg:grid-cols-[1fr_2fr] lg:items-start lg:gap-11">
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
                                <img src="{{ asset($customer['logo']) }}" alt="{{ $customer['name'] }}" width="{{ $customer['width'] }}" height="{{ $customer['height'] }}" decoding="async" class="max-h-14 max-w-44 object-contain">
                            </span>
                            <span class="mt-3 text-xl text-slate-950">{{ $customer['name'] }}</span>
                            <span class="text-base text-slate-500" dir="ltr">{{ parse_url($customer['url'], PHP_URL_HOST) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="scroll-mt-28 bg-white px-4 py-14 md:px-8 lg:px-10">
        <div class="mx-auto max-w-[1320px]">
            <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-[#0069FF]">سرویس‌های آویاتو</p>
                    <h2 class="mt-3 text-2xl font-bold leading-10 text-[#001739] md:text-3xl">قدم بعدی پروژه‌تان چیست؟</h2>
                </div>
                <a href="{{ route('solutions') }}" class="text-sm font-bold text-[#0069FF] hover:underline">همه راهکارها ←</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['cloud', 'اجرای سایت و اپلیکیشن با منابع مشخص و دسترسی مدیریتی.', 'مشاهده پلن‌ها', 'pricing', 'cpu.svg'],
                    ['domains', 'نام پروژه‌تان را پیدا کنید و سفارش ثبت دامنه را پیگیری کنید.', 'جستجوی دامنه', 'customer.domains.index', 'global-edit.svg'],
                    ['dns', 'رکوردهای دامنه، زیردامنه و ایمیل را از پنل مدیریت کنید.', 'مدیریت رکوردها', 'customer.cloud-dns.index', 'cloud-connection.svg'],
                    ['colocation', 'فضای رک، برق و شبکه متناسب با تجهیزات خود را بررسی کنید.', 'مشاهده شرایط میزبانی', 'solutions.colocation', 'data-2.svg'],
                ] as [$key, $body, $action, $destination, $icon])
                    <article class="flex flex-col rounded-2xl border border-[#C6DEFF] bg-[#FAFCFF] p-6 transition hover:border-[#0069FF]">
                        <div class="flex items-center justify-between gap-3">
                            <img src="{{ asset('assets/icons/figma/'.$icon) }}" alt="" aria-hidden="true" class="size-9">
                            <span class="text-xs text-slate-500" dir="ltr">{{ config('solutions.'.$key.'.eyebrow') }}</span>
                        </div>
                        <h3 class="mt-5 text-xl font-bold text-[#001739]">{{ config('solutions.'.$key.'.label') }}</h3>
                        <p class="mb-6 mt-3 text-sm leading-8 text-[#002355]">{{ $body }}</p>
                        <a href="{{ route($destination) }}" class="mt-auto text-sm font-bold text-[#0069FF] hover:underline">{{ $action }} ←</a>
                    </article>
                @endforeach
            </div>
            <p class="mt-4 text-xs leading-7 text-slate-500">جستجوی دامنه و مدیریت رکوردها در پنل مشتری انجام می‌شود.</p>
        </div>
    </section>

    <section id="plans" class="landing-plans bg-white px-4 py-14 md:px-8 md:py-20 lg:mt-16 lg:px-10">
        <div class="mx-auto grid max-w-[1320px] gap-8 lg:grid-cols-[449px_minmax(0,861px)] lg:items-start lg:gap-[10px]">
            <div class="text-right lg:pt-10">
                <a href="{{ route('pricing') }}" class="inline-flex flex-row-reverse items-center gap-2 text-[30px] font-bold leading-[44px] text-[#0046AA] md:text-[32px]">
                    <span aria-hidden="true" class="text-2xl">←</span><span>سرور ابری متناسب با پروژه شما</span>
                </a>
                <p class="mt-4 text-lg leading-8 text-[#000C1C] md:text-xl">منابع، قیمت و مدت استفاده را شفاف می‌بینید تا با خیال راحت، پلنی متناسب با نیاز پروژه‌تان انتخاب کنید.</p>
                <a href="{{ route('pricing') }}" class="mt-5 inline-flex h-11 items-center justify-center rounded-lg border border-[#0069FF] px-6 text-base text-[#0069FF] transition hover:bg-[#EEF5FF]">مشاهده همه پلن‌ها</a>
            </div>

            @if ($marketingBundles->isNotEmpty())
                <div class="grid gap-0">
                    @foreach ($marketingBundles->take(3) as $bundle)
                        @php($isRecommended = $loop->index === $recommendedIndex)
                        <a href="{{ route('pricing') }}" class="grid min-h-[126px] grid-cols-3 items-center gap-3 rounded-lg border border-[#C6DEFF] bg-[#FAFCFF] px-4 py-5 text-right transition hover:border-[#0046AA] {{ $isRecommended ? 'border-2 border-[#0046AA] bg-[#EFEFEF]' : '' }} sm:grid-cols-[1fr_1fr_1fr_auto] sm:gap-5 sm:px-6" dir="rtl">
                            <span class="col-span-3 min-w-0 sm:col-span-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <strong class="text-xl font-bold text-[#000C1C] md:text-2xl">{{ $bundle->name }}</strong>
                                    @if ($isRecommended)
                                        <span class="whitespace-nowrap rounded-full bg-[#F1396B] px-2.5 py-1 text-xs leading-5 text-white">پلن پیشنهادی</span>
                                    @endif
                                </span>
                                <span class="mt-1 block text-sm text-[#5A5A5A] md:text-base">{{ $wallets->format($bundle->monthly_price) }} / ماهانه</span>
                            </span>
                            <span class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap text-sm text-[#5A5A5A] md:text-lg"><img loading="lazy" decoding="async" src="{{ asset('assets/icons/figma/cpu.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->cpu_cores }} vCPU</span></span>
                            <span class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap text-sm text-[#5A5A5A] md:text-lg"><img loading="lazy" decoding="async" src="{{ asset('assets/icons/figma/ram.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->ram_gb }} GB</span></span>
                            <span class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap text-sm text-[#5A5A5A] md:text-lg"><img loading="lazy" decoding="async" src="{{ asset('assets/icons/figma/disk.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->disk_gb }} GB</span></span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-dashed border-[#C6DEFF] bg-[#FAFCFF] p-8 text-center text-[#5A5A5A]">فعلا پلنی برای نمایش وجود ندارد.</div>
            @endif
        </div>
    </section>


    <section class="mt-14 bg-[#F5F8FD] px-4 py-14 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-[1320px] gap-6 lg:grid-cols-2">
            <article class="rounded-3xl border border-[#C6DEFF] bg-white p-6 md:p-9">
                <p class="text-sm font-bold text-[#0069FF]">ثبت دامنه</p>
                <h2 class="mt-4 text-2xl font-bold leading-10 text-[#001739] md:text-3xl">نام پروژه بعدی‌تان را پیدا کنید.</h2>
                <p class="mt-4 leading-8 text-[#002355]">نام مورد نظرتان را جستجو کنید و در صورت آزاد بودن، ثبت سفارش را با مشاهده قیمت در پنل مشتری ادامه دهید.</p>
                <div class="mt-7" x-data="domainSearch({ publicSearch: true, url: @js(route('marketing.domains.check', [], false)) })">
                    <form x-cloak @submit.prevent="search()" :aria-busy="loading">
                        <label for="homepage-domain" class="block text-sm font-bold text-slate-700">نام دامنه</label>
                        <input id="homepage-domain" x-ref="domain" x-model="domain" @input="result = null; error = ''" type="text" maxlength="253" required dir="ltr" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="example.com" :readonly="loading" aria-describedby="homepage-domain-help" class="mt-3 w-full rounded-xl border border-slate-300 bg-slate-50 p-4 text-left text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <p id="homepage-domain-help" class="mt-2 text-xs leading-7 text-slate-500">نام دامنه را با پسوند وارد کنید. در صورت آزاد بودن و امکان خرید، به پنل مشتری منتقل می‌شوید.</p>
                        <button type="submit" :disabled="loading || redirecting || cooldown > 0" class="mt-4 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-bold text-white hover:bg-[#0050D0] disabled:cursor-wait disabled:opacity-60" x-text="redirecting ? 'در حال انتقال به پنل…' : (loading ? 'در حال بررسی…' : (cooldown ? 'جستجوی دوباره تا ' + cooldown + ' ثانیه دیگر' : 'جستجوی دامنه'))">جستجوی دامنه</button>
                    </form>
                    <div aria-live="polite" aria-atomic="true" class="mt-4 text-sm leading-8">
                        <p x-show="redirecting" x-cloak class="text-slate-600">دامنه آماده ادامه سفارش است؛ در حال انتقال به پنل مشتری…</p>
                        <p x-show="loading && !redirecting" x-cloak class="text-slate-600">در حال بررسی؛ این کار ممکن است چند ثانیه طول بکشد.</p>
                        <p x-show="error" x-cloak x-text="error" class="text-red-700" role="alert"></p>
                        <div x-show="result && !loading && !redirecting" x-cloak x-ref="result" tabindex="-1" class="rounded-xl bg-slate-50 p-4">
                            <p class="break-all font-bold" dir="ltr" x-text="result?.domain"></p>
                            <p x-text="!result?.available ? 'این دامنه در حال حاضر آزاد نیست؛ نام دیگری امتحان کنید.' : (result?.premium === true ? 'این دامنه ویژه است؛ برای بررسی قیمت با پشتیبانی تماس بگیرید.' : (result?.premium === null ? 'قیمت عادی یا ویژه این نام هنوز قابل تأیید نیست؛ کمی بعد دوباره بررسی کنید.' : 'دامنه آزاد است؛ در حال حاضر خرید آنلاین این پسوند فعال نیست.'))"></p>
                            <p x-show="result?.preliminary" class="mt-2 text-xs text-slate-500">نتیجه ایرنیک اولیه است؛ ثبت نهایی به تأیید ایرنیک بستگی دارد.</p>
                            <p class="mt-2 text-xs text-slate-500">جستجو نام دامنه را رزرو نمی‌کند.</p>
                            <a x-show="result?.available" href="{{ route('contact') }}" class="font-bold text-[#0069FF]">تماس با پشتیبانی ←</a>
                        </div>
                    </div>
                    <noscript><p class="mt-4 text-sm leading-8">برای جستجو در این صفحه جاوااسکریپت را فعال کنید، یا <a href="{{ route('customer.domains.index') }}" class="text-[#0069FF] underline">از پنل مشتری جستجو کنید</a>.</p></noscript>
                </div>
            </article>
            <article class="rounded-3xl bg-[#07172D] p-6 text-white md:p-9">
                <p class="text-sm font-bold text-blue-300">مدیریت DNS</p>
                <h2 class="mt-4 text-2xl font-bold leading-10 md:text-3xl">دامنه‌تان را به سرویس‌ها متصل کنید.</h2>
                <p class="mt-4 leading-8 text-slate-300">وب‌سایت، ایمیل و زیردامنه‌ها؛ رکوردهای هر سرویس را در یک پنل مدیریت کنید و وضعیت اتصال دامنه را ببینید.</p>
                <div class="my-7 rounded-xl border border-white/15 bg-white/5 p-4">
                    <p class="mb-3 text-xs text-slate-400">نمونه رکورد DNS</p>
                    <div class="flex flex-wrap items-center gap-3 font-mono text-sm text-blue-100" dir="ltr"><span>app.example.com</span><span class="rounded bg-blue-400/15 px-2 py-1">A</span><span aria-hidden="true">→</span><span>192.0.2.10</span></div>
                </div>
                <a href="{{ route('customer.cloud-dns.index') }}" class="inline-flex rounded-lg bg-white px-5 py-3 text-sm font-bold text-[#07172D] hover:bg-blue-50">مدیریت رکوردها ←</a>
            </article>
        </div>
    </section>

    <section class="landing-why mt-14 bg-white px-4 lg:mt-[84px] md:px-8 lg:px-10">
        <div class="mx-auto max-w-[894px] text-right lg:mr-[calc((100%-1320px)/2)]">
            <h2 class="text-[28px] font-bold leading-[42px] text-[#001739]">چرا آویاتو؟</h2>
            <div class="mt-2 grid gap-2">
                @foreach ($differenceRows as $row)
                    <article class="min-h-[70px]">
                        <div class="flex items-center justify-start gap-1 text-[#002F71]">
                            <img loading="lazy" decoding="async" src="{{ asset('assets/icons/figma/tick-circle.svg') }}" alt="" class="size-6" aria-hidden="true">
                            <h3 class="text-xl font-bold leading-8">{{ $row['title'] }}</h3>
                        </div>
                        <p class="mt-0.5 text-lg leading-8 text-[#002355]">{{ $row['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-14 px-4 md:px-8 lg:px-10">
        <div class="mx-auto flex max-w-[1320px] flex-col items-start justify-between gap-6 rounded-3xl bg-[#003A8E] p-7 text-white md:flex-row md:items-center md:p-10">
            <div><h2 class="text-2xl font-bold leading-10">برای قدم بعدی، کنار شما هستیم.</h2><p class="mt-3 leading-8 text-blue-100">انتخاب سرور، ثبت دامنه یا میزبانی تجهیزات؛ نیاز پروژه‌تان را با ما مطرح کنید.</p></div>
            <a href="{{ route('contact') }}" class="shrink-0 rounded-lg bg-white px-6 py-3 text-sm font-bold text-[#0069FF] hover:bg-blue-50">مشاوره رایگان ←</a>
        </div>
    </section>

    @if (! empty($latestPosts))
    <section class="landing-blog mt-14 bg-white px-4 lg:mt-16 md:px-8 lg:px-10">
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
