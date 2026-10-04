@extends('customer.layout')

@section('title', 'کیف پول')
@section('header_title', 'کیف پول')

@php
    $activeNav = 'wallet';
    $canTopUp = (bool) $canTopUp;
    $initialGateway = (string) old('gateway', $defaultPaymentGateway);
    $initialAmount = (string) old('amount_toman', '');
    $tabItems = [
        ['key' => 'overview', 'label' => 'نمای کلی'],
        ['key' => 'top-up', 'label' => 'افزایش موجودی'],
        ['key' => 'transactions', 'label' => 'تراکنش‌ها'],
    ];
@endphp

@section('full_width_header')
    @include('customer.wallet.partials.header')
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
    @if (session('promotion_success'))
        <div class="mb-6 flex flex-col gap-4 rounded-[28px] border border-emerald-200 bg-emerald-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div><p class="text-xs font-black text-emerald-700">هدیه فعال شد</p><h2 class="mt-2 text-2xl font-black text-slate-950">حالا پروژه‌ات را بالا بیاور.</h2><p class="mt-2 text-sm font-bold text-slate-600">پلن، موقعیت و سیستم‌عامل را انتخاب کنید.</p></div>
            <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-6 font-black text-white">ساخت اولین سرور</a>
        </div>
    @endif
    @if ($paymentNotice)
        <div role="status" class="mb-6 flex flex-col gap-3 rounded-2xl border px-5 py-4 text-sm font-bold leading-7 sm:flex-row sm:items-center sm:justify-between {{ $paymentNotice['tone'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($paymentNotice['tone'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800') }}">
            <span>{{ $paymentNotice['message'] }}</span>
            @if (! empty($paymentNotice['promotion_success']))
                <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-black text-white">ساخت سرور</a>
            @elseif (! empty($paymentNotice['receipt_url']))
                <a href="{{ $paymentNotice['receipt_url'] }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-black text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">مشاهده رسید پرداخت</a>
            @endif
        </div>
    @endif

    @include('customer.wallet.tabs.'.$selectedTab)
    </div>
@endsection
