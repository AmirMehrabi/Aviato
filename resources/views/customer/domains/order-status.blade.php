@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('title', 'وضعیت سفارش دامنه')
@section('header_title', 'وضعیت سفارش دامنه')
@section('header_subtitle', 'فضای کاری: '.$activeProject->name)
@php($activeNav = 'domains')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('customer.domains.index', [], false) }}" class="text-sm font-bold text-blue-600">→ بازگشت به دامنه‌ها</a>
    <section class="domain-card">
        <h1 dir="ltr" class="break-all text-2xl font-black">{{ $order->domain }}</h1>
        <p role="status" class="mt-5 font-bold text-blue-700">{{ $order->label() }}</p>
        <p class="domain-help mt-3">فضای کاری: {{ $activeProject->name }} · خریدار: {{ $order->customer->name }}</p>
        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $order->customer_message ?: (in_array($order->status, ['draft', 'awaiting_payment']) ? 'تکمیل مشخصات و پرداخت این سفارش بر عهده خریدار آن است. پس از ثبت، دامنه در همین فضای کاری نمایش داده می‌شود.' : ($order->isProcessing() ? 'سفارش در حال انجام است. نیازی به ثبت سفارش دوباره نیست؛ می‌توانید بعداً نتیجه را از همین صفحه ببینید.' : 'وضعیت سفارش در این صفحه نگهداری می‌شود.')) }}</p>
        <div class="mt-6 flex flex-wrap gap-3">
            @if($order->managed_domain_id)<a class="domain-primary" href="{{ route('customer.domains.show', $order->managedDomain, false) }}">مدیریت دامنه</a>@endif
            @if($order->invoice_id && $canViewDomainBilling)<a class="domain-secondary" href="{{ route('customer.domains.orders.invoice', $order, false) }}">مشاهده فاکتور</a>@endif
            @if($order->isProcessing())<a class="domain-secondary" href="{{ route('customer.domains.orders.show', $order, false) }}">بررسی دوباره وضعیت</a>@endif
        </div>
    </section>
</div>
@endsection
