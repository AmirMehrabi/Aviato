@extends('layouts.admin')

@section('title', 'داشبورد مدیریت آویاتو')

@section('content')
    <div class="px-4 py-5 md:px-8 lg:px-10">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-950">داشبورد عملیات</h1>
                <p class="mt-1 text-sm font-bold text-slate-500">موارد نیازمند اقدام، وضعیت زیرساخت و خلاصه پرداخت‌ها</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 transition-colors hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v6h-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    به‌روزرسانی
                </a>
                <a href="{{ route('admin.billing.overview') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-4 text-xs font-black text-white transition-[background-color,transform] hover:bg-[#0050D0] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    مرکز مالی
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </header>
        
        <section class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-5" aria-label="خلاصه وضعیت">
            @foreach ($statusStrip as $item)
                @php
                    $toneClasses = match($item['tone']) {
                        'green' => ['dot' => 'bg-emerald-500', 'icon' => 'bg-emerald-50', 'value' => 'text-emerald-700'],
                        'amber' => ['dot' => 'bg-amber-500', 'icon' => 'bg-amber-50', 'value' => 'text-amber-700'],
                        'red' => ['dot' => 'bg-red-500', 'icon' => 'bg-red-50', 'value' => 'text-red-700'],
                        default => ['dot' => 'bg-slate-400', 'icon' => 'bg-slate-50', 'value' => 'text-slate-950'],
                    };
                @endphp
                <a href="{{ $item['url'] }}" class="group flex min-h-[5.5rem] items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-200/40 transition-[border-color,box-shadow] hover:border-[#B8D6FF] hover:shadow-md hover:shadow-[#0069FF]/[0.08] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $toneClasses['icon'] }}" aria-hidden="true">
                        <span class="size-2.5 rounded-full {{ $toneClasses['dot'] }}"></span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-black text-slate-500">{{ $item['label'] }}</span>
                        <span class="mt-0.5 block text-lg font-black leading-tight {{ $toneClasses['value'] }}">{{ $item['value'] }}</span>
                        <span class="mt-0.5 block truncate text-[11px] font-bold text-slate-400">{{ $item['sub'] }}</span>
                    </span>
                </a>
            @endforeach
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,.65fr)]">
            <div id="critical-warnings" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/50">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-black text-slate-950">هشدارهای بحرانی</h2>
                            <span class="rounded-lg {{ $criticalAlerts->isNotEmpty() ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }} px-2 py-1 text-[11px] font-black">
                                {{ $criticalAlerts->count() }} قابل مشاهده
                            </span>
                        </div>
                        <p class="mt-1 text-xs font-bold leading-6 text-slate-500">ابتدا اقدام اصلی را انجام دهید. بستن هشدار فقط آن را برای حساب شما پنهان می‌کند.</p>
                    </div>
                    @if ($dismissedActiveCount > 0)
                        <form method="POST" action="{{ route('admin.dashboard.warnings.restore') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                                نمایش {{ $dismissedActiveCount }} هشدار بسته‌شده
                            </button>
                        </form>
                    @endif
                </div>

                <div class="space-y-2 p-3">
                    @forelse ($criticalAlerts->take(6) as $alert)
                        @php
                            $alertClasses = $alert['tone'] === 'red'
                                ? ['surface' => 'border-red-200 bg-red-50/70', 'dot' => 'bg-red-500', 'label' => 'text-red-700', 'button' => 'bg-red-600 hover:bg-red-700 focus-visible:outline-red-600']
                                : ['surface' => 'border-amber-200 bg-amber-50/70', 'dot' => 'bg-amber-500', 'label' => 'text-amber-800', 'button' => 'bg-amber-600 hover:bg-amber-700 focus-visible:outline-amber-600'];
                        @endphp
                        <article class="rounded-xl border p-3 {{ $alertClasses['surface'] }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $alertClasses['dot'] }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-black text-slate-950" dir="auto">{{ $alert['title'] }}</p>
                                        <span class="rounded-md bg-white/80 px-2 py-0.5 text-[10px] font-black {{ $alertClasses['label'] }}">{{ $alert['label'] }}</span>
                                    </div>
                                    <p class="mt-1 break-words text-xs font-bold leading-6 text-slate-600">{{ $alert['meta'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.dashboard.warnings.dismiss') }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="warning_key" value="{{ $alert['key'] }}">
                                    <button
                                        type="submit"
                                        class="inline-flex size-10 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-white hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-600"
                                        aria-label="بستن هشدار {{ $alert['title'] }}"
                                        title="بستن هشدار"
                                    >
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2 pr-5">
                                @if ($alert['action_url'])
                                    @if ($alert['action_method'] === 'GET')
                                        <a href="{{ $alert['action_url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-xs font-black text-white transition-[background-color,transform] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 {{ $alertClasses['button'] }}">
                                            {{ $alert['action_label'] }}
                                        </a>
                                    @else
                                        <form method="POST" action="{{ $alert['action_url'] }}" @if($alert['confirmation']) onsubmit="return confirm(@js($alert['confirmation']))" @endif>
                                            @csrf
                                            @if ($alert['action_method'] !== 'POST')
                                                @method($alert['action_method'])
                                            @endif
                                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-xs font-black text-white transition-[background-color,transform] active:scale-[0.96] focus-visible:outline-2 focus-visible:outline-offset-2 {{ $alertClasses['button'] }}">
                                                {{ $alert['action_label'] }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                <a href="{{ $alert['details_url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 transition-colors hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                                    مشاهده جزئیات
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/50 px-5 py-8 text-center">
                            <span class="mx-auto grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <p class="mt-3 text-sm font-black text-emerald-800">هشدار قابل مشاهده‌ای وجود ندارد</p>
                            @if ($activeWarningCount > 0)
                                <p class="mt-1 text-xs font-bold text-emerald-700">هشدارهای فعال برای حساب شما بسته شده‌اند و از بالا قابل بازیابی هستند.</p>
                            @else
                                <p class="mt-1 text-xs font-bold text-emerald-700">زیرساخت و عملیات فعلاً به اقدام فوری نیاز ندارند.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/50" aria-labelledby="infrastructure-health-heading">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5">
                    <div>
                        <h2 id="infrastructure-health-heading" class="text-base font-black text-slate-950">وضعیت زیرساخت</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">وضعیت واقعی اتصال و همگام‌سازی</p>
                    </div>
                    <a href="{{ route('admin.proxmox-servers.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">همه سرورها</a>
                </div>
                <div class="space-y-2 p-3">
                    @forelse ($serverHealth as $server)
                        <article class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ $server['url'] }}" class="truncate text-sm font-black text-slate-950 hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $server['name'] }}</a>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[10px] font-black {{ $server['status_class'] }}">
                                            <span class="size-1.5 rounded-full {{ $server['dot_class'] }}" aria-hidden="true"></span>
                                            {{ $server['status'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-[11px] font-bold text-slate-500">{{ $server['detail'] }}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ $server['sync'] }} · {{ $server['synced_at'] }}</p>
                                </div>
                                <form method="POST" action="{{ $server['sync_url'] }}" class="shrink-0">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 transition-colors hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                                        همگام‌سازی
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center">
                            <p class="text-sm font-black text-slate-600">سروری ثبت نشده است</p>
                            <a href="{{ route('admin.proxmox-servers.create') }}" class="mt-3 inline-flex text-xs font-black text-[#0069FF] hover:underline">افزودن سرور Proxmox</a>
                        </div>
                    @endforelse
                </div>
            </aside>
        </section>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50" aria-labelledby="payments-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="payments-heading" class="text-base font-black text-slate-950">پرداخت‌های درگاه</h2>
                    <p class="mt-1 text-xs font-bold text-slate-500">خلاصه وصول و موارد مالی نیازمند بررسی</p>
                </div>
                <a href="{{ route('admin.billing.payments.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده همه پرداخت‌ها</a>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                <div class="rounded-xl bg-emerald-50 p-3">
                    <p class="text-[11px] font-black text-emerald-700">وصول موفق امروز</p>
                    <p class="mt-1 text-lg font-black text-emerald-800">{{ $wallets->format($paymentSummary['today_amount']) }}</p>
                </div>
                <div class="rounded-xl bg-[#EBF3FF] p-3">
                    <p class="text-[11px] font-black text-[#31527F]">وصول موفق · ۳۰ روز</p>
                    <p class="mt-1 text-lg font-black text-[#0069FF]">{{ $wallets->format($paymentSummary['successful_amount']) }}</p>
                    <p class="mt-0.5 text-[10px] font-bold text-[#61799C]">{{ number_format($paymentSummary['successful_count']) }} پرداخت موفق</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] font-black text-slate-600">نرخ موفقیت پرداخت</p>
                    <p class="mt-1 text-lg font-black text-slate-950">{{ $paymentSummary['success_rate'] }}٪</p>
                </div>
                <a href="{{ route('admin.billing.wallets.index', ['state' => 'negative']) }}" class="rounded-xl {{ $paymentSummary['negative_wallets'] > 0 ? 'bg-red-50' : 'bg-emerald-50' }} p-3 transition-colors hover:brightness-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    <p class="text-[11px] font-black {{ $paymentSummary['negative_wallets'] > 0 ? 'text-red-700' : 'text-emerald-700' }}">کیف پول منفی</p>
                    <p class="mt-1 text-lg font-black {{ $paymentSummary['negative_wallets'] > 0 ? 'text-red-800' : 'text-emerald-800' }}">{{ $paymentSummary['negative_wallets'] }} مورد</p>
                    <p class="mt-0.5 text-[10px] font-bold {{ $paymentSummary['negative_wallets'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $paymentSummary['negative_total'] }}</p>
                </a>
            </div>
        </section>

        <section class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-950">آخرین پرداخت‌ها</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">{{ $paymentSummary['pending_count'] }} در انتظار · {{ $paymentSummary['failed_count'] }} ناموفق</p>
                    </div>
                    <a href="{{ route('admin.billing.payments.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده همه پرداخت‌ها</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse ($recentGatewayPayments as $payment)
                        @php
                            $paymentTone = match ($payment['status']) {
                                'successful' => 'bg-emerald-50 text-emerald-700',
                                'failed', 'cancelled' => 'bg-red-50 text-red-700',
                                default => 'bg-amber-50 text-amber-700',
                            };
                            $paymentStatus = [
                                'successful' => 'موفق',
                                'failed' => 'ناموفق',
                                'cancelled' => 'لغوشده',
                                'pending' => 'در انتظار',
                            ][$payment['status']] ?? $payment['status'];
                        @endphp
                        <a href="{{ $payment['url'] }}" class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 transition-colors hover:border-[#B8D6FF] hover:bg-[#F8FBFF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg text-xs font-black {{ $paymentTone }}" aria-hidden="true">{{ $payment['status'] === 'successful' ? '✓' : '!' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-black text-slate-900">{{ $payment['customer'] }}</span>
                                <span class="mt-0.5 block truncate text-[11px] font-bold text-slate-400">{{ $payment['provider'] }} · {{ $payment['reference'] ?: 'بدون مرجع' }}</span>
                            </span>
                            <span class="shrink-0 text-left">
                                <span class="block text-xs font-black text-slate-900">{{ $wallets->format($payment['amount']) }}</span>
                                <span class="mt-1 inline-flex rounded-md px-2 py-0.5 text-[10px] font-black {{ $paymentTone }}">{{ $paymentStatus }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-bold text-slate-400">هنوز پرداختی ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-black text-slate-950">تیکت‌های نیازمند پاسخ</h2>
                    <a href="{{ route('admin.tickets.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-black text-[#0069FF] transition-colors hover:bg-[#EBF3FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده همه تیکت‌ها</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse ($recentTickets as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket['number']) }}" class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 transition-colors hover:border-[#B8D6FF] hover:bg-[#F8FBFF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-black {{ match($ticket['priority']) { 'urgent' => 'bg-red-50 text-red-700', 'high' => 'bg-amber-50 text-amber-700', 'normal' => 'bg-[#EBF3FF] text-[#0069FF]', default => 'bg-slate-100 text-slate-500' } }}">
                                {{ $ticket['number'] }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-slate-900">{{ $ticket['subject'] }}</span>
                                <span class="mt-0.5 block truncate text-[11px] text-slate-400">{{ $ticket['customer'] }} · {{ $ticket['time'] }}</span>
                            </span>
                            <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-black {{ match($ticket['status']) { 'open' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', default => 'bg-slate-100 text-slate-600' } }}">
                                {{ $ticket['status_label'] }}
                            </span>
                        </a>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-bold text-slate-400">تیکتی نیازمند پاسخ نیست.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
