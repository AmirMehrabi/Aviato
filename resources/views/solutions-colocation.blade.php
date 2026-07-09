@extends('layouts.marketing')

@section('title', 'Co-location آویاتو | میزبانی تجهیزات در دیتاسنتر')
@section('description', 'سرویس Co-location آویاتو برای میزبانی سرور و تجهیزات شبکه شما در رک استاندارد دیتاسنتر با برق پایدار، شبکه مطمئن، IP و هماهنگی عملیاتی.')

@php
    $activePage = 'solutions';

    $fitCards = [
        ['title' => 'مالک سخت‌افزار هستید', 'body' => 'سرور، فایروال، استوریج یا تجهیزات شبکه را خودتان تهیه کرده‌اید و نمی‌خواهید کنترل سخت‌افزار را واگذار کنید.'],
        ['title' => 'نیاز به رک امن دارید', 'body' => 'فضای اداری، برق شهری یا اینترنت معمولی برای سرویس شما کافی نیست و باید تجهیزات در محیط کنترل‌شده نگهداری شود.'],
        ['title' => 'رشد تدریجی می‌خواهید', 'body' => 'می‌خواهید از یک یا چند یونیت شروع کنید و در صورت نیاز ظرفیت رک، برق، IP یا پهنای باند را مرحله‌ای افزایش دهید.'],
    ];

    $deliverables = [
        ['title' => 'فضای رک', 'body' => 'جانمایی تجهیزات در رک استاندارد، با هماهنگی قبلی برای اندازه، عمق، تعداد یونیت و نیازهای کابل‌کشی.'],
        ['title' => 'برق پایدار', 'body' => 'تأمین برق دیتاسنتری متناسب با توان مصرفی اعلام‌شده و بررسی ظرفیت قبل از تحویل.'],
        ['title' => 'اتصال شبکه', 'body' => 'تحویل پورت شبکه، IP و تنظیمات اولیه مورد توافق برای اتصال تجهیزات شما به اینترنت یا شبکه اختصاصی.'],
        ['title' => 'هماهنگی عملیات', 'body' => 'فرایند مشخص برای تحویل، نصب، بازدید، جابه‌جایی، خاموشی برنامه‌ریزی‌شده و درخواست‌های حضوری.'],
    ];

    $steps = [
        ['number' => '01', 'title' => 'نیازسنجی تجهیزات', 'body' => 'مدل دستگاه، تعداد یونیت، مصرف برق، تعداد پورت، IP و نیازهای دسترسی را اعلام می‌کنید.'],
        ['number' => '02', 'title' => 'بررسی ظرفیت و پیشنهاد', 'body' => 'تیم آویاتو ظرفیت رک، برق و شبکه را بررسی می‌کند و پیشنهاد عملیاتی و هزینه را شفاف اعلام می‌کند.'],
        ['number' => '03', 'title' => 'تحویل و نصب', 'body' => 'زمان تحویل هماهنگ می‌شود، تجهیزات در رک نصب می‌شوند و اتصال شبکه طبق توافق تحویل می‌شود.'],
        ['number' => '04', 'title' => 'بهره‌برداری و پشتیبانی', 'body' => 'درخواست‌های دسترسی، تغییر کابل، بررسی وضعیت یا توسعه ظرفیت از مسیر پشتیبانی پیگیری می‌شود.'],
    ];

    $responsibilities = [
        ['side' => 'با آویاتو', 'items' => ['فضای رک و برق مطابق توافق', 'تحویل اتصال شبکه و IP', 'هماهنگی نصب و دسترسی', 'پشتیبانی برای درخواست‌های دیتاسنتری']],
        ['side' => 'با مشتری', 'items' => ['تهیه و مالکیت سخت‌افزار', 'سلامت سیستم‌عامل و نرم‌افزارها', 'تهیه کابل یا ریل خاص در صورت نیاز', 'بکاپ و امنیت سرویس‌های داخل تجهیزات']],
    ];

    $faqs = [
        ['q' => 'Co-location برای چه کسانی مناسب است؟', 'a' => 'برای تیم‌هایی که سخت‌افزار خودشان را دارند، به کنترل کامل روی دستگاه نیاز دارند و می‌خواهند آن را در محیط دیتاسنتری با برق و شبکه پایدار نگهداری کنند.'],
        ['q' => 'آیا می‌توانم با یک سرور شروع کنم؟', 'a' => 'بله. نقطه شروع می‌تواند یک یا چند یونیت باشد. قبل از تحویل، اندازه، مصرف برق و نیاز شبکه بررسی می‌شود.'],
        ['q' => 'آیا آویاتو مدیریت سیستم‌عامل را انجام می‌دهد؟', 'a' => 'در حالت پایه، مدیریت سیستم‌عامل، نرم‌افزارها، امنیت داخلی و بکاپ داده‌ها با مشتری است. خدمات مدیریتی جداگانه فقط در صورت توافق جداگانه انجام می‌شود.'],
        ['q' => 'برای قیمت باید چه اطلاعاتی بدهم؟', 'a' => 'تعداد دستگاه، یونیت رک، توان مصرفی، تعداد پورت، نیاز IP، پهنای باند تقریبی و نوع دسترسی مورد نیاز، اطلاعات اصلی برای اعلام پیشنهاد هستند.'],
    ];
@endphp

@section('body_class', 'bg-[#F5F8FD]')

@section('content')
    <section class="relative isolate overflow-hidden bg-white px-4 pb-16 pt-28 md:px-8 md:pb-20 md:pt-32 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-20 h-[36rem] bg-[linear-gradient(180deg,#EEF5FF_0%,#FFFFFF_78%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-[#0069FF]">Co-location</p>
                <h1 class="mt-4 text-4xl font-medium leading-[1.18] text-slate-950 sm:text-5xl md:text-6xl">
                    سخت‌افزار شما،
                    <span class="block text-slate-700">در محیط دیتاسنتری مطمئن.</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 md:text-lg md:leading-9">
                    اگر مالک سرور یا تجهیزات شبکه خودتان هستید، Co-location آویاتو کمک می‌کند تجهیزات را در رک استاندارد، با برق پایدار، شبکه قابل اتکا و فرایند عملیاتی روشن میزبانی کنید.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                        درخواست مشاوره Co-location
                    </a>
                    <a href="{{ route('solutions') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-7 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#F7FBFF] hover:text-[#0069FF]">
                        مقایسه با سرور ابری
                    </a>
                </div>
            </div>

            <div class="relative min-h-[31rem] overflow-hidden rounded-[2rem] border border-slate-200 bg-[#07172D] p-6 text-white shadow-2xl shadow-slate-200/70">
                <div aria-hidden="true" class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('{{ asset('assets/images/hero-section.webp') }}');"></div>
                <div aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(135deg,rgba(7,23,45,.96),rgba(7,23,45,.72))]"></div>
                <div class="relative grid h-full min-h-[28rem] content-between">
                    <div>
                        <p class="text-sm font-bold text-[#8FC7FF]">مناسب برای تجهیزات اختصاصی</p>
                        <h2 class="mt-4 max-w-lg text-3xl leading-tight">وقتی سرور فیزیکی بخشی از معماری شماست، محیط نگهداری نباید نقطه ضعف باشد.</h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                            <p class="text-2xl font-black" dir="ltr">Rack</p>
                            <p class="mt-2 text-xs leading-6 text-slate-300">جانمایی کنترل‌شده</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                            <p class="text-2xl font-black" dir="ltr">Power</p>
                            <p class="mt-2 text-xs leading-6 text-slate-300">بر اساس ظرفیت توافقی</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                            <p class="text-2xl font-black" dir="ltr">Network</p>
                            <p class="mt-2 text-xs leading-6 text-slate-300">اتصال و IP قابل پیگیری</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-16 md:px-8 md:py-20 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">چه زمانی Co-location انتخاب درستی است؟</p>
                <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">وقتی کنترل سخت‌افزار برای شما مهم است.</h2>
                <p class="mt-5 leading-8 text-slate-600">سرور ابری برای شروع سریع عالی است؛ اما بعضی تیم‌ها به سخت‌افزار اختصاصی، تجهیزات شبکه خاص، لایسنس‌های وابسته به دستگاه یا کنترل کامل روی قطعات نیاز دارند.</p>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach($fitCards as $card)
                    <article class="rounded-[1.75rem] border border-slate-200 bg-[#FBFDFF] p-6 shadow-sm shadow-slate-200/50">
                        <h3 class="text-xl text-slate-950">{{ $card['title'] }}</h3>
                        <p class="mt-4 text-sm leading-8 text-slate-600">{{ $card['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F5F8FD] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.82fr_1.18fr] lg:items-start">
            <div>
                <p class="text-sm font-black text-[#0069FF]">آنچه تحویل می‌شود</p>
                <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">زیرساخت فیزیکی باید با قرارداد روشن شروع شود.</h2>
                <p class="mt-5 leading-8 text-slate-600">قبل از انتقال تجهیزات، جزئیات رک، برق، شبکه، IP، دسترسی و مسئولیت‌ها باید مشخص باشد تا بهره‌برداری بعدی قابل پیش‌بینی بماند.</p>
                <a href="{{ route('contact') }}" class="mt-7 inline-flex rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#F7FBFF] hover:text-[#0069FF]">
                    ارسال مشخصات تجهیزات
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($deliverables as $item)
                    <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/50">
                        <div class="mb-5 grid size-10 place-items-center rounded-2xl bg-[#EEF5FF] text-[#0069FF]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <h3 class="text-xl text-slate-950">{{ $item['title'] }}</h3>
                        <p class="mt-4 text-sm leading-8 text-slate-600">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">فرایند شروع</p>
                <h2 class="mt-3 text-3xl leading-tight text-slate-950 md:text-4xl">از نیازسنجی تا تحویل، مرحله‌ها روشن هستند.</h2>
            </div>
            <div class="mt-10 grid gap-4 lg:grid-cols-4">
                @foreach($steps as $step)
                    <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/50">
                        <p class="font-mono text-sm font-black text-[#0069FF]" dir="ltr">{{ $step['number'] }}</p>
                        <h3 class="mt-5 text-xl text-slate-950">{{ $step['title'] }}</h3>
                        <p class="mt-4 text-sm leading-8 text-slate-600">{{ $step['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#07172D] px-4 py-20 text-white md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-start">
            <div>
                <p class="text-sm font-bold text-[#8FC7FF]">مرز مسئولیت‌ها</p>
                <h2 class="mt-3 text-3xl leading-tight md:text-4xl">برای جلوگیری از ابهام، مسئولیت‌ها را از ابتدا جدا می‌کنیم.</h2>
                <p class="mt-5 leading-8 text-slate-300">Co-location یعنی سخت‌افزار شما در محیط دیتاسنتری نگهداری می‌شود. مدیریت سرویس‌های داخل دستگاه، مگر با توافق جداگانه، همچنان با تیم شماست.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($responsibilities as $group)
                    <article class="rounded-[1.75rem] border border-white/10 bg-white/[0.07] p-6">
                        <h3 class="text-2xl">{{ $group['side'] }}</h3>
                        <ul class="mt-5 grid gap-3 text-sm leading-7 text-slate-300">
                            @foreach($group['items'] as $item)
                                <li class="flex gap-3">
                                    <span class="mt-2 size-1.5 shrink-0 rounded-full bg-[#8FC7FF]"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F5F8FD] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-5xl">
            <div class="text-center">
                <h2 class="text-3xl leading-tight text-slate-950 md:text-4xl">سوال‌های رایج Co-location</h2>
                <p class="mx-auto mt-5 max-w-2xl leading-8 text-slate-600">اگر تجهیزات شما نیاز خاصی دارد، بهترین مسیر این است که قبل از انتقال دستگاه، جزئیات فنی را با تیم آویاتو بررسی کنید.</p>
            </div>
            <div class="mt-10 divide-y divide-slate-100 rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/50">
                @foreach($faqs as $faq)
                    <details class="group p-5 open:bg-white md:p-6" @if($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-right text-lg text-slate-950">
                            <span>{{ $faq['q'] }}</span>
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#EEF5FF] text-[#0069FF] transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-4 text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 rounded-[2rem] bg-[#07172D] p-6 text-white md:p-10 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-sm font-bold text-[#8FC7FF]">شروع با بررسی فنی</p>
                <h2 class="mt-3 text-3xl leading-tight md:text-4xl">مشخصات تجهیزات را بفرستید تا ظرفیت و مسیر تحویل بررسی شود.</h2>
                <p class="mt-4 text-sm leading-8 text-slate-300">برای اعلام قیمت دقیق، مدل دستگاه، تعداد یونیت، مصرف برق، پورت شبکه، IP و نیاز دسترسی را آماده کنید.</p>
            </div>
            <a href="{{ route('contact') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-7 py-3.5 text-sm font-bold text-[#07172D] transition hover:bg-blue-50">
                هماهنگی با فروش
            </a>
        </div>
    </section>
@endsection
