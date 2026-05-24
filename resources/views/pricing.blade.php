@extends('layouts.marketing')

@section('title', 'قیمت گذاری | آویاتو')
@section('description', 'پلن های زیرساخت ابری آویاتو برای استارتاپ ها، تیم های محصول و بارهای کاری عملیاتی.')

@php($activePage = 'pricing')

@section('content')
    <section class="relative overflow-hidden bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-16 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(circle_at_top,_rgba(0,105,255,0.18),_transparent_60%)]"></div>
        <div class="relative mx-auto max-w-4xl text-center">
            <p class="text-sm font-black text-[#0069FF]">قیمت گذاری شفاف</p>
            <h1 class="mt-4 text-4xl font-black leading-tight md:text-5xl">برای هر مرحله از رشد، یک پلن ساده و روشن</h1>
            <p class="mt-6 text-lg leading-9 text-slate-600">برای این صفحه از داده های نمونه استفاده شده، اما ساختار پلن ها بر اساس سناریوهای واقعی تیم های SaaS، فروشگاهی و زیرساختی طراحی شده است.</p>
        </div>
    </section>

    <section class="px-4 pb-16 md:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-5 lg:grid-cols-3">
            @foreach ([
                ['name' => 'Starter', 'price' => '۴۹۰٬۰۰۰', 'badge' => 'برای شروع سریع', 'desc' => 'برای MVP، لندینگ، وب اپ های سبک و محیط تست.', 'features' => ['۲ vCPU اختصاصی', '۴GB RAM DDR4', '۸۰GB NVMe', '۱ IP عمومی', 'بکاپ هفتگی'], 'tone' => 'border-slate-200 bg-white'],
                ['name' => 'Growth', 'price' => '۹۸۰٬۰۰۰', 'badge' => 'پیشنهاد تیم محصول', 'desc' => 'برای سرویس های production با ترافیک متوسط و استقرار مداوم.', 'features' => ['۴ vCPU اختصاصی', '۸GB RAM DDR4', '۱۶۰GB NVMe', 'شبکه خصوصی', 'بکاپ روزانه'], 'tone' => 'border-[#B8D6FF] bg-[#EBF3FF]'],
                ['name' => 'Scale', 'price' => '۲٬۴۵۰٬۰۰۰', 'badge' => 'برای عملیات پایدار', 'desc' => 'برای بارهای کاری حساس، صف ها، دیتابیس ها و میکروسرویس ها.', 'features' => ['۸ vCPU اختصاصی', '۱۶GB RAM DDR4', '۳۲۰GB NVMe RAID', 'فایروال مدیریتی', 'مانیتورینگ پایه'], 'tone' => 'border-slate-200 bg-white'],
            ] as $plan)
                <article class="rounded-2xl border p-6 shadow-sm {{ $plan['tone'] }}">
                    <p class="text-sm font-black text-[#0069FF]">{{ $plan['badge'] }}</p>
                    <h2 class="mt-4 text-3xl font-black">{{ $plan['name'] }}</h2>
                    <p class="mt-3 text-4xl font-black">{{ $plan['price'] }}</p>
                    <p class="mt-1 text-sm font-bold text-slate-500">تومان / ماه</p>
                    <p class="mt-5 min-h-20 text-sm leading-8 text-slate-600">{{ $plan['desc'] }}</p>
                    <div class="mt-6 space-y-3 border-t border-slate-200/80 pt-6 text-sm font-bold text-slate-700">
                        @foreach ($plan['features'] as $feature)
                            <div class="flex items-center gap-2">
                                <svg class="size-5 shrink-0 text-[#0069FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>{{ $feature }}</span>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('contact') }}" class="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">درخواست مشاوره</a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['title' => 'پرداخت منعطف', 'body' => 'پرداخت ماهانه، شارژ اعتباری و فاکتور سازمانی به صورت نمونه در دسترس هستند.'],
                    ['title' => 'هزینه قابل پیش بینی', 'body' => 'هر پلن سقف مصرف و منابع مشخص دارد تا هزینه ها از کنترل خارج نشوند.'],
                    ['title' => 'ارتقای بی دردسر', 'body' => 'در زمان رشد، منابع CPU، RAM و فضای دیسک با کمترین وقفه قابل افزایش هستند.'],
                ] as $item)
                    <div class="rounded-xl bg-slate-50 p-5">
                        <h3 class="text-lg font-black">{{ $item['title'] }}</h3>
                        <p class="mt-3 text-sm leading-8 text-slate-600">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
