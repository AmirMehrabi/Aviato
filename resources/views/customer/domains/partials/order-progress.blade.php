@php
    $complete = $order->status === 'completed';
    $funded = $complete || $order->funded_at !== null || $order->isProcessing();
    $ended = in_array($order->status, ['failed', 'cancelled'], true);
    $paymentLabel = $order->refund_transaction_id ? 'برگشت به کیف پول' : ($complete ? 'پرداخت انجام شد' : ($funded ? 'مبلغ سفارش تأمین شد' : ($ended ? 'پرداخت تکمیل نشد' : 'در انتظار پرداخت')));
    $requestLabel = $order->operation === 'renew' ? 'درخواست تمدید' : 'درخواست ثبت';
    $stages = [
        ['پرداخت', $paymentLabel, $funded && !$order->refund_transaction_id, !$funded && !$ended],
        [$requestLabel, $complete ? 'انجام شد' : ($ended ? $order->label() : ($order->isProcessing() ? $order->label() : 'پس از پرداخت')), $complete, $order->isProcessing()],
        ['نتیجه', $complete ? ($order->operation === 'renew' ? 'تمدید شد' : 'دامنه ثبت شد') : ($ended ? 'سفارش بسته شد' : 'در انتظار تأیید نهایی'), $complete, false],
    ];
@endphp

<ol aria-label="پیشرفت سفارش" class="my-5 grid gap-2 text-start sm:grid-cols-3">
    @foreach($stages as [$title, $label, $done, $current])
        <li @if($current) aria-current="step" @endif
            class="rounded-xl border p-3 {{ $done ? 'border-emerald-100 bg-emerald-50 text-emerald-800' : ($current ? 'border-blue-200 bg-blue-50 text-blue-800' : 'border-slate-200 bg-slate-50 text-slate-600') }}">
            <p class="text-xs font-black">{{ $title }}</p>
            <p class="mt-1 text-sm">{{ $label }}</p>
        </li>
    @endforeach
</ol>
