<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>حساب تعلیق شده</title>
    <link rel="icon" href="{{ asset('favicons/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">
    <meta name="theme-color" content="#B91C1C">
    <link rel="stylesheet" href="{{ asset('assets/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0F172A] text-white">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(239,68,68,.22),transparent_28%),radial-gradient(circle_at_bottom_left,rgba(245,158,11,.18),transparent_22%),linear-gradient(180deg,#0F172A_0%,#111827_55%,#1F2937_100%)]"></div>
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-red-400 via-amber-300 to-red-500"></div>

        <main class="relative mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="grid w-full gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-[28px] border border-white/10 bg-white/6 p-6 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-8">
                    <div class="flex items-center gap-3">
                        <div class="grid size-14 place-items-center rounded-2xl bg-red-500/15 text-red-200 ring-1 ring-red-400/25">
                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path d="M12 8v5" stroke-linecap="round"/>
                                <path d="M12 17h.01" stroke-linecap="round"/>
                                <path d="M10.3 3.9h3.4L22 17.8A2 2 0 0 1 20.3 21H3.7A2 2 0 0 1 2 17.8L10.3 3.9Z" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-black tracking-[0.25em] text-red-300">ACCOUNT SUSPENDED</p>
                            <h1 class="mt-1 text-3xl font-black text-white sm:text-4xl">حساب شما تعلیق شده است</h1>
                        </div>
                    </div>

                    <p class="mt-5 text-base leading-8 text-slate-200">
                        به دلیل موجودی منفی و ارسال چند نوبت هشدار، دسترسی این حساب موقتا متوقف شده است.
                        فعلا فقط می‌توانید کیف پول را شارژ کنید و صورتحساب‌ها و تراکنش‌های مالی را ببینید.
                    </p>

                    @if (session('error'))
                        <div class="mt-5 rounded-2xl border border-red-400/25 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-100">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex items-center justify-center rounded-2xl bg-red-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-red-500/25 transition hover:bg-red-400">
                            رفتن به کیف پول
                        </a>
                        <form method="POST" action="{{ route('customer.logout', [], false) }}">
                            @csrf
                            <button class="inline-flex w-full items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-black text-white transition hover:bg-white/10">
                                خروج از حساب
                            </button>
                        </form>
                    </div>

                    <div class="mt-8 grid gap-3 md:grid-cols-3">
                        @foreach ([
                            ['label' => 'موجودی کیف پول', 'value' => $wallets->format($wallet->balance)],
                            ['label' => 'پروژه فعال', 'value' => $activeProject->name],
                            ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage)],
                        ] as $item)
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs font-black text-slate-400">{{ $item['label'] }}</p>
                                <p class="mt-2 truncate text-lg font-black text-white">{{ $item['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-[28px] border border-white/10 bg-slate-950/45 p-6 shadow-2xl shadow-black/20 backdrop-blur-xl">
                        <p class="text-xs font-black tracking-[0.2em] text-amber-200">WHAT IS AVAILABLE</p>
                        <div class="mt-4 space-y-3 text-sm leading-7 text-slate-200">
                            <p>کیف پول را شارژ کنید تا بدهی تسویه شود.</p>
                            <p>صورتحساب‌ها و تراکنش‌های مالی همچنان در دسترس هستند.</p>
                            <p>ساخت، روشن کردن و مدیریت ماشین‌های مجازی تا رفع تعلیق غیرفعال است.</p>
                        </div>
                    </div>

                    <div class="rounded-[28px] border border-amber-400/20 bg-amber-400/10 p-6 text-amber-50 shadow-2xl shadow-black/10 backdrop-blur-xl">
                        <h2 class="text-lg font-black">برای رفع تعلیق چه کار کنم؟</h2>
                        <ol class="mt-4 space-y-3 text-sm leading-7">
                            <li>1. کیف پول را شارژ کنید.</li>
                            <li>2. اگر حساب هنوز تعلیق بود، با پشتیبانی تماس بگیرید.</li>
                            <li>3. بعد از فعال‌سازی مجدد، دسترسی کامل برمی‌گردد.</li>
                        </ol>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
