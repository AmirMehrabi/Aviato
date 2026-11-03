@adminAbility('billing.read')

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950">صورتحساب‌ها</h2>
            <p class="mt-1 text-sm text-slate-500">آخرین صورتحساب‌های مصرف ثبت‌شده برای این مشتری.</p>
            <div class="mt-5 space-y-3">
                @forelse($invoices as $invoice)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-950">{{ $invoice->number }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ \App\Support\Jalali::format($invoice->period_start, 'Y/m/d') }}</p>
                            </div>
                            @php($invoiceStatus = match ($invoice->status) { 'paid', 'issued' => 'پرداخت شده', 'cancelled' => 'لغو شده', default => 'در انتظار' })
                            <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $invoiceStatus === 'پرداخت شده' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-amber-50 text-amber-700' }}">{{ $invoiceStatus }}</span>
                        </div>
                        <p class="mt-3 text-left text-lg font-semibold text-slate-950">{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">هنوز صورتحسابی برای این مشتری صادر نشده است.</div>
                @endforelse
            </div>
        </section>

@endadminAbility
