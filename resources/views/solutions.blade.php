@extends('layouts.marketing')

@section('title', 'آویاتو: راهکارهای قابل اعتماد برای ماشین مجازی')
@section('description', 'انتخاب مسیر مناسب برای میزبانی سایت، فروشگاه، اپلیکیشن، دیتابیس و محیط تست با منابع شفاف، تحویل قابل پیگیری و پشتیبانی فارسی آویاتو.')

@php
    $activePage = 'solutions';
    $solutionBundles = ($bundles ?? collect())->values();
    $starterBundle = $solutionBundles->first();
    $growthBundle = $solutionBundles->get(1) ?? $starterBundle;
    $scaleBundle = $solutionBundles->get(2) ?? $growthBundle;

    $currentCustomers = [
        ['name' => 'مبیت', 'url' => 'https://mobit.ir', 'logo' => 'assets/images/customers/mobit-logo.svg'],
        ['name' => 'حسابرو', 'url' => 'https://hesabro.ir', 'logo' => 'assets/images/customers/hesabro-logo.png'],
        ['name' => 'کارخانه نوآوری کرمان', 'url' => 'https://kermanif.ir', 'logo' => 'assets/images/customers/kermanif-logo.png'],
    ];

    $trustSignals = [
        ['title' => 'منابع و قیمت روشن', 'body' => 'قبل از پرداخت، CPU، RAM، دیسک، IP و هزینه ماهانه را می بینید.'],
        ['title' => 'تحویل قابل پیگیری', 'body' => 'بعد از ثبت سفارش، وضعیت ساخت و اطلاعات اتصال از داخل پنل مشتری دنبال می شود.'],
        ['title' => 'پشتیبانی فارسی', 'body' => 'برای انتخاب پلن، شروع کار و سوال های رایج فنی می توانید با تیم آویاتو صحبت کنید.'],
        ['title' => 'مسیر رشد ساده', 'body' => 'از یک پلن منطقی شروع می کنید و هنگام رشد پروژه، مسیر ارتقا یا جداسازی سرویس ها روشن است.'],
    ];

    $solutions = [
        [
            'name' => 'سایت، وردپرس و فروشگاه',
            'summary' => 'برای پروژه هایی که درآمد، اعتبار برند یا ارتباط با مشتری به در دسترس بودن سایت وابسته است.',
            'bundle' => $growthBundle,
            'best' => 'وب سایت، فروشگاه، پنل مشتریان',
            'setup' => 'یک ماشین مجازی با IP اختصاصی، دیسک NVMe و منابع کافی برای وب سرور، دیتابیس و کش سبک.',
            'safe' => 'هزینه و منابع قبل از سفارش مشخص است و اطلاعات اتصال داخل پنل مشتری تحویل می شود.',
            'control' => 'نصب وردپرس، وب سرور، افزونه ها، SSL و نگهداری نرم افزارهای داخل سرور با شماست.',
            'upgrade' => 'وقتی فروشگاه پربازدیدتر شد یا دیتابیس سنگین شد، دیتابیس یا پردازش ها را جدا کنید.',
        ],
        [
            'name' => 'اپلیکیشن و API',
            'summary' => 'برای تیم هایی که یک سرویس آنلاین، API یا پنل مدیریتی دارند و منابع مشخص می خواهند.',
            'bundle' => $growthBundle,
            'best' => 'اپلیکیشن، API، پنل مدیریتی',
            'setup' => 'ماشین مجازی آماده برای Docker، پردازش پس زمینه، دیتابیس سبک و اتصال امن با IP اختصاصی.',
            'safe' => 'شروع از یک پلن قابل فهم باعث می شود هزینه اولیه کنترل شود و بعدا بر اساس مصرف واقعی تصمیم بگیرید.',
            'control' => 'کد، دیتابیس، صف، تنظیمات امنیتی اپلیکیشن و به روزرسانی سرویس های داخل سرور با تیم شماست.',
            'upgrade' => 'وقتی ترافیک یا پردازش زیاد شد، اپلیکیشن، دیتابیس و صف را روی منابع جداگانه پخش کنید.',
        ],
        [
            'name' => 'دیتابیس و پردازش',
            'summary' => 'برای پروژه هایی که سرعت پاسخ دیتابیس، حافظه و کارهای پس زمینه روی کیفیت سرویس اثر مستقیم دارد.',
            'bundle' => $scaleBundle,
            'best' => 'دیتابیس، صف، کش، پردازش روزانه',
            'setup' => 'پلن قوی تر با دیسک NVMe، RAM بیشتر و امکان استفاده از بکاپ و فایروال برای کنترل ریسک.',
            'safe' => 'منابع جدا برای دیتابیس یا پردازش، فشار را از سایت اصلی کم می کند و عیب یابی را ساده تر می کند.',
            'control' => 'طراحی دیتابیس، بهینه سازی کوئری ها، زمان بندی پردازش ها و سیاست نگهداری داده با شماست.',
            'upgrade' => 'وقتی بار پردازشی پایدار و سنگین شد، معماری را چندسروری و نقش ها را جدا کنید.',
        ],
        [
            'name' => 'محیط توسعه و تست',
            'summary' => 'برای زمانی که نمی خواهید تغییرات، نسخه جدید یا ابزار تازه را مستقیم روی سرویس اصلی امتحان کنید.',
            'bundle' => $starterBundle,
            'best' => 'تست نسخه جدید، نمونه سازی، تمرین',
            'setup' => 'یک پلن سبک با دسترسی مدیریتی برای نصب ابزارها، اجرای تست و ساخت محیط موقت.',
            'safe' => 'هزینه قابل کنترل است و محیط تست از سرویس اصلی جدا می ماند.',
            'control' => 'داده تست، نصب ابزارها، حذف محیط موقت و هماهنگی نسخه ها با شماست.',
            'upgrade' => 'اگر محیط تست به بخشی ثابت از فرایند تیم تبدیل شد، منابع آن را پایدارتر انتخاب کنید.',
        ],
    ];

    $clarityPoints = [
        ['title' => 'قبل از پرداخت', 'body' => 'منابع، تعداد IP، قیمت ماهانه و مسیر ثبت سفارش مشخص است.'],
        ['title' => 'بعد از سفارش', 'body' => 'ساخت ماشین مجازی شروع می شود و اطلاعات اتصال از پنل مشتری پیگیری می شود.'],
        ['title' => 'داخل سرور', 'body' => 'دسترسی مدیریتی دارید و نرم افزارهای مورد نیاز پروژه را خودتان نصب و نگهداری می کنید.'],
        ['title' => 'پشتیبانی', 'body' => 'برای انتخاب پلن، شروع کار، سوال های رایج و مسیر رشد می توانید راهنمایی بگیرید.'],
    ];

    $faqs = [
        ['q' => 'از کدام راهکار باید شروع کنم؟', 'a' => 'اگر پروژه شما سایت یا فروشگاه است، معمولا راهکار وب و فروشگاه نقطه شروع بهتری است. برای اپلیکیشن یا API، راهکار اپلیکیشن مناسب تر است. اگر فقط تست می کنید، از محیط توسعه شروع کنید.'],
        ['q' => 'آیا این صفحه جای مشاوره فنی را می گیرد؟', 'a' => 'نه. این صفحه برای انتخاب اولیه است. اگر معماری خاص، دیتابیس سنگین یا مهاجرت از سرور قبلی دارید، بهتر است قبل از خرید با پشتیبانی صحبت کنید.'],
        ['q' => 'چه چیزهایی بر عهده آویاتو است؟', 'a' => 'آویاتو ماشین مجازی، منابع انتخاب شده، IP و امکانات زیرساختی قابل ارائه مثل بکاپ و فایروال را فراهم می کند.'],
        ['q' => 'چه چیزهایی بر عهده مشتری است؟', 'a' => 'نصب نرم افزارها، به روزرسانی سیستم عامل، امنیت اپلیکیشن، تنظیمات داخل سرور و نگهداری کد یا دیتابیس پروژه بر عهده مشتری است، مگر اینکه سرویس مدیریتی جداگانه توافق شده باشد.'],
        ['q' => 'اگر بعدا منابع بیشتری لازم شد چه کنم؟', 'a' => 'لازم نیست از ابتدا بزرگ ترین پلن را انتخاب کنید. می توانید با یک نقطه شروع منطقی آغاز کنید و بعد از مشاهده مصرف واقعی، برای ارتقا یا جداسازی سرویس ها تصمیم بگیرید.'],
    ];
@endphp

@section('body_class', 'bg-[#F5F8FD]')

@section('content')
    <section class="relative isolate overflow-hidden bg-white px-4 pb-14 pt-28 md:px-8 md:pb-18 md:pt-32 lg:px-10">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-20 h-[34rem] bg-[linear-gradient(180deg,#EEF5FF_0%,#FFFFFF_76%)]"></div>
        <div aria-hidden="true" class="absolute right-1/2 top-24 -z-10 h-72 w-[42rem] translate-x-1/2 rounded-full bg-[#B9D6FF]/35 blur-3xl"></div>

        <div class="mx-auto max-w-7xl">
            <div class="relative min-h-[34rem] overflow-hidden rounded-[2.25rem] border border-slate-200 bg-white shadow-2xl shadow-slate-200/60">
                <div aria-hidden="true" class="absolute inset-y-0 left-0 hidden w-[42%] bg-cover bg-center md:block" style="background-image: url('{{ asset('assets/images/hero-section.webp') }}');"></div>
                <div aria-hidden="true" class="absolute inset-y-0 left-[35%] hidden w-48 bg-gradient-to-r from-transparent to-white md:block"></div>
                <div aria-hidden="true" class="absolute bottom-0 right-0 h-44 w-44 translate-x-14 translate-y-14 rounded-full border-[2.5rem] border-[#EEF5FF]"></div>

                <div class="relative flex min-h-[34rem] flex-col justify-between px-5 py-7 md:px-8 md:py-9 lg:px-12 lg:py-12">
                    <div class="max-w-3xl">
                        <h1 class="text-4xl font-medium leading-[1.18] tracking-[-0.03em] text-slate-950 sm:text-5xl md:text-6xl">
                            برای پروژه واقعی،
                            <span class="block text-slate-700">سرور را با حدس انتخاب نکنید.</span>
                        </h1>
                        <p class="mt-7 max-w-2xl text-base leading-8 text-slate-600 md:text-lg md:leading-9">
                            راهکارهای آویاتو مسیر شروع را روشن می کنند: چه منابعی لازم دارید، سفارش چطور تحویل می شود، کدام بخش ها با شماست و چه زمانی باید برای رشد آماده شوید.
                        </p>
                        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                                مشاوره انتخاب پلن
                            </a>
                            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-7 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-[#B8D6FF] hover:bg-[#F7FBFF] hover:text-[#0069FF]">
                                مقایسه پلن ها
                            </a>
                        </div>
                    </div>

                    <div class="mt-14 flex max-w-5xl flex-col gap-5 border-t border-slate-200 pt-6 md:flex-row md:items-end md:justify-between">
                        <div class="max-w-xl">
                            <p class="text-sm font-bold text-slate-950">قبل از خرید، ابهام های اصلی باید حذف شوند.</p>
                            <p class="mt-2 text-sm leading-7 text-slate-600">منابع، هزینه ماهانه، مسیر تحویل و مرز مسئولیت ها باید قبل از سفارش قابل فهم باشد.</p>
                        </div>
                        <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm font-bold text-slate-700">
                            <span class="inline-flex items-center gap-2">
                                <span class="size-2 rounded-full bg-[#0069FF]"></span>
                                منابع روشن
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="size-2 rounded-full bg-[#0069FF]"></span>
                                تحویل قابل پیگیری
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="size-2 rounded-full bg-[#0069FF]"></span>
                                پشتیبانی فارسی
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="size-2 rounded-full bg-[#0069FF]"></span>
                                مسیر رشد مشخص
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-16 md:px-8 md:py-20 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:items-center">
            <div class="text-center lg:text-right">
                <h2 class="text-3xl leading-tight text-slate-950 md:text-4xl">زیرساخت برای کارهای واقعی.</h2>
                <p class="mt-5 leading-8 text-slate-600">
                    آویاتو میزبان پروژه هایی است که برای فروش، پشتیبانی، توسعه و کار روزانه به ماشین مجازی پایدار نیاز دارند.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($currentCustomers as $customer)
                    <a href="{{ $customer['url'] }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-40 flex-col items-center justify-center rounded-2xl border border-slate-200 bg-[#FBFDFF] p-6 text-center transition hover:-translate-y-1 hover:border-[#B9D6FF] hover:bg-white hover:shadow-xl hover:shadow-slate-200/60">
                        <span class="flex h-16 w-full items-center justify-center">
                            <img src="{{ asset($customer['logo']) }}" alt="{{ $customer['name'] }}" class="max-h-14 max-w-44 object-contain">
                        </span>
                        <span class="mt-5 text-lg font-bold text-slate-950">{{ $customer['name'] }}</span>
                        <span class="mt-2 text-sm font-bold text-slate-500" dir="ltr">{{ parse_url($customer['url'], PHP_URL_HOST) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F5F8FD] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <h2 class="text-3xl leading-tight text-slate-950 md:text-4xl">راهکار را بر اساس ریسک پروژه انتخاب کنید، نه فقط عدد منابع.</h2>
                <p class="mt-5 leading-8 text-slate-600">
                    هر کارت یک نقطه شروع پیشنهادی است: چه چیزی می سازید، چرا این انتخاب امن تر است، کدام بخش ها با شماست و چه زمانی باید مسیر رشد را بررسی کنید.
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2">
                @foreach ($solutions as $solution)
                    <article class="group overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/50 transition hover:-translate-y-1 hover:border-[#B9D6FF] hover:shadow-xl hover:shadow-slate-200/70">
                        <div class="grid gap-0 xl:grid-cols-[1fr_18rem]">
                            <div class="p-6 md:p-7">
                                <p class="text-sm font-bold text-[#2C67C9]">{{ $solution['best'] }}</p>
                                <h3 class="mt-3 text-2xl leading-tight text-slate-950 md:text-3xl">{{ $solution['name'] }}</h3>
                                <p class="mt-4 text-sm leading-8 text-slate-600">{{ $solution['summary'] }}</p>

                                <div class="mt-6 grid gap-3">
                                    <div class="rounded-2xl bg-[#F7FBFF] p-4">
                                        <p class="text-xs font-bold text-slate-500">چیدمان پیشنهادی</p>
                                        <p class="mt-2 text-sm font-bold leading-7 text-slate-700">{{ $solution['setup'] }}</p>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <p class="text-xs font-bold text-[#2C67C9]">چرا امن تر است؟</p>
                                            <p class="mt-2 text-sm leading-7 text-slate-600">{{ $solution['safe'] }}</p>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 p-4">
                                            <p class="text-xs font-bold text-[#2C67C9]">چه چیزی با شماست؟</p>
                                            <p class="mt-2 text-sm leading-7 text-slate-600">{{ $solution['control'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <aside class="flex flex-col justify-between bg-[#07172D] p-6 text-white">
                                <div>
                                    <p class="text-xs font-bold text-[#8FC7FF]">نقطه شروع پیشنهادی</p>
                                    <h4 class="mt-3 text-2xl leading-tight">{{ $solution['bundle']?->name ?? 'بعد از انتشار پلن' }}</h4>
                                    @if ($solution['bundle'])
                                        <p class="mt-4 text-2xl font-bold">{{ $wallets->format($solution['bundle']->monthly_price) }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-400">ماهانه</p>
                                        <div class="mt-6 grid grid-cols-2 gap-2 text-center text-xs font-bold">
                                            <span class="rounded-xl bg-white/10 p-3">{{ $solution['bundle']->cpu_cores }} vCPU</span>
                                            <span class="rounded-xl bg-white/10 p-3">{{ $solution['bundle']->ram_gb }}GB RAM</span>
                                            <span class="rounded-xl bg-white/10 p-3">{{ $solution['bundle']->disk_gb }}GB NVMe</span>
                                            <span class="rounded-xl bg-white/10 p-3">{{ $solution['bundle']->ip_count }} IP</span>
                                        </div>
                                    @else
                                        <p class="mt-4 text-sm leading-7 text-slate-300">بعد از فعال شدن پلن ها در پنل مدیریت، پیشنهاد این بخش نمایش داده می شود.</p>
                                    @endif
                                </div>
                                <div class="mt-7 rounded-2xl border border-white/10 bg-white/[0.07] p-4">
                                    <p class="text-xs font-bold text-[#8FC7FF]">زمان بررسی رشد</p>
                                    <p class="mt-2 text-sm leading-7 text-slate-300">{{ $solution['upgrade'] }}</p>
                                </div>
                            </aside>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.82fr_1.18fr] lg:items-start">
            <div>
                <h2 class="text-3xl leading-tight text-slate-950 md:text-4xl">اعتماد از شفافیت شروع می شود.</h2>
                <p class="mt-5 leading-8 text-slate-600">
                    صفحه راهکارها نباید فقط شما را به خرید نزدیک کند؛ باید قبل از پرداخت مرز مسئولیت، مسیر تحویل و هزینه را روشن کند.
                </p>
                <div class="mt-8 rounded-[1.75rem] bg-[#07172D] p-6 text-white">
                    <p class="text-sm font-bold text-[#8FC7FF]">پیشنهاد عملی</p>
                    <p class="mt-3 text-sm leading-8 text-slate-300">
                        اگر پروژه فعلی شما برای فروش، کاربر یا داده واقعی استفاده می شود، قبل از خرید درباره چیدمان مناسب و مسیر رشد با پشتیبانی صحبت کنید.
                    </p>
                    <a href="{{ route('contact') }}" class="mt-5 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#07172D] transition hover:bg-blue-50">
                        صحبت با پشتیبانی
                    </a>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($clarityPoints as $point)
                    <article class="rounded-[1.75rem] border border-slate-200 bg-[#FBFDFF] p-6 shadow-sm shadow-slate-200/40">
                        <div class="mb-5 grid size-10 place-items-center rounded-2xl bg-[#EEF5FF] text-[#2C67C9]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <h3 class="text-xl text-slate-950">{{ $point['title'] }}</h3>
                        <p class="mt-4 text-sm leading-8 text-slate-600">{{ $point['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#F5F8FD] px-4 py-20 md:px-8 md:py-24 lg:px-10">
        <div class="mx-auto max-w-5xl">
            <div class="text-center">
                <h2 class="text-3xl leading-tight text-slate-950 md:text-4xl">سوال های مهم قبل از انتخاب راهکار</h2>
                <p class="mx-auto mt-5 max-w-2xl leading-8 text-slate-600">
                    اگر پاسخ این سوال ها برای پروژه شما کافی نیست، مسیر درست این است که قبل از خرید مشاوره بگیرید.
                </p>
            </div>

            <div class="mt-10 divide-y divide-slate-100 rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/50">
                @foreach ($faqs as $faq)
                    <details class="group p-5 open:bg-white md:p-6" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-right text-lg text-slate-950">
                            {{ $faq['q'] }}
                            <span class="grid size-8 shrink-0 place-items-center rounded-2xl bg-[#EEF5FF] text-[#2C67C9] transition group-open:rotate-45">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                                </svg>
                            </span>
                        </summary>
                        <p class="mt-4 max-w-3xl text-sm leading-8 text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-[#07172D] px-4 py-16 text-white md:px-8 md:py-20 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <h2 class="max-w-4xl text-3xl leading-tight md:text-4xl">اگر پروژه شما واقعی است، انتخاب پلن را به حدس تبدیل نکنید.</h2>
                <p class="mt-4 max-w-3xl leading-8 text-slate-300">
                    برای جدا کردن سایت و دیتابیس، انتخاب منابع، مهاجرت از سرور قبلی یا شروع امن تر، قبل از خرید با ما صحبت کنید.
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-7 py-4 text-sm font-bold text-[#07172D] transition hover:bg-blue-50">
                    مشاوره قبل از خرید
                </a>
                <a href="{{ route('customer.register') }}" class="inline-flex items-center justify-center rounded-xl border border-white/15 px-7 py-4 text-sm font-bold text-white transition hover:bg-white/10">
                    شروع خرید
                </a>
            </div>
        </div>
    </section>
@endsection
