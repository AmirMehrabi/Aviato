@php
    $trafficPlans = collect($trafficBundles);
    $incomingOnly = $trafficPlans->isNotEmpty() && $trafficPlans->every(fn ($plan) => $plan->network_accounting_enabled && $plan->network_usage_direction === 'ingress');
    $trafficRates = $trafficPlans->filter(fn ($plan) => $plan->network_accounting_enabled && $plan->network_overage_price_unit_bytes > 0)
        ->map(fn ($plan) => (int) round($plan->network_overage_price * 1073741824 / $plan->network_overage_price_unit_bytes))
        ->unique();
@endphp

@if ($trafficPlans->isNotEmpty())
    <aside class="mt-5 rounded-xl border border-[#C6DEFF] bg-[#FAFCFF] p-5 text-right text-[#002355]" aria-label="شرایط ترافیک سرور ابری">
        <p class="font-bold text-[#003A8E]">{{ $incomingOnly ? 'ترافیک خروجی بدون هزینه حجمی' : 'سهمیه و هزینه ترافیک مطابق پلن' }}</p>
        <p class="mt-1 text-sm leading-7">
            @if ($incomingOnly)
                همه پلن‌ها سهمیه ماهانه ترافیک ورودی دارند؛
                @if ($trafficRates->count() === 1)
                    مصرف مازاد با نرخ {{ $wallets->format($trafficRates->first()) }} به‌ازای هر <bdi dir="ltr">GiB</bdi> محاسبه می‌شود.
                @else
                    نرخ مصرف مازاد برای هر پلن در صفحه قیمت‌گذاری مشخص است.
                @endif
            @else
                سهمیه ماهانه، جهت محاسبه و نرخ ترافیک مازاد را در جزئیات هر پلن ببینید.
            @endif
            هزینه ترافیک مازاد علاوه بر قیمت سرور است.
        </p>
        <a href="{{ route('pricing') }}#traffic" class="mt-2 inline-flex text-sm font-bold leading-7 text-[#0069FF] underline underline-offset-4">مشاهده سهمیه پلن‌ها و جزئیات ترافیک ←</a>
    </aside>
@endif
