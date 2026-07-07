@extends('customer.layout')

@section('title', 'داشبورد مشتری')
@section('header_title', 'داشبورد ماشین های مجازی')
@section('header_subtitle', 'ساخت سریع ماشین ابری، کنترل هزینه و مدیریت سرورها')
@section('breadcrumbs')
    <span class="truncate text-slate-700">داشبورد</span>
@endsection

@php
    $activeNav = 'dashboard';
    $searchRows = [];

    if ($canManageVms) {
        $searchRows[] = [
            'title' => 'ساخت ماشین',
            'description' => 'انتخاب پلن ماشین مجازی و شروع مسیر ساخت',
            'type' => 'عملیات',
            'url' => route('customer.servers.create', [], false),
            'keywords' => 'ساخت ماشین vps server',
        ];
    }

    if ($canViewVms) {
        $searchRows[] = [
            'title' => 'سرورها',
            'description' => 'مشاهده همه ماشین های ابری',
            'type' => 'صفحه',
            'url' => route('customer.servers.index', [], false),
            'keywords' => 'servers ماشین سرورها',
        ];
    }

    foreach ($vmRows as $vm) {
        $searchRows[] = [
            'title' => $vm['name'],
            'description' => $vm['ip'].' - '.$vm['region'].' - '.$vm['plan'],
            'type' => 'ماشین مجازی',
            'url' => $vm['url'],
            'keywords' => $vm['name'].' '.$vm['ip'].' '.$vm['region'].' '.$vm['plan'].' '.$vm['status'],
        ];
    }
@endphp

@section('search_data')
@json($searchRows)
@endsection

@section('content')
    @php
        $walletIsBlocked = $wallet->is_locked || $wallet->balance < 0;
        $metricCards = [
            ['label' => 'کل ماشین ها', 'value' => $dashboardStats['total'], 'hint' => 'ماشین های مجازی فعال در حساب', 'tone' => 'text-slate-950'],
            ['label' => 'روشن', 'value' => $summary['running'], 'hint' => 'در حال مصرف کامل منابع', 'tone' => 'text-[#0069FF]'],
            ['label' => 'منابع', 'value' => $dashboardStats['cpu'].' CPU / '.$dashboardStats['ram'].'GB', 'hint' => $dashboardStats['disk'].'GB دیسک رزرو شده', 'tone' => 'text-slate-950'],
            ['label' => 'برآورد ماهانه', 'value' => $wallets->format($dashboardStats['monthly_spend']), 'hint' => 'بر اساس وضعیت فعلی', 'tone' => 'text-emerald-700'],
            ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage), 'hint' => 'در برداشت بعدی اعمال می شود', 'tone' => $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600'],
        ];
    @endphp

    @if (! $canViewVms)
        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'موجودی کیف پول', 'value' => $wallets->format($wallet->balance), 'hint' => 'کیف پول مالک فضای کاری', 'tone' => $walletIsBlocked ? 'text-red-600' : 'text-slate-950'],
                ['label' => 'برآورد ماهانه', 'value' => $wallets->format($dashboardStats['monthly_spend']), 'hint' => 'براساس ماشین های قابل مشاهده برای شما', 'tone' => 'text-emerald-700'],
                ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage), 'hint' => 'در برداشت بعدی اعمال می شود', 'tone' => $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600'],
                ['label' => 'صورتحساب ها', 'value' => $invoiceCount, 'hint' => 'تعداد فاکتورهای حساب', 'tone' => 'text-[#0069FF]'],
            ] as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                    <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 truncate text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    <p class="mt-1 truncate text-xs font-bold text-slate-400">{{ $metric['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black text-slate-500">دسترسی مالی</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">نمای مالی فضای کاری</h2>
                    </div>
                    <span class="rounded-xl bg-[#EBF3FF] px-3 py-1.5 text-xs font-black text-[#0069FF]">مالی</span>
                </div>
                <p class="mt-4 text-sm font-bold leading-7 text-slate-500">
                    نقش شما برای این فضای کاری به اطلاعات مالی محدود است. برای مشاهده ماشین ها یا عملیات سرور، مالک فضای کاری باید نقش شما را تغییر دهد.
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex justify-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">مشاهده کیف پول</a>
                    <a href="{{ route('customer.invoices.index', [], false) }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">مشاهده صورتحساب ها</a>
                </div>
            </div>

            <aside class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">آخرین تراکنش ها</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($transactions as $transaction)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950">{{ $transaction->description ?: 'تراکنش کیف پول' }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $transaction->created_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-black {{ $transaction->amount < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $wallets->format($transaction->amount) }}</span>
                        </div>
                    @empty
                        <p class="rounded-2xl bg-slate-50 px-4 py-5 text-center text-sm font-bold text-slate-500">هنوز تراکنشی ثبت نشده است.</p>
                    @endforelse
                </div>
            </aside>
        </section>
    @elseif ($vmRows->isEmpty())
        <section class="relative overflow-hidden rounded-[2rem] border border-[#B8D6FF] bg-[#F8FBFF] px-6 py-14 text-center shadow-sm shadow-[#0069FF]/10 sm:px-10 lg:min-h-[560px]">
            <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top,#B8D6FF_0,transparent_58%)] opacity-70"></div>
            <div class="relative mx-auto flex max-w-3xl flex-col items-center">
                <div class="grid size-24 place-items-center rounded-3xl bg-[#0069FF] text-white shadow-2xl shadow-[#0069FF]/25">
                    <svg class="size-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M6 8a6 6 0 0 1 11.7-1.9A5 5 0 0 1 18 16H7a5 5 0 0 1-1-9.9Z" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 16v3m3-3v3m3-3v3M8 21h8" stroke-linecap="round"/>
                    </svg>
                </div>
                <span class="mt-7 rounded-full bg-white px-4 py-2 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">شروع سریع ماشین مجازی</span>
                <h2 class="mt-5 text-4xl font-black leading-tight text-slate-950 md:text-5xl">اولین ماشین ابری خود را بسازید</h2>
                <p class="mt-5 max-w-2xl text-base font-bold leading-9 text-slate-600">
                    هنوز هیچ ماشین مجازی برای این حساب ثبت نشده است. پلن، سیستم عامل و دیتاسنتر را انتخاب کنید و بعد از آماده شدن ماشین، هزینه، مانیتورینگ و بکاپ را از همین پنل دنبال کنید.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#0069FF] px-7 py-4 text-sm font-black text-white shadow-lg shadow-[#0069FF]/25 transition hover:bg-[#0050D0]">
                        ساخت اولین ماشین
                    </a>
                    <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-7 py-4 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-white hover:text-[#0069FF]">
                        شارژ کیف پول
                    </a>
                </div>
                <div class="mt-8 grid w-full gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['title' => 'پرداخت PAYG', 'body' => 'مصرف از کیف پول محاسبه می شود'],
                        ['title' => 'IP اختصاصی', 'body' => 'بعد از آماده سازی به ماشین متصل می شود'],
                        ['title' => 'مانیتورینگ و بکاپ', 'body' => 'پس از ساخت از منوی کنسول فعال است'],
                    ] as $item)
                        <div class="rounded-2xl border border-white bg-white/80 p-4 text-right shadow-sm shadow-slate-200/70">
                            <p class="font-black text-slate-950">{{ $item['title'] }}</p>
                            <p class="mt-2 text-xs font-bold leading-6 text-slate-500">{{ $item['body'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex w-full max-w-xl flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-right shadow-sm shadow-slate-200/70 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-black text-slate-500">موجودی حساب</p>
                        <p class="mt-1 text-xl font-black {{ $walletIsBlocked ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
                    </div>
                    <span class="rounded-xl px-3 py-2 text-xs font-black {{ $walletIsBlocked ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $walletIsBlocked ? 'نیازمند شارژ یا رفع قفل' : 'آماده ساخت ماشین' }}</span>
                </div>
            </div>
        </section>
    @else
        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($metricCards as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                    <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 truncate text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    <p class="mt-1 truncate text-xs font-bold text-slate-400">{{ $metric['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_380px]">
            <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="relative overflow-hidden bg-[#031B4E] p-6 text-white">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(0,105,255,.45),transparent_28%),radial-gradient(circle_at_85%_10%,rgba(0,166,126,.28),transparent_24%)]"></div>
                    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-black text-[#8FBFFF]">Account Health</p>
                            <h2 class="mt-2 text-3xl font-black leading-tight">وضعیت حساب شما شفاف است</h2>
                            <p class="mt-3 max-w-2xl text-sm font-bold leading-8 text-[#C7D4EA]">
                                {{ $summary['running'] }} ماشین روشن، {{ $summary['stopped'] }} ماشین خاموش و {{ $wallets->format($pendingUsage) }} مصرف ثبت نشده دارید.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs font-black text-[#9DB4DC]">موجودی کیف پول</p>
                            <p class="mt-2 text-2xl font-black {{ $walletIsBlocked ? 'text-red-200' : 'text-white' }}">{{ $wallets->format($wallet->balance) }}</p>
                            <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="mt-4 inline-flex w-full justify-center rounded-xl bg-[#00A67E] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#008F6E]">افزایش اعتبار</a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 p-5 lg:grid-cols-3">
                    @foreach ($notifications as $notice)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 size-2.5 shrink-0 rounded-full {{ $notice['tone'] }}"></span>
                                <div>
                                    <p class="font-black text-slate-950">{{ $notice['title'] }}</p>
                                    <p class="mt-2 text-xs font-bold leading-6 text-slate-500">{{ $notice['body'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-5">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black text-slate-500">صورتحساب</p>
                            <h2 class="mt-1 font-black text-slate-950">آخرین وضعیت مالی</h2>
                        </div>
                        <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500">{{ $invoiceCount }} فاکتور</span>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">آخرین صورتحساب</span>
                            <span class="font-black text-slate-950">{{ $latestInvoice?->number ?? 'صادر نشده' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">مصرف ثبت نشده</span>
                            <span class="font-black {{ $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $wallets->format($pendingUsage) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">وضعیت کیف پول</span>
                            <span class="font-black {{ $walletIsBlocked ? 'text-red-600' : 'text-emerald-700' }}">{{ $walletIsBlocked ? 'نیازمند توجه' : 'فعال' }}</span>
                        </div>
                    </div>
                    <a href="{{ route('customer.invoices.index', [], false) }}" class="mt-5 inline-flex w-full justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">مشاهده صورتحساب ها</a>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="font-black text-slate-950">آخرین تراکنش ها</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($transactions as $transaction)
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">{{ $transaction->description ?: 'تراکنش کیف پول' }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $transaction->created_at?->format('Y-m-d H:i') }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-black {{ $transaction->amount < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $wallets->format($transaction->amount) }}</span>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 px-4 py-5 text-center text-sm font-bold text-slate-500">هنوز تراکنشی ثبت نشده است.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </section>

        <section class="mt-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">ماشین های اخیر</h2>
                    <p class="mt-1 text-sm text-slate-500">وضعیت، منابع و هزینه تقریبی ماشین های مجازی همین حساب.</p>
                </div>
                <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex w-fit justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">همه سرورها</a>
            </div>

            <div class="grid gap-4 p-5 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($vmRows->take(6) as $vm)
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-[#0069FF]/10">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-lg font-black text-slate-950" dir="ltr">{{ $vm['name'] }}</p>
                                <p class="mt-1 truncate text-xs font-bold text-slate-500" dir="ltr">{{ $vm['ip'] }} · {{ $vm['region'] }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-2 rounded-xl px-2.5 py-1 text-xs font-black {{ $vm['statusClass'] }}">
                                <span class="size-2 rounded-full {{ $vm['dot'] }}"></span>
                                {{ $vm['status'] }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-slate-50 px-2 py-3">
                                <p class="text-[11px] font-black text-slate-400">CPU</p>
                                <p class="mt-1 text-sm font-black text-slate-950" dir="ltr">{{ $vm['cpu'] }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-2 py-3">
                                <p class="text-[11px] font-black text-slate-400">RAM</p>
                                <p class="mt-1 text-sm font-black text-slate-950" dir="ltr">{{ $vm['ram'] }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-2 py-3">
                                <p class="text-[11px] font-black text-slate-400">Disk</p>
                                <p class="mt-1 text-sm font-black text-slate-950" dir="ltr">{{ $vm['disk'] }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <div>
                                <p class="text-xs font-black text-slate-400">پلن / هزینه</p>
                                <p class="mt-1 text-sm font-black text-slate-950">{{ $vm['plan'] }} · {{ $wallets->format($vm['cost']) }}</p>
                            </div>
                            <a href="{{ $vm['url'] }}" class="inline-flex shrink-0 rounded-xl bg-slate-950 px-4 py-2 text-xs font-black text-white transition hover:bg-[#0069FF]">مشاهده</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
