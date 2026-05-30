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
    <section id="top" class="relative isolate overflow-hidden bg-[#06162E] px-4 pb-16 pt-12 text-white md:px-8 md:pb-24 md:pt-20 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full bg-[radial-gradient(circle_at_top_right,rgba(0,128,255,0.28),transparent_34%),linear-gradient(180deg,#071B3A_0%,#06162E_72%)]"></div>
        <div aria-hidden="true" class="absolute left-[-7rem] top-16 -z-10 h-72 w-72 rounded-full bg-[#0080FF]/20 blur-3xl"></div>
        <div aria-hidden="true" class="absolute right-[-6rem] top-28 -z-10 h-60 w-60 rounded-full bg-sky-300/10 blur-3xl"></div>
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-full opacity-[0.16] [background-image:radial-gradient(#93c5fd_1px,transparent_1px)] [background-size:28px_28px]"></div>

        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[minmax(430px,0.92fr)_minmax(0,1.08fr)] lg:items-center">
            <div class="order-2 hidden lg:block">
                <div class="relative mx-auto max-w-[720px]">
                    <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-[#0069FF]/10 blur-2xl"></div>
                    <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#F8FAFC] shadow-2xl shadow-slate-950/30">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-1">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full bg-[#ff5f57] ring-1 ring-black/5"></span>
                                <span class="size-3 rounded-full bg-[#febc2e] ring-1 ring-black/5"></span>
                                <span class="size-3 rounded-full bg-[#28c840] ring-1 ring-black/5"></span>
                            </div>
                            <div class="flex min-w-0 flex-1 justify-center">
                                <div class="max-w-xs truncate rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-center text-xs font-bold text-slate-500" dir="ltr">
                                    aviato.ir/servers/create
                                </div>
                            </div>
                            <div class="h-4 w-14"></div>
                        </div>

                        <div class="p-4">
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-black uppercase text-[#0069FF]">ساخت VPS جدید</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">سیستم عامل، پلن و دسترسی اولیه را انتخاب کنید</p>
                                </div>
                                <span class="rounded-lg border border-[#B8D6FF] bg-[#F2F8FF] px-3 py-2 text-xs font-black text-[#0069FF]">کیف پول آماده</span>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_210px]">
                                <div class="space-y-4">
                                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                                        <div class="border-b border-slate-100 px-4 py-1">
                                            <h2 class="mt-1 text-sm font-black text-slate-950">۱- سیستم عامل را انتخاب کنید</h2>
                                        </div>
                                        <div class="grid gap-2 p-4 md:grid-cols-3">
                                            @foreach ([['assets/images/distro/ubuntu.png', 'Ubuntu', '۳ نسخه آماده', 'bg-orange-100 text-orange-700 border-orange-200'], ['assets/images/distro/debian.png', 'Debian', '۲ نسخه آماده', 'bg-red-100 text-red-700 border-red-200']] as $family)
                                                <div class="flex items-center gap-1.5 rounded-lg border p-3 text-right {{ $loop->first ? 'border-[#0069FF] bg-[#F2F8FF] ring-4 ring-[#0069FF]/10' : 'border-slate-200 bg-white' }}">
                                                    <span class="grid size-9 rounded-full shrink-0 place-items-center  border text-xs font-black {{ $family[3] }}">
                                                        <img src="{{ asset($family[0]) }}" class="size-9" alt="Ubuntu Logo">
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-black text-slate-950">{{ $family[1] }}</span>
                                                        {{-- <span class="mt-1 block text-[11px] font-bold text-slate-500">{{ $family[2] }}</span> --}}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
          
                                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                                        <div class="border-b border-slate-100 px-4 py-1">
                                            <h2 class="mt-1 text-sm font-black text-slate-950">۲- نسخه و پلن VPS</h2>
                                        </div>
                                        <div class="grid gap-3 p-4 md:grid-cols-[1fr_1.15fr]">
                                            <div class="flex items-center gap-3 rounded-lg bg-[#F2F8FF] p-3">
                                                {{-- <span class="size-4 rounded-full border-4 border-[#0069FF] bg-white"></span> --}}
                                                <img src="{{ asset("assets/images/distro/ubuntu.png") }}" class="size-9" alt="Ubuntu Logo">
                                                {{-- <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-orange-100 text-xs font-black text-orange-700">U</span> --}}
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-black text-slate-950">Ubuntu 22.04 LTS</span>
                                                    {{-- <span class="mt-1 block text-[11px] font-bold text-slate-500">Cloud-init ready</span> --}}
                                                </span>
                                            </div>
                                            <div class="relative rounded-xl border border-[#0069FF] bg-[#F2F8FF] p-3 text-right ring-4 ring-[#0069FF]/10">
                                                <span class="absolute left-3 top-3 rounded-md bg-[#0069FF] px-2 py-1 text-[10px] font-black text-white">پیشنهادی</span>
                                                <span class="block text-sm font-black text-slate-950">{{ $heroBundle?->name ?? 'پلن پیشنهادی' }}</span>
                                                {{-- <span class="mt-2 block text-xs leading-6 text-slate-500">{{ $heroBundle?->description ?: 'مناسب سایت و فروشگاه فعال' }}</span> --}}
                                                <span class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px]">
                                                    <span class="rounded-lg bg-white p-1 ring-1 ring-slate-200 text-black"><b>{{ $heroBundle?->cpu_cores ?? 4 }}</b><br>CPU</span>
                                                    <span class="rounded-lg bg-white p-1 ring-1 ring-slate-200 text-black"><b>{{ $heroBundle?->ram_gb ?? 8 }}</b><br>RAM</span>
                                                    <span class="rounded-lg bg-white p-1 ring-1 ring-slate-200 text-black"><b>{{ $heroBundle?->disk_gb ?? 80 }}</b><br>Disk</span>
                                                </span>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                                        <div class="border-b border-slate-100 px-4 py-1">
                                            <h2 class="mt-1 text-sm font-black text-slate-950">۳- دسترسی اولیه</h2>
                                        </div>
                                        <div class="grid gap-3 px-4 pt-2 pb-4 md:grid-cols-2">
                                            <div>
                                                <span class="text-xs font-black text-slate-700">نام VPS</span>
                                                <div class="mt-2 rounded-lg border border-slate-200 px-3 py-2.5 text-left text-xs font-bold text-slate-500" dir="ltr">web-01</div>
                                            </div>
                                            <div>
                                                <span class="text-xs font-black text-slate-700">Username</span>
                                                <div class="mt-2 rounded-lg border border-slate-200 px-3 py-2.5 text-left text-xs font-bold text-slate-500" dir="ltr">ubuntu</div>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <aside class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                                    <h2 class="text-base font-black text-slate-950">خلاصه ساخت</h2>
                                    <div class="mt-4 space-y-3 text-xs">
                                        <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">سیستم عامل</span><span class="font-black text-slate-950">Ubuntu</span></div>
                                        <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">پلن</span><span class="font-black text-slate-950">{{ $heroBundle?->name ?? '—' }}</span></div>
                                        <div class="flex justify-between gap-3"><span class="font-bold text-slate-500">منابع</span><span class="font-black text-slate-950" dir="ltr">{{ $heroBundle?->cpu_cores ?? 4 }} CPU / {{ $heroBundle?->ram_gb ?? 8 }}GB</span></div>
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-[11px] font-black text-slate-500">هزینه ماهانه تقریبی</p>
                                            <p class="mt-2 text-lg font-black text-slate-950">{{ $heroBundle ? $wallets->format($heroBundle->monthly_price) : '—' }}</p>
                                        </div>
                                        <div class="rounded-lg border border-dashed border-slate-300 p-3 text-[11px] leading-5 text-slate-500">IP به صورت خودکار تخصیص داده می شود.</div>
                                    </div>
                                    <a href="{{ route('customer.register') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-[#0069FF] px-4 py-3 text-xs font-black text-white transition hover:bg-[#0050D0]">ساخت VPS</a>
                                </aside>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-1">
                <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-normal text-white md:text-6xl">
                    سرور مجازی سریع،
                    <span class="block text-[#8FC7FF]">با قیمت مشخص</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-9 text-slate-300 md:text-lg">
                    VPS آویاتو برای راه اندازی سایت، فروشگاه و اپلیکیشن است. پلن ها روشن هستند، قیمت را قبل از خرید می بینید و برای شروع کار پشتیبانی فارسی دارید.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-7 py-4 text-base font-black text-white shadow-xl shadow-[#0069FF]/25 transition hover:bg-[#0050D0]">
                        خرید سرور مجازی
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-white/20 px-7 py-4 text-base font-black text-white shadow-sm transition hover:bg-white/10">
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
