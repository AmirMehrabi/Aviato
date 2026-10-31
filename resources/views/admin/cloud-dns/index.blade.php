@extends('layouts.admin')
@section('title', 'مدیریت DNS')
@section('content')
<div class="space-y-6 px-4 py-6 md:px-8 lg:px-10">
    @include('cloud-dns.messages')
    <div><h1 class="text-2xl font-black">مدیریت DNS</h1><p class="mt-2 text-sm text-slate-500">دامنه‌های مشتریان و وضعیت ارتباط با سرورهای DNS</p></div>
    @include('admin.cloud-dns.rollout')
    <div class="grid gap-4 sm:grid-cols-2">@foreach($health as $node => $state)<section class="rounded-xl border border-slate-200 bg-white p-5"><p class="font-mono font-bold" dir="ltr">{{ $node === 'primary' ? 'NS1 · Primary' : 'NS2 · Secondary' }}</p><p class="mt-3 text-sm text-slate-600">{{ $state }}</p><p class="mt-2 text-xs text-slate-400">ارتباط API · وضعیت انتشار هر دامنه در جزئیات آن نمایش داده می‌شود</p></section>@endforeach</div>
    <form method="GET" class="flex gap-3"><label class="flex-1"><span class="sr-only">جستجو</span><input name="search" value="{{ $search }}" placeholder="دامنه، فضای کاری یا ایمیل مالک" class="w-full rounded-xl border border-slate-300 px-4 py-3"></label><button class="rounded-xl bg-[#0069FF] px-6 text-sm font-bold text-white">جستجو</button></form>
    <div class="overflow-x-auto rounded-xl border border-slate-200"><table class="w-full text-right text-sm"><thead class="bg-slate-50"><tr><th class="p-4">دامنه</th><th class="p-4">مشتری / فضای کاری</th><th class="p-4">انتشار</th><th class="p-4">واگذاری NS</th><th class="p-4">نسخه</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($zones as $zone)<tr><td class="p-4"><a class="font-mono font-bold text-[#0069FF]" href="{{ route('admin.cloud-dns.show', $zone) }}" dir="ltr">{{ $zone->name }}</a></td><td class="p-4">{{ $zone->project->owner?->name }}<p class="mt-1 text-xs text-slate-500">{{ $zone->project->name }}</p></td><td class="p-4">@include('cloud-dns.status')</td><td class="p-4">{{ $zone->delegation_status === 'active' ? 'فعال' : 'در انتظار' }}</td><td class="p-4" dir="ltr">{{ $zone->synced_revision }} / {{ $zone->revision }}</td></tr>@empty<tr><td colspan="5" class="p-12 text-center text-slate-500">دامنه‌ای پیدا نشد.</td></tr>@endforelse</tbody></table></div>
    {{ $zones->links() }}
</div>
@endsection
