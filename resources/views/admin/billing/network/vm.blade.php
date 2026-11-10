@extends('layouts.admin')
@section('title', 'مصرف شبکه '.$vm->display_name)
@section('content')
@php($displayTimezone = config('app.timezone', 'Asia/Tehran'))
<div class="px-4 py-6 md:px-8 lg:px-10"><div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"><div>
@adminRoute('admin.billing.network.index')
<a href="{{ route('admin.billing.network.index') }}" class="text-sm font-black text-[#0069FF]">حسابداری شبکه ←</a>
@endadminRoute
<h1 class="mt-2 text-2xl font-black">{{ $vm->display_name }}</h1><p class="mt-1 font-mono text-xs text-slate-400" dir="ltr">{{ $vm->uuid }}</p></div><div class="flex gap-2">
@adminRoute('admin.virtual-machines.edit')
<a href="{{ route('admin.virtual-machines.edit', $vm) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black">تنظیم override</a>
@endadminRoute

@adminRoute('admin.virtual-machines.show')
<a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white">جزئیات ماشین</a>
@endadminRoute
</div></div>
@include('admin.billing.network.vm-details')
</div>
@endsection
