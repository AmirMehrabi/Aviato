@extends('customer.layout')
@section('title', 'دی‌ان‌اس ابری')
@section('header_title', 'دی‌ان‌اس ابری')
@section('header_subtitle', 'دامنه‌ها و رکوردهای DNS فضای کاری شما')
@php($activeNav = 'cloud-dns')
@section('content')
<div class="space-y-6">
    @include('cloud-dns.messages')
    <section class="rounded-2xl border border-blue-100 bg-blue-50/50 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-xs font-black tracking-widest text-[#0069FF]">دی‌ان‌اس ابری</p><h2 class="mt-2 text-2xl font-black">DNS دامنه‌های شما، در یک جا</h2><p class="mt-3 text-sm leading-7 text-slate-600">دامنه را اضافه کنید، مالکیت را تأیید کنید و رکوردها را پیش از تغییر نیم‌سرور آماده کنید.</p></div>
            <span class="rounded-full border border-blue-100 bg-white px-4 py-2 text-sm font-bold text-[#0069FF]">طرح رایگان · {{ $zones->total() }} / {{ config('cloud-dns.plans.free.zone_limit') }} دامنه</span>
        </div>
        <div class="mt-5 flex flex-wrap gap-3" dir="ltr">@foreach(config('cloud-dns.nameservers') as $ns)<code class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm">{{ $ns }}</code>@endforeach</div>
    </section>
    @if($canManage && config('cloud-dns.enabled'))
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">افزودن دامنه</h2><form method="POST" action="{{ route('customer.cloud-dns.store', [], false) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">@csrf<label class="flex-1"><span class="sr-only">نام دامنه</span><input name="name" value="{{ old('name') }}" required maxlength="253" placeholder="example.ir" dir="ltr" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-[#0069FF] focus:outline-none"></label><button class="rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white hover:bg-[#0050D0]">افزودن دامنه</button></form></section>
    @elseif(! config('cloud-dns.enabled'))
        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">دی‌ان‌اس ابری در حال آماده‌سازی است.</p>
    @endif
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-5"><h2 class="font-black">دامنه‌های {{ $activeProject->name }}</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-right text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4">دامنه</th><th class="p-4">مالکیت</th><th class="p-4">واگذاری NS</th><th class="p-4">انتشار رکوردها</th><th class="p-4">مجموعه رکورد</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($zones as $zone)<tr class="hover:bg-slate-50"><td class="p-4"><a href="{{ route('customer.cloud-dns.show', $zone, false) }}" class="font-mono font-bold text-[#0069FF]" dir="ltr">{{ $zone->name }}</a></td><td class="p-4">{{ $zone->verified_at ? 'تأییدشده' : 'نیازمند TXT' }}</td><td class="p-4">{{ $zone->delegation_status === 'active' ? 'فعال' : 'در انتظار بررسی' }}</td><td class="p-4">@include('cloud-dns.status')</td><td class="p-4">{{ $zone->record_sets_count }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-black text-slate-700">اولین دامنه خود را اضافه کنید</p><p class="mt-2 text-sm text-slate-500">رکوردهای وب‌سایت، ایمیل و تأیید سرویس‌ها را اینجا مدیریت کنید.</p></td></tr>@endforelse</tbody></table></div>
    </section>
    {{ $zones->links() }}
</div>
@endsection
