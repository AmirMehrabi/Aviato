@extends('customer.layout')

@section('title', 'صورتحساب ها')
@section('header_title', 'صورتحساب های ماهانه')
@section('header_subtitle', 'گزارش تفصیلی مصرف ماهانه به تفکیک ماشین مجازی و برداشت های کیف پول')

@php
    $activeNav = 'invoices';
    $invoiceCount = $invoices->total();
@endphp

@section('content')
    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">تعداد صورتحساب ها</p>
            <p class="mt-3 text-2xl font-black text-slate-950">{{ $invoices->total() }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">آخرین مبلغ</p>
            <p class="mt-3 text-2xl font-black text-slate-950">{{ $latestInvoice ? $wallets->format($latestInvoice->total_amount) : '—' }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">نحوه تسویه</p>
            <p class="mt-3 text-2xl font-black text-emerald-600">از کیف پول</p>
        </article>
    </section>

    <section class="mt-6 rounded-[28px] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-black text-slate-950">بایگانی صورتحساب</h2>
            <p class="mt-1 text-sm text-slate-500">این صورتحساب ها خلاصه ای از برداشت های زنده PAYG از کیف پول شما هستند.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($invoices as $invoice)
                <article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-base font-black text-slate-950">{{ $invoice->number }}</p>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-black text-emerald-700">{{ match ($invoice->status) { 'paid', 'issued' => 'تسویه شده', 'cancelled' => 'لغو شده', default => 'در حال بررسی' } }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">بازه {{ $invoice->period_start->format('Y/m/d') }} تا {{ $invoice->period_end->format('Y/m/d') }} · صدور {{ $invoice->issued_at?->format('Y/m/d H:i') }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-left">
                            <p class="text-xs font-black text-slate-500">مبلغ کل</p>
                            <p class="mt-1 text-lg font-black text-slate-950">{{ $wallets->format($invoice->total_amount) }}</p>
                        </div>
                        <a href="{{ route('customer.invoices.show', $invoice, false) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#2563EB] px-4 py-3 text-sm font-black text-white transition hover:bg-[#1d4ed8]">مشاهده جزئیات</a>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">هنوز صورتحسابی برای این حساب صادر نشده است.</div>
            @endforelse
        </div>

        <div class="border-t border-slate-200 px-5 py-4">
            {{ $invoices->links() }}
        </div>
    </section>
@endsection
