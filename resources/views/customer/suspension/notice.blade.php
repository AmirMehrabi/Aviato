@extends('customer.layout')

@section('title', 'وضعیت کیف پول')

@section('header_title', 'وضعیت کیف پول')
@section('header_subtitle', 'جزئیات موجودی و دسترسی به عملیات پرداختی')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-6">
            <div class="rounded-2xl border {{ $effectiveBalance < 0 ? 'border-red-200 bg-red-50 shadow-red-200/50' : 'border-amber-200 bg-amber-50 shadow-amber-200/50' }} p-6 shadow-sm sm:p-8">
                <div class="flex items-center gap-4">
                    <div class="grid size-14 shrink-0 place-items-center rounded-2xl {{ $effectiveBalance < 0 ? 'bg-red-100 text-red-600 ring-red-200' : 'bg-amber-100 text-amber-600 ring-amber-200' }} ring-1">
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path d="M12 8v5" stroke-linecap="round"/>
                            <path d="M12 17h.01" stroke-linecap="round"/>
                            <path d="M10.3 3.9h3.4L22 17.8A2 2 0 0 1 20.3 21H3.7A2 2 0 0 1 2 17.8L10.3 3.9Z" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-black tracking-[0.25em] {{ $effectiveBalance < 0 ? 'text-red-600' : 'text-amber-600' }}">عملیات پرداختی متوقف است</p>
                        <h2 class="mt-1 text-2xl font-black {{ $effectiveBalance < 0 ? 'text-red-900' : 'text-amber-900' }} sm:text-3xl">{{ $effectiveBalance < 0 ? 'موجودی مؤثر کیف پول منفی است' : 'کیف پول شما خالی است' }}</h2>
                    </div>
                </div>

                <p class="mt-5 text-sm leading-8 {{ $effectiveBalance < 0 ? 'text-red-800' : 'text-amber-800' }}">
                    مشاهده داشبورد، سرویس‌ها، صورتحساب‌ها و مدیریت حساب همچنان در دسترس است.
                    تا زمان شارژ کیف پول، فقط عملیات ایجادکننده هزینه مانند ساخت یا ارتقای سرویس و ثبت بکاپ جدید انجام نمی‌شود.
                </p>

                @if (session('error'))
                    <div class="mt-5 rounded-xl border border-red-200 bg-white px-4 py-3 text-sm font-bold text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex items-center justify-center rounded-xl {{ $effectiveBalance < 0 ? 'bg-red-600 shadow-red-600/20 hover:bg-red-500' : 'bg-amber-500 shadow-amber-500/20 hover:bg-amber-400' }} px-5 py-3 text-sm font-black text-white shadow-lg transition">
                        رفتن به کیف پول
                    </a>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="inline-flex items-center justify-center rounded-xl border {{ $effectiveBalance < 0 ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-amber-200 text-amber-700 hover:bg-amber-50' }} bg-white px-5 py-3 text-sm font-black transition">
                        شارژ سریع
                    </a>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['label' => 'موجودی کیف پول', 'value' => $wallets->format($wallet->balance), 'color' => ($wallet->balance ?? 0) < 0 ? 'text-red-600' : 'text-slate-950'],
                    ['label' => 'پروژه فعال', 'value' => $activeProject->name, 'color' => 'text-slate-950'],
                    ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage), 'color' => 'text-amber-600'],
                ] as $item)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                        <p class="text-xs font-black text-slate-400">{{ $item['label'] }}</p>
                        <p class="mt-2 truncate text-lg font-black {{ $item['color'] }}">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black tracking-[0.2em] text-amber-600">چه چیزهایی در دسترس است</p>
                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600">
                    <p>کیف پول را شارژ کنید تا محدودیت برداشته شود.</p>
                    <p>فضاهای کاری، سرویس‌ها، صورتحساب‌ها و تراکنش‌های مالی همچنان در دسترس هستند.</p>
                    <p>عملیات ایجادکننده هزینه، مانند ساخت و ارتقای سرویس یا ثبت بکاپ جدید، تا زمان شارژ کیف پول انجام نمی‌شود.</p>
                    <p>عملیات کاهش هزینه، مانند حذف سرویس یا غیرفعال کردن بکاپ خودکار، همچنان قابل انجام است.</p>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm shadow-amber-200/40">
                <h2 class="text-base font-black text-amber-900">برای فعال شدن عملیات پرداختی چه کار کنم؟</h2>
                <ol class="mt-4 space-y-3 text-sm leading-7 text-amber-800">
                    <li>۱. کیف پول را شارژ کنید.</li>
                    <li>۲. بعد از ثبت موفق شارژ، محدودیت به‌صورت خودکار برداشته می‌شود.</li>
                    <li>۳. ماشین‌های مجازی متوقف‌شده را دوباره می‌توانید روشن کنید.</li>
                </ol>
            </div>
        </aside>
    </div>
@endsection
