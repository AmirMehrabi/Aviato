<section id="domain-orders" class="domain-card scroll-mt-6">
    <h1 class="domain-heading">سفارش‌های دامنه</h1>
    <div class="mt-4 divide-y divide-slate-100">
        @forelse($domainOrders as $order)
            @php
                $canResume = (int) $order->customer_id === (int) $customer->id && in_array($order->status, ['draft', 'awaiting_payment'], true);
            @endphp
            <article class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm">
                <div class="min-w-0">
                    <bdi dir="ltr" class="break-all font-black">{{ $order->domain }}</bdi>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $order->operation === 'renew' ? 'تمدید' : 'ثبت' }} · {{ $order->period }} سال
                        · {{ \App\Support\Jalali::format($order->created_at, 'Y/m/d') }}
                    </p>
                </div>
                <span class="domain-badge">{{ $order->label() }}</span>
                <a href="{{ route('customer.domains.orders.show', $order, false) }}" class="domain-secondary">
                    {{ $canResume ? 'ادامه سفارش' : 'مشاهده سفارش' }}
                    <span class="sr-only">{{ $order->domain }}</span>
                </a>
            </article>
        @empty
            <div class="py-6 text-center">
                <p class="domain-help">هنوز سفارش دامنه‌ای در این فضای کاری ثبت نشده است.</p>
            </div>
        @endforelse
    </div>
    {{ $domainOrders->links() }}
</section>
