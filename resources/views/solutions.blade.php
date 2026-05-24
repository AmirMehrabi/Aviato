@extends('layouts.marketing')

@section('title', 'راهکارها | آویاتو')
@section('description', 'راهکارهای زیرساخت ابری آویاتو برای فروشگاه ها، SaaS، AI، دیتابیس و محیط های توسعه.')

@php($activePage = 'solutions')

@section('content')
    <section class="relative overflow-hidden bg-slate-950 px-4 pb-20 pt-16 text-white md:px-8 md:pt-24 lg:px-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(0,105,255,0.35),_transparent_35%),radial-gradient(circle_at_top_left,_rgba(16,185,129,0.22),_transparent_25%)]"></div>
        <div class="relative mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-sm font-black text-blue-200">راهکارهای قابل اجرا</p>
                <h1 class="mt-4 text-4xl font-black leading-tight md:text-6xl">زیرساختی که با مدل کسب و کار شما حرف می زند</h1>
                <p class="mt-6 max-w-2xl text-lg leading-9 text-slate-200">این صفحه از سناریوهای واقعی بازار الهام گرفته: استارتاپ SaaS، فروشگاه آنلاین، پردازش AI، دیتابیس های حساس و محیط های staging. حتی اگر هنوز این بسته ها را نفروشیم، هر مورد از نظر فنی و تجاری قابل استفاده است.</p>
                <div class="mt-8 flex flex-wrap gap-3 text-sm font-bold">
                    <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">Latency پایین</span>
                    <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">Scaling مرحله ای</span>
                    <span class="rounded-full border border-white/15 bg-white/10 px-4 py-2">امنیت و بکاپ</span>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['metric' => '۹۹.۹٪', 'label' => 'هدف دسترس پذیری سرویس'],
                    ['metric' => '< ۶۰s', 'label' => 'زمان تحویل نمونه VM'],
                    ['metric' => '۱۰Gb', 'label' => 'ظرفیت شبکه داخلی'],
                    ['metric' => '۲۴/۷', 'label' => 'پایش سلامت زیرساخت'],
                ] as $stat)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                        <p class="text-3xl font-black text-white">{{ $stat['metric'] }}</p>
                        <p class="mt-2 text-sm leading-7 text-slate-300">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-4 py-16 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="mb-10 max-w-3xl">
                <p class="text-sm font-black text-[#0069FF]">راهکارهای پیشنهادی</p>
                <h2 class="mt-3 text-3xl font-black leading-tight md:text-5xl">سناریوهای واقعی برای تیم های مختلف</h2>
            </div>
            <div class="grid gap-5 xl:grid-cols-3">
                @foreach ([
                    ['name' => 'فروشگاه آنلاین', 'summary' => 'برای ووکامرس، مجنتو یا فروشگاه اختصاصی با تمرکز روی سرعت صفحه و تحمل کمپین های فروش.', 'stack' => 'Nginx + PHP-FPM + Redis + MySQL Replica', 'fit' => '۲۰ تا ۲۰۰ هزار بازدید ماهانه', 'items' => ['کش Redis برای سبد خرید و session', 'Replica دیتابیس برای گزارش گیری', 'بکاپ روزانه و بازگردانی سریع'], 'accent' => 'from-[#0069FF] to-[#4F46E5]'],
                    ['name' => 'SaaS و API', 'summary' => 'برای اپلیکیشن های B2B، پنل های مدیریتی و APIهای production با استقرار مستمر.', 'stack' => 'Docker + Queue Worker + PostgreSQL + Object Storage', 'fit' => 'تیم های محصول ۳ تا ۲۰ نفره', 'items' => ['محیط staging و production جدا', 'Queue برای ایمیل، وب هوک و jobها', 'مقیاس پذیری افقی API'], 'accent' => 'from-[#0F766E] to-[#14B8A6]'],
                    ['name' => 'AI و پردازش داده', 'summary' => 'برای inference سبک، ETL و پردازش batch که نیازمند I/O بالا و زمان بندی دقیق هستند.', 'stack' => 'Python Worker + GPU Ready Nodes + S3 Compatible Storage', 'fit' => 'تیم های هوش مصنوعی و تحلیل داده', 'items' => ['صف پردازش برای jobهای سنگین', 'ذخیره سازی آبجکت برای dataset', 'نودهای مخصوص پردازش موازی'], 'accent' => 'from-[#EA580C] to-[#F59E0B]'],
                    ['name' => 'دیتابیس حیاتی', 'summary' => 'برای PostgreSQL و MySQL با نیاز به پایداری، بکاپ خودکار و نظارت دقیق.', 'stack' => 'Managed Backups + Read Replica + Private Network', 'fit' => 'سرویس های مالی، CRM و ERP', 'items' => ['شبکه خصوصی میان اپ و دیتابیس', 'Replica برای خواندن و failover', 'مانیتورینگ lag و فضای دیسک'], 'accent' => 'from-[#7C3AED] to-[#A855F7]'],
                    ['name' => 'محیط توسعه تیمی', 'summary' => 'برای تیم هایی که می خواهند preview env، staging و sandbox را سریع بالا بیاورند.', 'stack' => 'Template VM + CI/CD + Shared VPN', 'fit' => 'تیم های توسعه و QA', 'items' => ['ساخت محیط از روی template', 'VPN مشترک برای تست داخلی', 'خاموشی خودکار محیط های موقت'], 'accent' => 'from-[#BE123C] to-[#FB7185]'],
                    ['name' => 'اپلیکیشن سازمانی', 'summary' => 'برای اتوماسیون، ERP و سرویس های داخلی که به دسترسی کنترل شده و گزارش گیری نیاز دارند.', 'stack' => 'Private VLAN + Reverse Proxy + Audit Logging', 'fit' => 'شرکت های متوسط و بزرگ', 'items' => ['دسترسی محدود مبتنی بر IP', 'ثبت رویدادهای مدیریتی', 'تفکیک سرویس های داخلی و عمومی'], 'accent' => 'from-[#334155] to-[#0F172A]'],
                ] as $solution)
                    <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-200/80">
                        <div class="bg-gradient-to-l p-6 text-white {{ $solution['accent'] }}">
                            <p class="text-sm font-black text-white/80">مناسب برای {{ $solution['fit'] }}</p>
                            <h3 class="mt-3 text-2xl font-black">{{ $solution['name'] }}</h3>
                            <p class="mt-4 text-sm leading-8 text-white/90">{{ $solution['summary'] }}</p>
                        </div>
                        <div class="p-6">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-black text-slate-500">پشته پیشنهادی</p>
                                <p class="mt-2 text-sm font-bold leading-7 text-slate-700">{{ $solution['stack'] }}</p>
                            </div>
                            <div class="mt-5 space-y-3 text-sm font-bold text-slate-700">
                                @foreach ($solution['items'] as $item)
                                    <div class="flex items-start gap-2">
                                        <svg class="mt-0.5 size-5 shrink-0 text-[#0069FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span>{{ $item }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <a href="{{ route('contact') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-black text-[#0069FF] transition hover:text-[#0050D0]">
                                دریافت طرح پیشنهادی
                                <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-4 pb-20 md:px-8 lg:px-10">
        <div class="mx-auto max-w-7xl rounded-3xl bg-[#0F172A] p-8 text-white shadow-2xl shadow-slate-900/15 md:p-10">
            <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-black text-blue-200">نیاز خاص دارید؟</p>
                    <h2 class="mt-3 text-3xl font-black leading-tight">می توانیم برای هر سرویس، ترکیب منابع و معماری مناسب پیشنهاد دهیم.</h2>
                    <p class="mt-4 text-sm leading-8 text-slate-300">از انتخاب region و topology شبکه تا backup policy و migration plan، این صفحه مسیر گفتگو با مشتری را از همین حالا واقعی می کند.</p>
                </div>
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-4 text-sm font-black text-slate-950 transition hover:bg-slate-100">شروع گفتگو</a>
            </div>
        </div>
    </section>
@endsection
