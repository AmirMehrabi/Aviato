@extends('customer.layout')

@section('title', 'رسید پرداخت '.$payment->receiptNumber())
@section('header_title', 'رسید پرداخت کیف پول')
@section('header_subtitle', 'جزئیات پرداخت موفق و افزایش موجودی کیف پول')

@php
    $activeNav = 'invoices';
@endphp

@section('content')
    <style>
        @media print {
            aside, main > header, .no-print { display: none !important; }
            body, main { background: #fff !important; }
            main { width: 100% !important; }
            .receipt-sheet { border: 0 !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
        }
    </style>

    <div class="no-print mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('customer.invoices.index', ['tab' => 'receipts'], false) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#F2F8FF] hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">بازگشت به رسیدهای پرداخت</a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">چاپ یا ذخیره PDF</button>
    </div>

    <article class="receipt-sheet mx-auto max-w-4xl rounded-[32px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 sm:p-8 lg:p-10">
        <header class="flex flex-col gap-6 border-b border-slate-200 pb-7 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-[#0069FF]">رسید پرداخت و شارژ کیف پول</p>
                <h1 class="mt-3 text-2xl font-black text-slate-950" dir="ltr">{{ $payment->receiptNumber() }}</h1>
                <p class="mt-2 text-sm text-slate-500">این رسید، تأیید پرداخت موفق و افزایش موجودی کیف پول است.</p>
            </div>
            <div class="w-fit rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">
                <span aria-hidden="true">✓</span>
                پرداخت موفق
            </div>
        </header>

        <section aria-labelledby="receipt-payment-heading" class="mt-7">
            <h2 id="receipt-payment-heading" class="text-base font-black text-slate-950">اطلاعات پرداخت</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">مبلغ پرداخت</dt>
                    <dd class="mt-2 text-xl font-black text-slate-950">{{ $wallets->format($payment->amount, $payment->currency) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">تاریخ پرداخت</dt>
                    <dd class="mt-2 font-black text-slate-950" dir="ltr">{{ \App\Support\Jalali::format($payment->paid_at) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">درگاه پرداخت</dt>
                    <dd class="mt-2 font-black text-slate-950">{{ $gatewayLabel }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">واحد ثبت‌شده</dt>
                    <dd class="mt-2 font-black text-slate-950" dir="ltr">{{ $payment->currency }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">شماره مرجع درگاه</dt>
                    <dd class="mt-2 break-all font-mono text-sm font-black text-slate-950" dir="ltr">{{ $payment->provider_reference ?: '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-black text-slate-500">شناسه پرداخت</dt>
                    <dd class="mt-2 break-all font-mono text-sm font-black text-slate-950" dir="ltr">{{ $payment->authority ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="receipt-owner-heading" class="mt-7 border-t border-slate-200 pt-7">
            <h2 id="receipt-owner-heading" class="text-base font-black text-slate-950">دارنده کیف پول</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <dt class="text-xs font-black text-slate-500">نام</dt>
                    <dd class="mt-2 font-black text-slate-950">{{ $payment->customer->name }}</dd>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <dt class="text-xs font-black text-slate-500">اطلاعات تماس</dt>
                    <dd class="mt-2 break-all font-black text-slate-950" dir="ltr">{{ $payment->customer->email ?: $payment->customer->phone ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <footer class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-bold leading-7 text-slate-600">
            این سند رسید پرداخت کیف پول است. جزئیات مصرف خدمات در صورتحساب‌های ماهانه نمایش داده می‌شود.
        </footer>
    </article>
@endsection
