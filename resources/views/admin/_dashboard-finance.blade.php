@php($trendMax = max(1, $finance['trend']->max('amount')))
<section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="finance-heading">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="finance-heading" class="text-lg font-bold text-slate-950">نمای مالی</h2>
            <p class="mt-1 text-xs text-slate-500">وصول روزانه در بازه انتخاب‌شده · {{ $finance['trend_currency'] }}</p>
        </div>
        @adminRoute('admin.billing.overview')
            <a href="{{ route('admin.billing.overview', $reportRange) }}" class="min-h-10 py-2 text-sm font-semibold text-[#0059DB] hover:underline">مرکز مالی ←</a>
        @endadminRoute
    </div>
    <div class="mt-5 flex h-40 items-end gap-1.5" role="img" aria-label="روند وصول روزانه در بازه انتخاب‌شده">
        @foreach ($finance['trend'] as $day)
            <div class="relative flex h-full min-w-0 flex-1 items-end" title="{{ $day['label'] }}: {{ $wallets->format($day['amount'], $finance['trend_currency']) }}">
                <div class="w-full rounded-t bg-[#0069FF]" style="height: {{ $day['amount'] > 0 ? max(3, round($day['amount'] / $trendMax * 100)) : 0 }}%"></div>
                <span class="sr-only">{{ $day['label'] }}: {{ $wallets->format($day['amount'], $finance['trend_currency']) }}</span>
            </div>
        @endforeach
    </div>
    <div class="mt-2 flex justify-between text-xs text-slate-500"><span>{{ $finance['trend']->first()['label'] }}</span><span>{{ $finance['trend']->last()['label'] }}</span></div>
    @if ($finance['trend']->sum('amount') === 0)
        <p class="mt-3 text-sm text-slate-500">در این بازه وصولی ثبت نشده است.</p>
    @endif
    @if ($finance['trend_currency_count'] > 1)
        <p class="mt-3 text-xs text-slate-500">نمودار فقط {{ $finance['trend_currency'] }} را نشان می‌دهد؛ وصول هر ارز در کارت بالا جداست.</p>
    @endif
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <div><span class="text-sm text-slate-600">نرخ موفقیت پرداخت </span><strong class="text-lg text-slate-950">{{ $finance['success_rate'] === null ? '—' : $finance['success_rate'].'٪' }}</strong><p class="mt-1 text-xs text-slate-500">از {{ number_format($finance['finalized_attempts']) }} تلاش نهایی · در انتظار محاسبه نشده</p></div>
        @adminRoute('admin.billing.payments.index')
            <a href="{{ route('admin.billing.payments.index', $reportRange) }}" class="min-h-10 py-2 text-sm font-semibold text-[#0059DB] hover:underline">پرداخت‌های این بازه ←</a>
        @endadminRoute
    </div>
</section>
