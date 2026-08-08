@extends('customer.layout')

@section('title', 'صورتحساب‌ها و پرداخت‌ها')
@section('header_title', 'صورتحساب‌ها و پرداخت‌ها')
@section('header_subtitle', 'صورتحساب مصرف خدمات و رسید پرداخت‌های کیف پول')

@php
    $activeNav = 'invoices';
    $invoiceCount = $invoiceTotal + $receiptTotal;
@endphp

@section('content')
    <nav aria-label="نوع سند مالی" class="inline-flex w-full rounded-2xl border border-slate-200 bg-slate-100 p-1 sm:w-auto">
        <a
            href="{{ route('customer.invoices.index', ['tab' => 'usage'], false) }}"
            @if ($activeTab === 'usage') aria-current="page" @endif
            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl px-5 text-sm font-black transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] sm:flex-none {{ $activeTab === 'usage' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-950' }}"
        >
            صورتحساب‌های مصرف
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $invoiceTotal }}</span>
        </a>
        <a
            href="{{ route('customer.invoices.index', ['tab' => 'receipts'], false) }}"
            @if ($activeTab === 'receipts') aria-current="page" @endif
            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl px-5 text-sm font-black transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] sm:flex-none {{ $activeTab === 'receipts' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-950' }}"
        >
            رسیدهای پرداخت
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $receiptTotal }}</span>
        </a>
    </nav>

    @if ($activeTab === 'usage')
        <section class="mt-6 grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">تعداد صورتحساب‌ها</p>
                <p class="mt-3 text-2xl font-black text-slate-950">{{ $invoiceTotal }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">آخرین مبلغ</p>
                <p class="mt-3 text-2xl font-black text-slate-950">{{ $latestInvoice ? $wallets->format($latestInvoice->total_amount, $latestInvoice->currency) : '—' }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">نحوه تسویه</p>
                <p class="mt-3 text-2xl font-black text-emerald-600">از کیف پول</p>
            </article>
        </section>

        <section class="mt-6 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="border-b border-slate-200 p-5">
                <h2 class="text-lg font-black text-slate-950">بایگانی صورتحساب‌های مصرف</h2>
                <p class="mt-1 text-sm text-slate-500">جمع‌بندی برداشت‌های مصرف خدمات در هر ماه.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-black text-slate-950" dir="ltr">{{ $invoice->number }}</p>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-black text-emerald-700">{{ match ($invoice->status) { 'paid', 'issued' => 'تسویه‌شده', 'cancelled' => 'لغوشده', default => 'در حال بررسی' } }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">بازه {{ \App\Support\Jalali::format($invoice->period_start, 'Y/m/d') }} تا {{ \App\Support\Jalali::format($invoice->period_end, 'Y/m/d') }} · صدور {{ \App\Support\Jalali::format($invoice->issued_at) }}</p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <div class="sm:text-left">
                                <p class="text-xs font-black text-slate-500">مبلغ کل</p>
                                <p class="mt-1 text-lg font-black text-slate-950">{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</p>
                            </div>
                            <a href="{{ route('customer.invoices.show', $invoice, false) }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-[#2563EB] px-4 text-sm font-black text-white transition hover:bg-[#1d4ed8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده جزئیات صورتحساب</a>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center">
                        <p class="font-black text-slate-800">هنوز صورتحساب مصرفی صادر نشده است</p>
                        <p class="mt-2 text-sm text-slate-500">پس از پایان دوره، خلاصه مصرف خدمات در این بخش نمایش داده می‌شود.</p>
                    </div>
                @endforelse
            </div>

            @if ($invoices->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $invoices->links() }}</div>
            @endif
        </section>
    @else
        <section class="mt-6 grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">تعداد رسیدها</p>
                <p class="mt-3 text-2xl font-black text-slate-950">{{ $receiptTotal }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">آخرین پرداخت</p>
                <p class="mt-3 text-2xl font-black text-slate-950">{{ $latestReceipt ? $wallets->format($latestReceipt->amount, $latestReceipt->currency) : '—' }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">وضعیت</p>
                <p class="mt-3 text-2xl font-black text-emerald-600">پرداخت موفق</p>
            </article>
        </section>

        <section class="mt-6 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="border-b border-slate-200 p-5">
                <h2 class="text-lg font-black text-slate-950">رسیدهای پرداخت کیف پول</h2>
                <p class="mt-1 text-sm text-slate-500">رسید هر پرداخت موفق بلافاصله پس از افزایش موجودی آماده است.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($receipts as $payment)
                    <article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-black text-slate-950" dir="ltr">{{ $payment->receiptNumber() }}</p>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-black text-emerald-700">پرداخت موفق</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">{{ \App\Support\Jalali::format($payment->paid_at) }} · {{ $gatewayLabels[$payment->provider] ?? $payment->provider }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $payment->provider_reference ?: $payment->authority }}</p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <div class="sm:text-left">
                                <p class="text-xs font-black text-slate-500">مبلغ پرداخت</p>
                                <p class="mt-1 text-lg font-black text-slate-950">{{ $wallets->format($payment->amount, $payment->currency) }}</p>
                            </div>
                            <a href="{{ route('customer.payments.receipt.show', $payment, false) }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-[#2563EB] px-4 text-sm font-black text-white transition hover:bg-[#1d4ed8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مشاهده رسید پرداخت</a>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center">
                        <p class="font-black text-slate-800">هنوز رسید پرداختی ندارید</p>
                        <p class="mt-2 text-sm text-slate-500">پس از نخستین پرداخت موفق، رسید آن در این بخش نمایش داده می‌شود.</p>
                        <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">افزایش موجودی کیف پول</a>
                    </div>
                @endforelse
            </div>

            @if ($receipts->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $receipts->links() }}</div>
            @endif
        </section>
    @endif
@endsection
