@extends('customer.layout')
@section('primary_actions_in_content', '1')
@section('own_messages', '1')
@section('title', 'صورتحساب‌های کولوکیشن')
@section('header_title', 'صورتحساب‌های کولوکیشن')
@section('header_subtitle', 'همه پرداخت‌های دوره‌ای و ترافیک در یکجا')
@php($activeNav = 'colocation')
@section('content')
<div class="mx-auto max-w-5xl">
    @include('customer.colocation._navigation')
    <section class="colo-card">@forelse($invoices as $invoice)<p class="pt-4 text-xs text-slate-500">{{ $invoice->colocationService->name }}</p>@include('customer.colocation._invoice-row')@empty<div class="py-10 text-center"><h2 class="text-lg font-bold">هنوز صورتحساب کولوکیشن ندارید</h2><p class="mt-3 text-sm text-slate-500">پس از انتخاب پلن، اولین صورتحساب اینجا نمایش داده می‌شود.</p><a class="colo-link mt-5 inline-block" href="{{ route('customer.colocation.index', [], false) }}">مشاهده پلن‌ها</a></div>@endforelse</section>
    <div class="mt-5">{{ $invoices->links() }}</div>
</div>
@endsection
