@extends('customer.layout')

@section('title', 'انتقال به درگاه ملت')
@section('header_title', 'انتقال به درگاه پرداخت')
@section('header_subtitle', 'در حال اتصال امن به درگاه پرداخت ملت')

@php
    $activeNav = 'wallet';
    $payload = $payment->gateway_payload ?? [];
@endphp

@section('content')
    <section class="mx-auto max-w-3xl rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
            <div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-[#2563EB]">Mellat</span>
                <h2 class="mt-4 text-2xl font-black text-slate-950">پرداخت آماده است</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">پس از بازگشت موفق از بانک، کیف پول شما به مبلغ پرداخت شده شارژ می‌شود.</p>
            </div>
            <div class="rounded-2xl bg-slate-50 px-5 py-4 text-right">
                <p class="text-xs font-black text-slate-500">شناسه پرداخت</p>
                <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $payment->authority }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">مبلغ</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $wallets->format($payment->amount) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">درگاه</p>
                <p class="mt-3 text-xl font-black text-slate-950">ملت</p>
            </div>
            <div class="rounded-2xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">موجودی فعلی</p>
                <p class="mt-3 text-xl font-black {{ $wallet->balance < 0 ? 'text-rose-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
            </div>
        </div>

        <form id="mellat-redirect-form" method="POST" action="{{ $payload['redirect_url'] ?? '' }}" class="mt-8">
            <input type="hidden" name="RefId" value="{{ $payment->authority }}">
            <button class="inline-flex w-full items-center justify-center rounded-2xl bg-[#2563EB] px-5 py-3 text-sm font-black text-white transition hover:bg-[#1d4ed8]">ورود به درگاه ملت</button>
        </form>
    </section>

    <script>
        window.addEventListener('load', () => {
            document.getElementById('mellat-redirect-form')?.submit();
        });
    </script>
@endsection
