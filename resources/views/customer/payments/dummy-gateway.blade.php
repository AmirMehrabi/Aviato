@extends('customer.layout')

@section('title', 'درگاه آزمایشی')
@section('header_title', 'درگاه پرداخت آزمایشی')
@section('header_subtitle', 'این صفحه شبیه ساز پرداخت است و پس از تایید، کیف پول بلافاصله شارژ می شود')

@php
    $activeNav = 'wallet';
@endphp

@section('content')
    <section class="mx-auto max-w-3xl rounded-[32px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
            <div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-[#2563EB]">Dummy Gateway</span>
                <h2 class="mt-4 text-2xl font-black text-slate-950">آماده ثبت پرداخت آزمایشی</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">پس از تایید این مرحله، رکورد پرداخت با وضعیت موفق ثبت می شود و یک تراکنش شارژ در کیف پول شما ساخته خواهد شد.</p>
            </div>
            <div class="rounded-3xl bg-slate-50 px-5 py-4 text-right">
                <p class="text-xs font-black text-slate-500">شناسه پرداخت</p>
                <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $payment->authority }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">مبلغ</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $wallets->format($payment->amount) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">ارائه دهنده</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ strtoupper($payment->provider) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">موجودی فعلی</p>
                <p class="mt-3 text-xl font-black {{ $wallet->balance < 0 ? 'text-rose-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
            </div>
        </div>

        <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-7 text-slate-600">
            این مرحله طوری طراحی شده که بعدا بتوان یک درگاه واقعی را جایگزین آن کرد. در حال حاضر با کلیک روی دکمه زیر، پرداخت به صورت موفق نهایی می شود.
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <form method="POST" action="{{ route('customer.wallet.payments.gateway.store', $payment, false) }}" class="flex-1">
                @csrf
                <button class="inline-flex w-full items-center justify-center rounded-2xl bg-[#2563EB] px-5 py-3 text-sm font-black text-white transition hover:bg-[#1d4ed8]">تایید و ثبت پرداخت موفق</button>
            </form>
            <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت به کیف پول</a>
        </div>
    </section>
@endsection
