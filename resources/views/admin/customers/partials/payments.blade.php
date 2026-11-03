@adminAbility('billing.read')
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-semibold text-slate-950">آخرین پرداخت‌ها</h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[640px] text-right text-sm"><thead class="text-xs text-slate-500"><tr><th scope="col" class="py-3">مبلغ</th><th scope="col">وضعیت</th><th scope="col">روش پرداخت</th><th scope="col">تاریخ</th><th scope="col">رسید</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($payments as $payment)
                <tr><td class="py-3 font-semibold">{{ $wallets->format($payment->amount, $payment->currency) }}</td><td>{{ ['successful' => 'موفق', 'pending' => 'در انتظار', 'failed' => 'ناموفق', 'cancelled' => 'لغو شده'][$payment->status] ?? $payment->status }}</td><td>{{ $payment->provider ?: '—' }}</td><td>{{ \App\Support\Jalali::format($payment->paid_at ?: $payment->created_at) }}</td><td dir="ltr">{{ $payment->receiptNumber() }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">پرداختی ثبت نشده است.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endadminAbility
