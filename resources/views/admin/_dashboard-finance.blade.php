@php
    $paymentRange = ['from' => $finance['from']->toDateString(), 'to' => $finance['to']->toDateString()];
    $paidRange = $paymentRange + ['date_basis' => 'paid', 'status' => 'successful'];
    $trendMax = max(1, $finance['trend']->max('amount'));
@endphp

<section class="mt-6" aria-labelledby="finance-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="finance-heading" class="text-lg font-bold text-slate-950">مالی و پرداخت درگاه</h2>
            <p class="mt-1 text-sm text-slate-600">وصول درگاه، شارژ کیف پول است؛ مصرف تسویه‌شده جداگانه نمایش داده می‌شود.</p>
        </div>
        <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1" aria-label="بازه گزارش مالی">
            @foreach ([1 => 'امروز', 7 => '۷ روز', 30 => '۳۰ روز'] as $days => $label)

@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', array_filter(['period' => $days, 'category' => $dashboard['category']])) }}" @if ($finance['days'] === $days) aria-current="page" @endif class="inline-flex min-h-9 items-center rounded-lg px-3 text-sm font-semibold {{ $finance['days'] === $days ? 'bg-[#0069FF] text-white' : 'text-slate-700 hover:bg-slate-100' }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $label }}</a>
@endadminRoute

            @endforeach
        </nav>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">

@adminRoute('admin.billing.payments.index')
<a href="{{ route('admin.billing.payments.index', $paidRange) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <h3 class="text-sm font-semibold text-slate-600">وصول موفق درگاه</h3>
            <div class="mt-3 space-y-1 text-xl font-bold text-slate-950">
                @forelse ($finance['collected'] as $money)
                    <div>{{ $money['formatted'] }} <span class="text-xs font-medium text-slate-500" dir="ltr">({{ $money['currency'] }})</span></div>
                @empty
                    <div>{{ $wallets->format(0) }}</div>
                @endforelse
            </div>
            <p class="mt-2 text-xs text-slate-500">براساس زمان پرداخت موفق</p>
        </a>
@endadminRoute


@adminRoute('admin.billing.payments.index')
<a href="{{ route('admin.billing.payments.index', $paymentRange + ['status' => 'successful']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <h3 class="text-sm font-semibold text-slate-600">تلاش موفق</h3>
            <p class="mt-3 text-2xl font-bold text-slate-950">{{ number_format($finance['successful_attempts']) }}</p>
            <p class="mt-2 text-xs text-slate-500">از تلاش‌های آغازشده در این بازه</p>
        </a>
@endadminRoute

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-600">نرخ موفقیت</h3>
            <p class="mt-3 text-2xl font-bold text-slate-950">{{ $finance['success_rate'] === null ? '—' : $finance['success_rate'].'٪' }}</p>
            <p class="mt-2 text-xs text-slate-500">از {{ number_format($finance['finalized_attempts']) }} تلاش تعیین‌تکلیف‌شده؛ در انتظار محاسبه نشده</p>
        </div>

@adminRoute('admin.billing.payments.index')
<a href="{{ route('admin.billing.payments.index', $paymentRange + ['status' => 'unsuccessful']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <h3 class="text-sm font-semibold text-slate-600">ناموفق و لغوشده</h3>
            <p class="mt-3 text-2xl font-bold text-slate-950">{{ number_format($finance['failed_attempts']) }}</p>
            <p class="mt-2 text-xs text-slate-500">تلاش‌های آغازشده در این بازه</p>
        </a>
@endadminRoute


@adminRoute('admin.dashboard')
<a href="{{ route('admin.dashboard', ['category' => 'payments', 'period' => $finance['days']]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <h3 class="text-sm font-semibold text-slate-600">پرداخت نیازمند بررسی</h3>
            <p class="mt-3 text-2xl font-bold {{ $finance['aged_pending'] + $finance['reconciliation_count'] ? 'text-amber-800' : 'text-slate-950' }}">{{ number_format($finance['aged_pending'] + $finance['reconciliation_count']) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ number_format($finance['aged_pending']) }} در انتظار بیش از ۱۵ دقیقه · {{ number_format($finance['reconciliation_count']) }} بدون اعتبار کیف پول</p>
        </a>
@endadminRoute

    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><h3 class="font-bold text-slate-950">روند وصول درگاه</h3><p class="mt-1 text-xs text-slate-600">{{ $finance['trend_days'] }} روز اخیر · {{ $finance['trend_currency'] }} · براساس زمان پرداخت</p></div>

@adminRoute('admin.billing.overview')
<a href="{{ route('admin.billing.overview') }}" class="text-sm font-semibold text-[#0059DB] hover:underline">مرکز مالی</a>
@endadminRoute

            </div>
            <div class="mt-5 flex h-36 items-end gap-1" role="img" aria-label="روند وصول روزانه در {{ $finance['trend_days'] }} روز اخیر">
                @foreach ($finance['trend'] as $day)
                    <div class="group relative flex h-full min-w-0 flex-1 items-end" title="{{ $day['label'] }}: {{ $wallets->format($day['amount'], $finance['trend_currency']) }}">
                        <div class="w-full rounded-t bg-[#0069FF]" style="height: {{ $day['amount'] > 0 ? max(3, round($day['amount'] / $trendMax * 100)) : 0 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-xs text-slate-600"><span>{{ $finance['trend']->first()['label'] }}</span><span>{{ $finance['trend']->last()['label'] }}</span></div>
            @if ($finance['trend_currency_count'] > 1)
                <p class="mt-3 text-xs text-slate-600">نمودار فقط ارز {{ $finance['trend_currency'] }} را نشان می‌دهد؛ جمع هر ارز در کارت وصول جداست.</p>
            @endif
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-950">سلامت درگاه‌ها</h3><p class="mt-1 text-xs text-slate-600">تلاش‌ها بر اساس زمان ایجاد؛ وصول بر اساس زمان پرداخت. نرخ برای کمتر از ۱۰ تلاش نهایی نمایش داده نمی‌شود.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full whitespace-nowrap text-right text-sm"><thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="px-4 py-3">درگاه</th><th class="px-4 py-3">تلاش</th><th class="px-4 py-3">موفق</th><th class="px-4 py-3">ناموفق/لغو</th><th class="px-4 py-3">نرخ موفقیت</th><th class="px-4 py-3">وصول</th><th class="px-4 py-3">در انتظار طولانی</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse ($finance['providers'] as $provider)
                    <tr><td class="px-4 py-3 font-semibold text-slate-900">
@adminRoute('admin.billing.payments.index')
<a class="text-[#0059DB] hover:underline" href="{{ route('admin.billing.payments.index', $paymentRange + ['provider' => $provider['key']]) }}">{{ $provider['name'] }}</a>
@endadminRoute
</td><td class="px-4 py-3">{{ number_format($provider['attempts']) }}</td><td class="px-4 py-3">{{ number_format($provider['successful']) }}</td><td class="px-4 py-3">{{ number_format($provider['failed']) }}</td><td class="px-4 py-3">{{ $provider['rate'] === null ? 'داده ناکافی' : $provider['rate'].'٪' }}</td><td class="px-4 py-3">@forelse ($provider['collected'] as $money)<div>{{ $money['formatted'] }} <span class="text-xs text-slate-500" dir="ltr">({{ $money['currency'] }})</span></div>@empty — @endforelse</td><td class="px-4 py-3 {{ $provider['aged_pending'] ? 'font-semibold text-amber-800' : 'text-slate-600' }}">{{ number_format($provider['aged_pending']) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-600">در این بازه پرداخت درگاهی ثبت نشده است.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">

@adminRoute('admin.billing.usage.index')
<a href="{{ route('admin.billing.usage.index', $paymentRange + ['state' => 'settled']) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF]"><div><h3 class="text-sm font-semibold text-slate-700">مصرف تسویه‌شده خدمات</h3><p class="mt-1 text-xs text-slate-600">براساس تاریخ خدمت در همین بازه</p></div><strong class="text-lg text-slate-950">{{ $wallets->format($finance['settled_consumption']) }}</strong></a>
@endadminRoute


@adminRoute('admin.billing.wallets.index')
<a href="{{ route('admin.billing.wallets.index', ['state' => 'negative']) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#0069FF]"><div><h3 class="text-sm font-semibold text-slate-700">کیف پول منفی</h3><p class="mt-1 text-xs text-slate-600">{{ number_format($finance['negative_wallet_count']) }} حساب · کسری کل</p></div><strong class="text-lg text-slate-950">{{ $wallets->format($finance['negative_wallet_total']) }}</strong></a>
@endadminRoute

    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h3 class="font-bold text-slate-950">آخرین پرداخت‌های درگاه</h3><p class="mt-1 text-xs text-slate-600">آخرین ۸ تلاش، مستقل از بازه گزارش</p></div>
@adminRoute('admin.billing.payments.index')
<a href="{{ route('admin.billing.payments.index') }}" class="text-sm font-semibold text-[#0059DB] hover:underline">همه پرداخت‌ها</a>
@endadminRoute
</div>
        <div class="overflow-x-auto"><table class="min-w-full whitespace-nowrap text-right text-sm"><thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="px-4 py-3">مشتری</th><th class="px-4 py-3">درگاه</th><th class="px-4 py-3">مبلغ</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3">زمان ایجاد</th><th class="px-4 py-3">مرجع</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($finance['recent'] as $payment)
                <tr><td class="px-4 py-3 text-slate-900">{{ $payment->customer?->name ?: 'مشتری شماره '.$payment->customer_id }}</td><td class="px-4 py-3">{{ $payment->provider }}</td><td class="px-4 py-3 font-semibold">{{ $wallets->format($payment->amount, $payment->currency) }}</td><td class="px-4 py-3"><x-admin.status-badge :value="$payment->status" /></td><td class="px-4 py-3">{{ \Morilog\Jalali\Jalalian::fromCarbon($payment->created_at)->format('Y/m/d H:i') }}</td><td class="max-w-36 truncate px-4 py-3" dir="ltr" title="{{ $payment->provider_reference ?: $payment->authority }}">{{ $payment->provider_reference ?: ($payment->authority ?: '—') }}</td><td class="px-4 py-3">
@adminRoute('admin.billing.payments.show')
<a href="{{ route('admin.billing.payments.show', $payment) }}" class="font-semibold text-[#0059DB] hover:underline">جزئیات</a>
@endadminRoute
</td></tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-600">هنوز پرداختی ثبت نشده است.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</section>
