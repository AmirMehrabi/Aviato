@extends('layouts.admin')

@section('title', 'گزارش رخدادها')

@section('content')
<div class="mx-auto max-w-7xl p-4 md:p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><p class="text-sm font-black text-[#0069FF]">OPERATIONS</p><h1 class="mt-2 text-3xl font-black text-slate-950">گزارش رخدادها</h1><p class="mt-2 text-sm text-slate-500">ثبت و انتشار گزارش رخدادها برای مشتریان.</p></div><a href="{{ route('admin.incidents.create') }}" class="inline-flex w-fit rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">رخداد جدید</a></div>
    @if (session('status'))<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
    <div class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-right text-sm"><thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500"><tr><x-admin.sortable-heading label="رخداد" column="title" :sort="$sort" /><x-admin.sortable-heading label="سرویس" column="affected_service" :sort="$sort" /><x-admin.sortable-heading label="وضعیت" column="status" :sort="$sort" /><x-admin.sortable-heading label="انتشار" column="is_published" :sort="$sort" /><th class="px-5 py-4"></th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($incidents as $incident)<tr><td class="px-5 py-4"><p class="font-black text-slate-950">{{ $incident->title }}</p><p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $incident->started_at->format('Y/m/d H:i') }}</p></td><td class="px-5 py-4 text-slate-600">{{ $incident->affected_service }}</td><td class="px-5 py-4"><x-admin.status-badge :value="$incident->status" :label="$incident->statusLabel()" /></td><td class="px-5 py-4"><x-admin.status-badge :value="$incident->is_published ? 'published' : 'draft'" /></td><td class="px-5 py-4"><x-admin.icon-action :href="route('admin.incidents.edit', $incident)" label="مدیریت رخداد" icon="settings" tone="primary" /></td></tr>@empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">هنوز رخدادی ثبت نشده.</td></tr>@endforelse</tbody></table></div></div>
    <div class="mt-6">{{ $incidents->links() }}</div>
</div>
@endsection
