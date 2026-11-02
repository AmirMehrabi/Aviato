<article class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 py-5 last:border-0">
    <div>
        <p class="font-bold text-slate-900"><span dir="ltr">{{ $invoice->number }}</span> · {{ ($invoice->meta['traffic'] ?? false) ? 'ترافیک ماهانه' : (($invoice->meta['initial'] ?? false) ? 'اولین دوره و راه‌اندازی' : 'تمدید سرویس') }}</p>
        <p class="mt-2 text-xs leading-6 text-slate-500">{{ ($invoice->meta['coverage_pending'] ?? false) ? 'بازه سرویس پس از تکمیل نصب مشخص می‌شود' : 'از '.\App\Support\Jalali::format($invoice->period_start, 'Y/m/d').' تا '.\App\Support\Jalali::format($invoice->period_end, 'Y/m/d').' (ابتدای دوره بعد)' }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $invoice->status === 'paid' ? 'پرداخت: '.\App\Support\Jalali::format($invoice->paid_at) : 'مهلت پرداخت: '.\App\Support\Jalali::format($invoice->due_at, 'Y/m/d') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-4">
        <div><p class="font-black text-slate-900">{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</p><p class="mt-1 text-xs font-bold {{ $invoice->status === 'paid' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $invoice->status === 'paid' ? 'پرداخت‌شده' : ($invoice->status === 'void' ? 'لغوشده' : ($invoice->due_at?->isPast() ? 'سررسید گذشته' : 'در انتظار پرداخت')) }}</p></div>
        <a class="{{ $invoice->status === 'issued' ? 'colo-btn' : 'colo-link' }}" href="{{ route('customer.colocation.invoices.show', $invoice, false) }}">{{ $invoice->status === 'issued' ? 'مشاهده و پرداخت' : 'مشاهده صورتحساب' }}</a>
    </div>
</article>
