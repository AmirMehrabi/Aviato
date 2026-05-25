@extends('layouts.admin')
@section('title', 'IP Pools')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">IP Pools</h1>
            <p class="mt-2 text-sm text-slate-500">Pools are allocated with row locks and attached to VPS cloud-init networking.</p>
        </div>
        <a href="{{ route('admin.ip-pools.create') }}" class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">Pool جدید</a>
    </div>
    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($pools as $pool)
            @php
                $available = $pool->addresses->whereIn('status', ['available', 'released'])->count();
                $assigned = $pool->addresses->where('status', 'assigned')->count();
                $reserved = $pool->addresses->where('status', 'reserved')->count();
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">{{ $pool->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $pool->proxmoxServer?->name }} · {{ $pool->node ?: 'all nodes' }}</p>
                    </div>
                    <span class="rounded-md px-2 py-1 text-xs font-black {{ $pool->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $pool->is_active ? 'فعال' : 'غیرفعال' }}</span>
                </div>
                <div class="mt-5 space-y-2 text-sm text-slate-600" dir="ltr">
                    <p>{{ $pool->start_ip }}{{ $pool->end_ip ? ' - '.$pool->end_ip : '' }}</p>
                    <p>gw {{ $pool->gateway }} /{{ $pool->prefix_length }} · {{ $pool->network_bridge }}</p>
                    <p>DNS {{ $pool->nameservers ?: '1.1.1.1' }}</p>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $available }}</span><span class="text-slate-500">Available</span></div>
                    <div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $reserved }}</span><span class="text-slate-500">Reserved</span></div>
                    <div class="rounded-lg bg-slate-50 p-3"><span class="block font-black">{{ $assigned }}</span><span class="text-slate-500">Assigned</span></div>
                </div>
                <a class="mt-4 inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700" href="{{ route('admin.ip-pools.edit', $pool) }}">ویرایش</a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">IP pool ثبت نشده است.</div>
        @endforelse
    </div>
</div>
@endsection
