@extends('customer.layout')

@section('title', 'جزئیات صورتحساب')
@section('header_title', 'جزئیات صورتحساب '.$invoice->number)
@section('header_subtitle', 'تفکیک کارکرد ماشین های مجازی و مبلغ برداشت شده از کیف پول در این دوره')

@php
    $activeNav = 'invoices';
@endphp

@section('content')
    <section class="rounded-[32px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">{{ $invoice->status }}</span>
                <h2 class="mt-4 text-2xl font-black text-slate-950">{{ $invoice->number }}</h2>
                <p class="mt-2 text-sm text-slate-500">بازه صورتحساب: {{ $invoice->period_start->format('Y/m/d') }} تا {{ $invoice->period_end->format('Y/m/d') }}</p>
                <p class="mt-1 text-sm text-slate-500">تاریخ صدور: {{ $invoice->issued_at?->format('Y/m/d H:i') }}</p>
            </div>
            <div class="rounded-3xl bg-slate-50 px-5 py-4 text-right">
                <p class="text-xs font-black text-slate-500">جمع کل</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ $wallets->format($invoice->total_amount) }}</p>
                <p class="mt-1 text-xs font-bold text-slate-500">این مبلغ طی ماه از کیف پول کسر شده است.</p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">جمع کارکرد</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $wallets->format($invoice->subtotal_amount) }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">برداشت از کیف پول</p>
                <p class="mt-3 text-xl font-black text-rose-600">{{ $wallets->format(-1 * $invoice->wallet_charged_amount) }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 p-5">
                <p class="text-xs font-black text-slate-500">تعداد آیتم ها</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $invoice->items->count() }}</p>
            </article>
        </div>

        <div class="mt-8 overflow-hidden rounded-3xl border border-slate-200">
            <table class="min-w-full text-right text-sm">
                <thead class="bg-slate-50 text-xs font-black text-slate-500">
                    <tr>
                        <th class="px-5 py-4">ماشین مجازی</th>
                        <th class="px-5 py-4">شرح</th>
                        <th class="px-5 py-4">ساعت کارکرد</th>
                        <th class="px-5 py-4">نرخ ساعتی</th>
                        <th class="px-5 py-4">جمع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="px-5 py-4 font-black text-slate-950">{{ $item->label }}</td>
                            <td class="px-5 py-4 text-sm leading-7 text-slate-600">{{ $item->description }}</td>
                            <td class="px-5 py-4 font-bold text-slate-700">{{ number_format((float) $item->quantity, 2) }} ساعت</td>
                            <td class="px-5 py-4 font-bold text-slate-700">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-5 py-4 font-black text-slate-950">{{ $wallets->format($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-7 text-slate-600">
            این صورتحساب برای بایگانی و بررسی صادر شده است. در این نسخه، مبلغ ها در طول ماه به صورت زنده از کیف پول کسر می شوند و این صفحه جمع بندی همان تراکنش ها را نشان می دهد.
        </div>
    </section>
@endsection
