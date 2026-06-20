@extends('customer.layout')

@section('title', 'انتقال به درگاه پرداخت')
@section('header_title', 'در حال انتقال به درگاه پرداخت')
@section('header_subtitle', 'برای ادامه پرداخت به صفحه امن سرویس پرداخت منتقل می‌شوید')

@php
    $activeNav = 'wallet';
    $payload = $payment->gateway_payload ?? [];
@endphp

@section('content')
    <section class="mx-auto max-w-xl rounded-[28px] border border-slate-200 bg-white p-7 text-center shadow-sm shadow-slate-200/60">
        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-[#2563EB]">{{ $gatewayLabel }}</span>
        <h1 class="mt-5 text-2xl font-black text-slate-950">انتقال به درگاه پرداخت</h1>
        <p class="mt-3 text-sm leading-7 text-slate-500">در صورت منتقل نشدن خودکار، دکمه زیر را انتخاب کنید.</p>

        <div class="mt-6 rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-600">
            مبلغ پرداخت: <span class="font-black text-slate-950">{{ $wallets->format($payment->amount) }}</span>
        </div>

        <a id="gateway-redirect-link" href="{{ $payload['redirect_url'] ?? '#' }}" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-[#2563EB] px-5 py-3 text-sm font-black text-white transition hover:bg-[#1d4ed8]">
            ادامه پرداخت با {{ $gatewayLabel }}
        </a>
    </section>

    @if (! empty($payload['redirect_url']))
        <script>
            window.setTimeout(() => {
                window.location.assign(@json($payload['redirect_url']));
            }, 800);
        </script>
    @endif
@endsection
