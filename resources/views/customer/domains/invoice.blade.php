@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('title', 'فاکتور دامنه')
@section('header_title', 'فاکتور دامنه')
@section('header_subtitle', 'فضای کاری: '.$activeProject->name)
@php($activeNav = 'domains')
@section('content')
    @include('customer.corporate.billing-recipient', ['recipient' => $order->billingRecipient()])
<div class="mx-auto max-w-3xl space-y-6">
    <a href="{{ route('customer.domains.orders.show', $order, false) }}" class="text-sm font-bold text-blue-600">→ بازگشت به سفارش</a>
    <section class="domain-card">
        <div class="flex flex-wrap justify-between gap-4"><div><p class="domain-help">فاکتور {{ $invoice->number }}</p><h1 dir="ltr" class="mt-2 break-all text-2xl font-black">{{ $order->domain }}</h1><p class="domain-help mt-2">تاریخ صدور: {{ \App\Support\Jalali::format($invoice->issued_at) }}</p></div><span class="domain-badge self-start">تسویه شده</span></div>
        <p class="mt-5 text-sm">صادر شده برای: {{ ($order->billingRecipient()['name'] ?? '') }}</p>
        <dl class="mt-6 divide-y divide-slate-100 text-sm">@foreach($invoice->items as $item)<div class="flex flex-wrap justify-between gap-3 py-4"><dt>{{ $item->label }} <span class="text-slate-500">{{ $item->description }}</span></dt><dd class="font-bold">{{ $wallets->format($item->subtotal, $invoice->currency) }}</dd></div>@endforeach<div class="flex justify-between gap-3 py-5 text-lg font-black"><dt>جمع کل</dt><dd>{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</dd></div></dl>
        <p class="domain-help">این فاکتور مربوط به سفارش دامنه است. رسید شارژ کیف پول، پرداخت دیگری بابت این خرید محسوب نمی‌شود.</p>
    </section>
</div>
@endsection
