@extends('customer.layout')
@section('title', 'فاکتور دامنه')
@section('header_title', 'فاکتور دامنه')
@section('header_subtitle', 'جزئیات صورتحساب ثبت یا تمدید دامنه')
@section('breadcrumbs')
    @include('customer.domains.partials.breadcrumbs', [
        'parents' => [['دامنه‌ها', route('customer.domains.index', [], false)], ['سفارش‌ها', route('customer.domains.index', ['view' => 'orders'], false)], ['سفارش دامنه', route('customer.domains.orders.show', $order, false)]],
        'current' => $invoice->number,
        'ltr' => true,
    ])
@endsection
@php($activeNav = 'domains')
@section('content')
    @include('customer.corporate.billing-recipient', ['recipient' => $order->billingRecipient()])
<div class="space-y-6">
    <section class="domain-card">
        <div class="flex flex-wrap justify-between gap-4"><div><p class="domain-help">فاکتور {{ $invoice->number }}</p><h2 dir="ltr" class="mt-2 break-all text-2xl font-black">{{ $order->domain }}</h2><p class="domain-help mt-2">تاریخ صدور: {{ \App\Support\Jalali::format($invoice->issued_at) }}</p></div><span class="domain-badge self-start">تسویه شده</span></div>
        <p class="mt-5 text-sm">صادر شده برای: {{ ($order->billingRecipient()['name'] ?? '') }}</p>
        <dl class="mt-6 divide-y divide-slate-100 text-sm">@foreach($invoice->items as $item)<div class="flex flex-wrap justify-between gap-3 py-4"><dt>{{ $item->label }} <span class="text-slate-500">{{ $item->description }}</span></dt><dd class="font-bold">{{ $wallets->format($item->subtotal, $invoice->currency) }}</dd></div>@endforeach<div class="flex justify-between gap-3 py-5 text-lg font-black"><dt>جمع کل</dt><dd>{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</dd></div></dl>
        <p class="domain-help">این فاکتور مربوط به سفارش دامنه است. رسید شارژ کیف پول، پرداخت دیگری بابت این خرید محسوب نمی‌شود.</p>
    </section>
</div>
@endsection
