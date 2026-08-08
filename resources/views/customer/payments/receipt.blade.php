@extends('customer.layout')

@section('title', 'رسید پرداخت '.$payment->receiptNumber())
@section('header_title', 'رسید پرداخت کیف پول')
@section('header_subtitle', 'جزئیات پرداخت موفق و افزایش موجودی کیف پول')

@php
    $activeNav = 'invoices';
@endphp

@section('content')
    <style>
        @page { size: A4; margin: 12mm; }
        @media print {
            aside, main > header, .no-print { display: none !important; }
            body, main { background: #fff !important; }
            main { width: 100% !important; }
            .receipt-sheet { width: 100% !important; max-width: none !important; border: 0 !important; box-shadow: none !important; margin: 0 !important; }
            .receipt-print-area { padding: 0 !important; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    <div class="no-print mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('customer.invoices.index', ['tab' => 'receipts'], false) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#F2F8FF] hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">بازگشت به رسیدهای پرداخت</a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0069FF] px-5 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">چاپ یا ذخیره PDF</button>
    </div>

    <article class="receipt-sheet mx-auto max-w-5xl overflow-hidden border border-slate-300 bg-white shadow-sm shadow-slate-200/60">
        <div class="receipt-print-area p-5 sm:p-8 lg:p-10">
            <header class="grid gap-6 border-b-2 border-[#0069FF] pb-6 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                <div class="flex items-center gap-4">
                    <img src="{{ $company['logo_url'] }}" alt="لوگوی {{ $company['name'] }}" class="h-14 w-36 object-contain object-right sm:h-16 sm:w-40">
                    <p class="hidden max-w-48 text-sm font-black leading-6 text-slate-950 lg:block">{{ $company['name'] }}</p>
                </div>

                <div class="text-center">
                    <p class="text-xl font-black text-slate-950">رسید پرداخت کیف پول</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">نسخه مشتری</p>
                </div>

                <dl class="text-sm sm:text-left">
                    <div class="flex items-center gap-2 sm:justify-end">
                        <dt class="font-bold text-slate-500">شماره رسید:</dt>
                        <dd class="font-mono font-black text-slate-950" dir="ltr">{{ $payment->receiptNumber() }}</dd>
                    </div>
                    <div class="mt-2 flex items-center gap-2 sm:justify-end">
                        <dt class="font-bold text-slate-500">تاریخ صدور:</dt>
                        <dd class="font-black text-slate-950" dir="ltr">{{ \App\Support\Jalali::format($payment->paid_at) }}</dd>
                    </div>
                </dl>
            </header>

            <div class="mt-6 grid border border-slate-300 sm:grid-cols-2">
                <section aria-labelledby="seller-heading" class="p-4 sm:border-l sm:border-slate-300 sm:p-5">
                    <h1 id="seller-heading" class="border-b border-slate-200 pb-2 text-sm font-black text-[#0069FF]">مشخصات صادرکننده</h1>
                    <dl class="mt-3 space-y-2 text-sm leading-6">
                        <div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">نام:</dt><dd class="font-black text-slate-950">{{ $company['name'] }}</dd></div>
                        @if ($company['national_id'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">شناسه ملی:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $company['national_id'] }}</dd></div>@endif
                        @if ($company['registration_number'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">شماره ثبت:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $company['registration_number'] }}</dd></div>@endif
                        @if ($company['economic_code'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">کد اقتصادی:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $company['economic_code'] }}</dd></div>@endif
                        @if ($company['phone'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">تلفن:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $company['phone'] }}</dd></div>@endif
                        @if ($company['email'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">ایمیل:</dt><dd class="break-all font-bold text-slate-800" dir="ltr">{{ $company['email'] }}</dd></div>@endif
                        @if ($company['address'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">نشانی:</dt><dd class="font-bold text-slate-800">{{ $company['address'] }}</dd></div>@endif
                        @if ($company['postal_code'])<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">کد پستی:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $company['postal_code'] }}</dd></div>@endif
                    </dl>
                </section>

                <section aria-labelledby="customer-heading" class="border-t border-slate-300 p-4 sm:border-t-0 sm:p-5">
                    <h2 id="customer-heading" class="border-b border-slate-200 pb-2 text-sm font-black text-[#0069FF]">مشخصات دارنده کیف پول</h2>
                    <dl class="mt-3 space-y-2 text-sm leading-6">
                        <div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">نام:</dt><dd class="font-black text-slate-950">{{ $payment->customer->name }}</dd></div>
                        @if ($payment->customer->national_code)<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">کد ملی:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $payment->customer->national_code }}</dd></div>@endif
                        @if ($payment->customer->phone)<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">شماره همراه:</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $payment->customer->phone }}</dd></div>@endif
                        @if ($payment->customer->email)<div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-500">ایمیل:</dt><dd class="break-all font-bold text-slate-800" dir="ltr">{{ $payment->customer->email }}</dd></div>@endif
                    </dl>
                </section>
            </div>

            <section aria-labelledby="payment-heading" class="mt-6">
                <h2 id="payment-heading" class="mb-2 text-sm font-black text-slate-950">اطلاعات پرداخت</h2>
                <div class="overflow-x-auto border border-slate-300">
                    <table class="min-w-[720px] w-full border-collapse text-center text-sm">
                        <thead class="bg-slate-100 text-xs font-black text-slate-600">
                            <tr>
                                <th class="border-l border-slate-300 px-3 py-3">تاریخ پرداخت</th>
                                <th class="border-l border-slate-300 px-3 py-3">شماره مرجع درگاه</th>
                                <th class="px-3 py-3">شناسه پرداخت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold text-slate-900">
                                <td class="border-l border-t border-slate-300 px-3 py-3" dir="ltr">{{ \App\Support\Jalali::format($payment->paid_at) }}</td>
                                <td class="border-l border-t border-slate-300 px-3 py-3 font-mono" dir="ltr">{{ $payment->provider_reference ?: '—' }}</td>
                                <td class="border-t border-slate-300 px-3 py-3 font-mono" dir="ltr">{{ $payment->authority ?: '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="receipt-items-heading" class="mt-6">
                <h2 id="receipt-items-heading" class="sr-only">شرح پرداخت</h2>
                <div class="overflow-x-auto border border-slate-300">
                    <table class="min-w-[640px] w-full border-collapse text-sm">
                        <thead class="bg-[#EAF3FF] text-xs font-black text-[#31527F]">
                            <tr>
                                <th class="w-16 border-l border-slate-300 px-3 py-3 text-center">ردیف</th>
                                <th class="border-l border-slate-300 px-4 py-3 text-right">شرح</th>
                                <th class="w-52 px-4 py-3 text-left">مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold text-slate-900">
                                <td class="border-l border-t border-slate-300 px-3 py-4 text-center">۱</td>
                                <td class="border-l border-t border-slate-300 px-4 py-4">افزایش موجودی کیف پول</td>
                                <td class="border-t border-slate-300 px-4 py-4 text-left">{{ $wallets->format($payment->amount, $payment->currency) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-400 bg-slate-50">
                                <td colspan="2" class="px-4 py-4 text-left font-black text-slate-700">جمع مبلغ پرداخت‌شده</td>
                                <td class="px-4 py-4 text-left text-lg font-black text-[#0069FF]">{{ $wallets->format($payment->amount, $payment->currency) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <footer class="mt-8 border-t border-slate-300 pt-4 text-xs font-bold leading-6 text-slate-500">
                <p>این سند رسید افزایش موجودی کیف پول است و جایگزین صورتحساب مصرف خدمات نیست. صورتحساب مصرف به‌صورت ماهانه و جداگانه صادر می‌شود.</p>
                <p class="mt-1">برای پیگیری پرداخت، شماره رسید و شماره مرجع درگاه را در اختیار پشتیبانی قرار دهید.</p>
            </footer>
        </div>
    </article>
@endsection
