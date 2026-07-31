@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'باندل‌ها')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
@if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-2xl font-black">باندل‌های VM</h1>
        <p class="mt-2 text-sm text-slate-500">سخت‌افزار آماده برای ساخت سریع VM. قیمت باندل فقط در حالت روشن اعمال می‌شود.</p>
    </div>
    <a href="{{ route('admin.billing.bundles.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">باندل جدید</a>
</div>
<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
@forelse($bundles as $bundle)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-black">{{ $bundle->name }}</h2><p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $bundle->slug }}</p></div><div class="flex flex-col items-end gap-2"><x-admin.status-badge :value="$bundle->is_active ? 'active' : 'inactive'" /><x-admin.status-badge :value="$bundle->show_on_marketing ? 'published' : 'draft'" :label="$bundle->show_on_marketing ? 'نمایش عمومی' : 'مخفی در سایت'" /></div></div>
    <div class="mt-5 grid grid-cols-4 gap-2 text-center text-xs"><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->cpu_cores }}</span><span class="text-slate-500">CPU</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->ram_gb }}</span><span class="text-slate-500">GB RAM</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->disk_gb }}</span><span class="text-slate-500">GB Disk</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->ip_count }}</span><span class="text-slate-500">IP</span></div></div>
    <p class="mt-5 text-left text-xl font-black text-[#0069FF]">{{ $money->format($bundle->monthly_price) }} <span class="text-xs text-slate-500">/ ماه روشن</span></p>
    <div class="mt-4"><x-admin.icon-action :href="route('admin.billing.bundles.edit', $bundle)" label="ویرایش باندل" icon="edit" tone="primary" /></div>
</article>
@empty
<div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">باندلی ثبت نشده است.</div>
@endforelse
</div></div>
@endsection
