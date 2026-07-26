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
                </div>
                <div class="shrink-0 text-left">
                    <p class="break-words text-lg font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $wallets->format($transaction->amount) }}</p>
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
