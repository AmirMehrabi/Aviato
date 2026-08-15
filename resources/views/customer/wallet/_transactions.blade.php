<div class="divide-y divide-slate-100">
    @forelse ($transactions as $transaction)
        @php
            $meta = $transaction->metadata ?? [];
        @endphp
        <article class="min-w-0 p-5">
            <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <p class="min-w-0 break-words text-base font-black text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</p>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black {{ $transaction->amount >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $transaction->type }}</span>
                        @if (($meta['category'] ?? null) === 'payg_usage')
                            <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-700">PAYG</span>
                        @endif
                        @if (($meta['category'] ?? null) === 'network_usage' || isset(($meta['categories'] ?? [])['network_usage']))
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-black text-blue-700">شبکه</span>
                        @endif
                    </div>
                    <div class="mt-2 flex min-w-0 flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-slate-500">
                        <span>{{ \App\Support\Jalali::format($transaction->created_at) }}</span>
                        <span class="break-words">مانده پس از تراکنش: {{ $wallets->format($transaction->balance_after) }}</span>
                        @if (!empty($meta['vm_name']))
                            <span class="break-all" dir="ltr">ماشین مجازی: {{ $meta['vm_name'] }}</span>
                        @endif
                    </div>
                    @if (($meta['category'] ?? null) === 'payg_usage')
                        <p class="mt-3 break-words text-sm leading-7 text-slate-600">از {{ \App\Support\Jalali::format(\Carbon\CarbonImmutable::parse($meta['period_start'])) }} تا {{ \App\Support\Jalali::format(\Carbon\CarbonImmutable::parse($meta['period_end'])) }} · {{ number_format((float) ($meta['hours'] ?? 0), 2) }} ساعت · نرخ ساعتی {{ number_format((float) ($meta['hourly_rate'] ?? 0), 2) }}</p>
                    @endif
                    @if (($meta['category'] ?? null) === 'network_usage' && !empty($meta['bucket_id']))
                        <p class="mt-3 text-xs text-slate-500" dir="ltr">Bucket {{ $meta['bucket_id'] }} · revision {{ $meta['revision'] ?? 1 }}{{ !empty($meta['correction']) ? ' · correction' : '' }}</p>
                    @endif
                </div>
                <div class="shrink-0 text-left">
                    <p class="break-words text-lg font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</p>
                    @if ($transaction->reference instanceof \App\Models\Payment && $transaction->reference->type === \App\Models\Payment::TYPE_TOP_UP && $transaction->reference->isSuccessful())
                        <a href="{{ route('customer.payments.receipt.show', $transaction->reference, false) }}" class="mt-2 inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-[#0069FF] transition hover:border-[#B8D6FF] hover:bg-[#F2F8FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده رسید پرداخت</a>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="p-10 text-center text-sm font-bold text-slate-500">هنوز تراکنشی برای این کیف پول ثبت نشده است.</div>
    @endforelse
</div>

@if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator && $transactions->hasPages())
    <div class="overflow-x-auto border-t border-slate-200 px-5 py-4">
        {{ $transactions->links() }}
    </div>
@endif
