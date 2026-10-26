@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('title', 'فاکتورهای دامنه')
@section('header_title', 'فاکتورهای دامنه')
@section('header_subtitle', 'فضای کاری: '.$activeProject->name)
@php($activeNav = 'invoices')
@section('content')
<div class="mx-auto max-w-4xl space-y-6"><nav class="flex flex-wrap gap-3" aria-label="نوع سند مالی"><a class="domain-secondary" href="{{ route('customer.invoices.index', [], false) }}">صورتحساب مصرف و رسید پرداخت</a><a class="domain-primary" aria-current="page" href="{{ route('customer.invoices.index', ['tab'=>'domains'], false) }}">فاکتورهای دامنه</a></nav><section class="domain-card"><h1 class="domain-heading">بایگانی ثبت و تمدید دامنه</h1><div class="mt-5 divide-y divide-slate-100">@forelse($invoices as $invoice)<a href="{{ route('customer.invoices.show', $invoice, false) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"><div><p class="font-bold" dir="ltr">{{ $invoice->number }}</p><p class="domain-help">{{ \App\Support\Jalali::format($invoice->issued_at) }}</p></div><span class="font-black">{{ $wallets->format($invoice->total_amount, $invoice->currency) }}</span><span class="text-blue-600">مشاهده فاکتور ←</span></a>@empty<p class="py-6 text-sm text-slate-500">پس از تکمیل ثبت یا تمدید دامنه، فاکتور اینجا نمایش داده می‌شود.</p>@endforelse</div><div class="mt-4">{{ $invoices->links() }}</div></section></div>
@endsection
