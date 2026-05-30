@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'باندل‌ها')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
@if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><h1 class="text-2xl font-black">باندل‌های VM</h1><p class="mt-2 text-sm text-slate-500">سخت‌افزار آماده برای ساخت سریع VM. قیمت باندل فقط در حالت روشن اعمال می‌شود.</p></div><a href="{{ route('admin.billing.bundles.create') }}" class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">باندل جدید</a></div>
<div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
@forelse($bundles as $bundle)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-black">{{ $bundle->name }}</h2><p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $bundle->slug }}</p></div><div class="flex flex-col items-end gap-2"><span class="rounded-md px-2 py-1 text-xs font-black {{ $bundle->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $bundle->is_active ? 'فعال' : 'غیرفعال' }}</span><span class="rounded-md px-2 py-1 text-xs font-black {{ $bundle->show_on_marketing ? 'bg-[#F2F8FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $bundle->show_on_marketing ? 'نمایش عمومی' : 'مخفی در سایت' }}</span></div></div>
    <div class="mt-5 grid grid-cols-4 gap-2 text-center text-xs"><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->cpu_cores }}</span><span class="text-slate-500">CPU</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->ram_gb }}</span><span class="text-slate-500">GB RAM</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->disk_gb }}</span><span class="text-slate-500">GB Disk</span></div><div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $bundle->ip_count }}</span><span class="text-slate-500">IP</span></div></div>
    <p class="mt-5 text-left text-xl font-black text-[#105D52]">{{ $money->format($bundle->monthly_price) }} <span class="text-xs text-slate-500">/ ماه روشن</span></p>
    <a class="mt-4 inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700" href="{{ route('admin.billing.bundles.edit', $bundle) }}">ویرایش</a>
</article>
@empty
<div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">باندلی ثبت نشده است.</div>
@endforelse
</div></div>
@endsection
