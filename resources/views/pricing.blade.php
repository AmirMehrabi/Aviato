@extends('layouts.marketing')

@section('title', 'آویاتو: قیمت‌گذاری سرویس‌های ابری')
@section('description', 'قیمت پلن های ماشین مجازی آویاتو با منابع مشخص، دیسک NVMe، IP اختصاصی و پرداخت ماهانه یا ساعتی.')

@php
    $activePage = 'pricing';
    $pricingBundles = ($bundles ?? collect())->values();
    $featuredIndex = $pricingBundles->count() > 1 ? 1 : null;
    $meta = [
        ['badge' => 'برای شروع آسان', 'fit' => 'سایت، وردپرس و پروژه های کوچک'],
        ['badge' => 'پلن پیشنهادی', 'fit' => 'فروشگاه، اپلیکیشن و سرویس های در حال رشد'],
        ['badge' => 'برای کارهای جدی تر', 'fit' => 'دیتابیس، پردازش و سایت های پربازدید'],
    ];
@endphp

@section('body_class', 'figma-marketing pricing-page bg-white')

@section('content')
    <section class="relative isolate min-h-[640px] overflow-hidden bg-[linear-gradient(180deg,#EDF4FE_0%,#FFFFFF_92%)] px-4 pb-12 md:px-8 lg:px-10">
        <div class="mx-auto max-w-[1320px]">
            <div class="ml-auto min-h-[224px] max-w-[872px] text-right">
                <h1 class="text-3xl font-bold leading-[48px] text-[#000C1C] md:text-[32px]">پلن <span class="text-[#0069FF]">ماشین مجازی</span> را راحت انتخاب کنید و بخرید</h1>
                <p class="mt-6 text-lg leading-9 text-[#5A5A5A] md:text-[22px] md:leading-10">هر پلن می‌بینید چه مقدار CPU، RAM، دیسک و IP می‌گیرید و هزینه ماهانه یا ساعتی آن چقدر است. بعد از انتخاب پلن، می‌توانید حساب مشتری بسازید و سفارش را ادامه دهید.</p>
                <a href="{{ route('contact') }}" class="mt-6 inline-flex h-12 items-center justify-center rounded-lg bg-[#0069FF] px-8 text-sm font-bold text-white transition hover:bg-[#0050D0]">مشاوره رایگان</a>
            </div>
            <div class="mt-6 flex min-h-[145px] flex-col justify-center gap-[18px] rounded-[28px] border border-[#E3EEFF] bg-[#FAFCFF] px-6 py-3 shadow-[0_0_1px_#C6DEFF]">
                <p class="text-right text-lg font-bold leading-[26px] text-[#0069FF]">راهنمای سریع انتخاب</p>
                <div class="grid gap-2 text-right text-base leading-[27px] text-[#002355] md:grid-cols-3 md:gap-6 md:text-lg">
                    <p><strong>قدرت بیشتر:</strong> مناسب دیتابیس، پردازش و ترافیک بالاتر.</p>
                    <p><strong>رشد:</strong> انتخاب بهتر برای فروشگاه و اپلیکیشن‌های فعال.</p>
                    <p><strong>شروع:</strong> مناسب سایت سبک، تست و پروژه‌های تازه.</p>
                </div>
            </div>
        </div>
    </section>

    @if ($pricingBundles->isNotEmpty())
        <section class="bg-white px-4 pb-0 pt-8 text-[#000C1C] md:px-8 lg:px-10">
            <div class="mx-auto max-w-[1320px]">
                <div class="ml-auto flex min-h-[90px] max-w-[1195px] flex-col items-start justify-center gap-2 text-right">
                    <h2 class="text-[28px] font-bold leading-[42px] text-[#000C1C]">قیمت‌گذاری و مقایسه منابع</h2>
                    <p class="text-lg leading-8 text-[#003A8E] md:text-[22px] md:leading-10">این جدول برای مقایسه سریع پلن‌هاست. هزینه نهایی بر اساس پلنی که انتخاب می‌کنید و وضعیت استفاده از سرور محاسبه می‌شود.</p>
                </div>
                <div class="mt-4 overflow-hidden rounded-xl border border-[#C6DEFF] bg-[#FAFCFF]">
                    <div class="overflow-x-auto">
                        <div class="min-w-[1120px]">
                        <div class="grid h-[83px] grid-cols-[minmax(180px,1.2fr)_minmax(130px,1fr)_minmax(130px,1fr)_minmax(130px,1fr)_minmax(190px,1.35fr)_minmax(170px,1.2fr)_64px] items-center border-b border-[#C6DEFF] bg-[#E3EEFF] text-center text-base text-[#5A5A5A]">
                            <div class="px-3 text-right">نام سرویس</div>
                            <div class="px-3">پردازنده</div>
                            <div class="px-3">حافظه</div>
                            <div class="px-3">دیسک</div>
                            <div class="px-3">قیمت / ماهیانه</div>
                            <div class="px-3">قیمت / ساعتی</div>
                            <div aria-hidden="true"></div>
                        </div>
                        @foreach ($pricingBundles as $bundle)
                            <div class="grid min-h-[126px] grid-cols-[minmax(180px,1.2fr)_minmax(130px,1fr)_minmax(130px,1fr)_minmax(130px,1fr)_minmax(190px,1.35fr)_minmax(170px,1.2fr)_64px] items-center border-b border-[#C6DEFF] text-center last:border-b-0 {{ $loop->index === $featuredIndex ? 'bg-[#EFEFEF] ring-2 ring-inset ring-[#0046AA]' : 'bg-[#FAFCFF]' }}">
                                <div class="px-4 text-right">
                                    <div class="flex flex-wrap items-center justify-start gap-2">
                                        <span class="text-xl font-bold leading-9 text-[#000C1C]">{{ $bundle->name }}</span>
                                        @if ($loop->index === $featuredIndex)
                                            <span class="whitespace-nowrap rounded-full bg-[#F1396B] px-3 py-1 text-xs leading-5 text-white">پلن پیشنهادی</span>
                                        @endif
                                    </div>
                                    <p class="line-clamp-2 text-sm leading-6 text-[#5A5A5A]">{{ $bundle->description ?: ($meta[$loop->index]['fit'] ?? 'برای پروژه‌های کوچک') }}</p>
                                </div>
                                <div class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap px-2 text-lg text-[#5A5A5A]" dir="ltr"><img src="{{ asset('assets/icons/figma/cpu.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->cpu_cores }} vCPU</span></div>
                                <div class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap px-2 text-lg text-[#5A5A5A]" dir="ltr"><img src="{{ asset('assets/icons/figma/ram.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->ram_gb }}GB</span></div>
                                <div class="flex flex-nowrap items-center justify-center gap-1 whitespace-nowrap px-2 text-lg text-[#5A5A5A]" dir="ltr"><img src="{{ asset('assets/icons/figma/disk.svg') }}" alt="" class="size-6" aria-hidden="true"><span>{{ $bundle->disk_gb }}GB</span></div>
                                <div class="px-2 text-lg font-bold text-[#003A8E]">{{ $wallets->format($bundle->monthly_price) }}</div>
                                <div class="px-2 text-base text-[#5A5A5A]">{{ $wallets->format(round($bundle->monthly_price / 720)) }}</div>
                                <a href="{{ route('customer.register') }}" aria-label="خرید پلن {{ $bundle->name }}" class="mx-auto flex size-8 items-center justify-center text-[#0069FF] transition hover:translate-x-[-2px]">
                                    <img src="{{ asset('assets/icons/figma/arrow-left.svg') }}" alt="" class="size-8" aria-hidden="true">
                                </a>
                            </div>
                        @endforeach
                        <div aria-hidden="true" class="h-[6px]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="bg-white px-4 pb-0 pt-[86px] text-right md:px-8 lg:px-10">
        <div class="mx-auto max-w-[1320px]">
            <div class="ml-auto grid max-w-[648px] gap-6">
                @foreach ([
                    ['title' => 'پرداخت با کیف پول', 'body' => 'کیف پول خود را شارژ می‌کنید و هزینه سرورها از همانجا قابل پیگیری است.', 'icon' => 'empty-wallet-tick.svg'],
                    ['title' => 'پرداخت آسان', 'body' => 'کیف پول خود را شارژ می‌کنید و هزینه سرورها از همانجا قابل پیگیری است.', 'icon' => 'moneys.svg'],
                    ['title' => 'پشتیبانی 24 ساعته', 'body' => 'برای انتخاب و استفاده از سرویس‌ها، تیم پشتیبانی در کنار شماست.', 'icon' => 'clock.svg'],
                ] as $item)
                    <article class="text-right">
                        <div class="flex items-center justify-start gap-1 text-[#002F71]">
                            <h3 class="text-xl font-bold leading-[30px]">{{ $item['title'] }}</h3>
                            <img src="{{ asset('assets/icons/figma/'.$item['icon']) }}" alt="" class="size-6" aria-hidden="true">
                        </div>
                        <p class="mt-1 text-xl leading-9 text-[#002355]">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 pb-[111px] pt-10 md:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[140px] max-w-[1320px] flex-col-reverse items-start justify-between gap-6 rounded-[28px] border border-[#AACDFF] bg-[#002F71] px-6 py-7 text-white md:flex-row-reverse md:items-center md:px-16 md:py-[31px]">
            <a href="{{ route('customer.register') }}" class="inline-flex min-h-14 items-center justify-center rounded-lg bg-[#FAFCFF] px-10 text-sm text-[#0069FF] transition hover:bg-white">سرور خود را راه بیاندازید</a>
            <div class="text-right">
                <h2 class="text-2xl font-bold leading-9">پلن مناسب خود را پیدا کردید؟</h2>
                <p class="mt-1 text-lg leading-8">حساب خود را بسازید و ماشین مجازی خود را سفارش دهید</p>
            </div>
        </div>
    </section>
@endsection
