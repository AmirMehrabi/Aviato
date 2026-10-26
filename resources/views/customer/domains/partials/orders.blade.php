<section id="domain-orders" class="domain-card scroll-mt-6">
    <h2 class="domain-heading">سفارش‌های دامنه</h2><p class="domain-help mt-2">خرید ناتمام را ادامه دهید یا نتیجه ثبت و تمدید را ببینید.</p>
    <div class="mt-4 divide-y divide-slate-100">
        @forelse($domainOrders as $order)
            <a href="{{ route('customer.domains.orders.show', $order, false) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"><span dir="ltr" class="break-all font-bold">{{ $order->domain }}</span><span class="text-slate-500">{{ $order->label() }}</span><span class="font-bold text-blue-600">{{ (int) $order->customer_id === (int) $customer->id && in_array($order->status, ['draft', 'awaiting_payment']) ? 'ادامه سفارش' : 'مشاهده وضعیت' }} ←</span></a>
        @empty
            <p class="py-5 text-sm text-slate-500">هنوز سفارش دامنه‌ای در این فضای کاری ثبت نشده است.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $domainOrders->links() }}</div>
</section>
