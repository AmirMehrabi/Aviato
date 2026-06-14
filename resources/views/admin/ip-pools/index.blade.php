@extends('layouts.admin')

@section('title', 'IP Pools')

@section('content')
    <div class="px-4 py-6 md:px-8 lg:px-10">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-black">IP Pools</h1>
                <p class="mt-2 text-sm text-slate-500">خلاصه ظرفیت هر Pool را ببینید و برای مشاهده IPهای مصرف‌شده، آزاد و ماشین‌های متصل وارد جزئیات Pool شوید.</p>
            </div>
            <a href="{{ route('admin.ip-pools.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Pool جدید</a>
        </div>
        </section>

        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($pools as $pool)
                @php
                    $available = $pool->addresses->whereIn('status', ['available', 'released'])->count();
                    $assigned = $pool->addresses->where('status', 'assigned')->count();
                    $reserved = $pool->addresses->where('status', 'reserved')->count();
                    $total = $pool->addresses->count();
                    $used = $assigned + $reserved;
                    $usedPercent = $total > 0 ? (int) round(($used / $total) * 100) : 0;
                @endphp
                <a href="{{ route('admin.ip-pools.show', $pool) }}" class="group block overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-[#0069FF]/10">
                    <div class="border-b border-slate-100 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-black text-slate-950 transition group-hover:text-[#0069FF]">{{ $pool->name }}</h2>
                                <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $pool->proxmoxServer?->name ?: 'بدون Proxmox' }} · {{ $pool->node ?: 'all nodes' }}</p>
                            </div>
                            <span class="shrink-0 rounded-md px-2.5 py-1 text-xs font-black {{ $pool->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $pool->is_active ? 'فعال' : 'غیرفعال' }}</span>
                        </div>

                        <div class="mt-4 space-y-1 font-mono text-xs text-slate-500" dir="ltr">
                            <p class="truncate">{{ $pool->start_ip }}{{ $pool->end_ip ? ' - '.$pool->end_ip : '' }}</p>
                            <p class="truncate">gw {{ $pool->gateway }} /{{ $pool->prefix_length }} · {{ $pool->network_bridge }}</p>
                        </div>

                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#0069FF]" style="width: {{ max(4, $usedPercent) }}%"></div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs font-bold text-slate-500">
                            <span>{{ $usedPercent }}٪ استفاده شده</span>
                            <span>{{ $total }} IP</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-slate-100 text-center">
                        <div class="p-4">
                            <p class="text-2xl font-black text-slate-950">{{ $available }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">Available</p>
                        </div>
                        <div class="p-4">
                            <p class="text-2xl font-black text-amber-700">{{ $reserved }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">Reserved</p>
                        </div>
                        <div class="p-4">
                            <p class="text-2xl font-black text-[#0069FF]">{{ $assigned }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">Assigned</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                    <p class="text-lg font-black text-slate-950">IP pool ثبت نشده است.</p>
                    <p class="mt-2 text-sm text-slate-500">برای رزرو خودکار IP در Provisioning، اولین Pool را بسازید.</p>
                    <a href="{{ route('admin.ip-pools.create') }}" class="mt-5 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Pool جدید</a>
                </div>
            @endforelse
        </section>
    </div>
@endsection
