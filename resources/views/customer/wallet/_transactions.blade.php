<div class="divide-y divide-slate-100 md:hidden">
    @forelse ($transactions as $transaction)
        <article class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="break-words font-bold text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ \App\Support\Jalali::format($transaction->created_at) }}</p>
                </div>
                <p class="shrink-0 text-sm font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</p>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
                <span>مانده پس از تراکنش</span>
                <span class="font-bold text-slate-700">{{ $wallets->format($transaction->balance_after) }}</span>
            </div>
            @if ($transaction->reference instanceof \App\Models\Payment && $transaction->reference->type === \App\Models\Payment::TYPE_TOP_UP && $transaction->reference->isSuccessful())
                <a href="{{ route('customer.payments.receipt.show', $transaction->reference, false) }}" class="mt-3 inline-block text-xs font-black text-[#0069FF]">مشاهده رسید پرداخت</a>
            @endif
        </article>
    @empty
        <p class="p-10 text-center text-sm text-slate-500">تراکنشی با این فیلترها پیدا نشد.</p>
    @endforelse
</div>
<div class="hidden overflow-x-auto md:block">
    <table class="w-full min-w-[720px] text-right text-sm">
        <thead class="bg-slate-50 text-xs font-bold text-slate-500">
            <tr>
                <th scope="col" class="px-5 py-4">تاریخ</th>
                <th scope="col" class="px-5 py-4">شرح</th>
                <th scope="col" class="px-5 py-4">نوع</th>
                <th scope="col" class="px-5 py-4 text-left">مبلغ</th>
                <th scope="col" class="px-5 py-4 text-left">مانده پس از تراکنش</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($transactions as $transaction)
                @php
                    $meta = $transaction->metadata ?? [];
                    $typeLabel = [
                        'credit' => 'شارژ', 'charge' => 'کارکرد', 'refund' => 'بازگشت',
                        'adjustment' => 'اصلاح', 'debit' => 'برداشت',
                    ][$transaction->type] ?? $transaction->type;
                @endphp
                <tr class="align-top hover:bg-slate-50/70">
                    <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ \App\Support\Jalali::format($transaction->created_at) }}</td>
                    <td class="min-w-64 px-5 py-4">
                        <div class="font-bold text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</div>
                        @if (($meta['category'] ?? null) === 'payg_usage' && !empty($meta['period_start']) && !empty($meta['period_end']))
                            <div class="mt-1 text-xs text-slate-500">از {{ \App\Support\Jalali::format(\Carbon\CarbonImmutable::parse($meta['period_start'])) }} تا {{ \App\Support\Jalali::format(\Carbon\CarbonImmutable::parse($meta['period_end'])) }}</div>
                        @endif
                        @if ($transaction->reference instanceof \App\Models\Payment && $transaction->reference->type === \App\Models\Payment::TYPE_TOP_UP && $transaction->reference->isSuccessful())
                            <a href="{{ route('customer.payments.receipt.show', $transaction->reference, false) }}" class="mt-2 inline-block text-xs font-black text-[#0069FF] hover:underline">مشاهده رسید پرداخت</a>
                        @endif
                    </td>
                    <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-black {{ $transaction->amount >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $typeLabel }}</span></td>
                    <td class="whitespace-nowrap px-5 py-4 text-left font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-left font-bold text-slate-700">{{ $wallets->format($transaction->balance_after) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">تراکنشی با این فیلترها پیدا نشد.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator && $transactions->hasPages())
    <div class="overflow-x-auto border-t border-slate-200 px-5 py-4">{{ $transactions->links() }}</div>
@endif
