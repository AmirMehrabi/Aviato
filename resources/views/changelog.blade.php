@extends('layouts.marketing')

@section('title', 'لیست تغییرات آویاتو | نسخه‌های منتشرشده')
@section('description', 'نسخه‌های منتشرشده آویاتو و خلاصه تغییرات هر release.')

@php
    $activePage = 'changelog';

    $releases = [
        [
            'version' => '0.8.7',
            'date' => 'ششم خرداد ۱۴۰۵',
            // 'tag' => 'بهبودهای رابط و auth',
            // 'summary' => 'این نسخه روی تبدیل صفحه خانه به یک لندینگ فروش VPS و پاکسازی مسیرهای ورود و ثبت نام برای پنل‌ها تمرکز دارد.',
            'items' => [
                'بازطراحی hero صفحه خانه با ظاهر browser-window و نگه داشتن کارت سفارش VPS داخل آن',
                'به‌روزرسانی FAQ با سوالات واقعی قبل از خرید مثل زمان تحویل، انتخاب پلن، دسترسی روت و ارتقا منابع',
                'حذف مسیر ثبت نام مدیر و پاک کردن traceهای آن از UI ورود مدیر',
                'جایگزینی mark مربعی قدیمی با لوگوی واقعی Aviato در صفحات ورود و ثبت نام',
                'رفع مشکل حذف VMهایی که هنگام حذف در سیستم باقی می‌ماندند'
            ],
        ],
        [
            'version' => '0.8.6',
            'date' => 'پنجم خرداد ۱۴۰۵',
            // 'tag' => 'بهبودهای امروز',
            'summary' => 'این نسخه روی روان‌تر شدن تجربه مشتری و پاکسازی وضعیت‌های ناسازگار تمرکز دارد؛ هم در ساخت سرور و هم در نگه‌داری داده‌های سرور.',
            'items' => [
                'افزودن حالت loading برای دکمه «Add Server» تا کاربر وضعیت ارسال را ببیند',
                'اضافه شدن امکان حذف سرورها از سمت مشتری',
                'تشخیص anomalyها و سرورهایی که دیگر در Proxmox وجود ندارند',
                'پاکسازی خودکار VMهای stale و حذف آن‌ها از پنل',
            ],
        ],
        [
            'version' => '0.8.5',
            'date' => 'چهارم خرداد ۱۴۰۵',
            'tag' => 'اولین انتشار',
            'summary' => 'اولین نسخه عمومی آویاتو بود؛ یک شروع ساده برای ساخت، مدیریت و تحویل VPS با مسیر خرید روشن و تجربه قابل فهم برای مشتری.',
            'items' => [
                'معرفی صفحه اصلی و مسیر خرید اولیه',
                'راه اندازی ثبت نام، ورود و تایید حساب برای مشتریان',
                'ساخت سرور مشتری با جریان ابتدایی انتخاب پلن و سیستم عامل',
                'نمایش قابلیت‌های اصلی مثل بکاپ، مانیتورینگ و قیمت گذاری شفاف',
            ],
        ]
    ];
@endphp

@section('content')
    <section class="bg-gradient-to-b from-[#EBF3FF] via-white to-white px-4 pb-14 pt-16 md:px-8 md:pt-24 lg:px-10">
        <div class="mx-auto max-w-4xl text-center">
            <h1 class="mt-4 text-4xl font-black leading-tight md:text-5xl">تغییرات نسخه‌های آویاتو</h1>
            <p class="mt-6 text-lg leading-9 text-slate-600">
                خلاصه releaseها. نسخه‌های آینده بعدا به همین صفحه اضافه می‌شوند.
            </p>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-5xl space-y-5">
            @foreach ($releases as $release)
                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 bg-[#F7FBFF] px-6 py-6 md:flex-row md:items-start md:justify-between md:px-8">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-3xl font-black text-slate-950">نسخه {{ $release['version'] }}</h2>
                                @if (isset($release['tag']))
                                    <span class="rounded-full bg-[#EAF4FF] px-3 py-1 text-xs font-black text-[#0069FF]">{{ $release['tag'] }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm  text-slate-500">انتشار در <span class="font-extrabold text-slate-800">{{ $release['date'] }}</span></p>
                        </div>
                        @if (isset($release['summary']))
                        <div class="rounded-2xl bg-white px-4 py-3 text-sm leading-7 text-slate-600 md:max-w-sm">
                            {{ $release['summary'] }}
                        </div>                            
                        @endif

                    </div>
                    <div class="px-6 py-6 md:px-8">
                        <ul class="grid gap-3 md:grid-cols-2">
                            @foreach ($release['items'] as $item)
                                <li class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-7 text-slate-700">
                                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-[#0069FF]"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @endforeach

            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-5 text-sm leading-7 text-slate-600">
                نسخه‌های بعدی همین‌جا اضافه می‌شوند تا تغییرات آینده برای کاربران، شفاف و ساده قابل پیگیری باشد.
            </div>
        </div>
    </section>
@endsection
