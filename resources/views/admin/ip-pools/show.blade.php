@extends('layouts.admin')

@section('title', 'IPAM - '.$pool->name)

@section('content')
    @php
        $available = $pool->addresses->whereIn('status', ['available', 'released'])->count();
        $assigned = $pool->addresses->where('status', 'assigned')->count();
        $reserved = $pool->addresses->where('status', 'reserved')->count();
        $total = $pool->addresses->count();
        $usedPercent = $total > 0 ? (int) round((($assigned + $reserved) / $total) * 100) : 0;
    @endphp

    <div class="px-4 py-6 md:px-8 lg:px-10">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-lg border border-[#B8D6FF] bg-[#031B4E] p-5 text-white shadow-sm lg:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.ip-pools.index') }}" class="text-sm font-bold text-white/60 transition hover:text-white">IP Pools</a>
                        <span class="text-white/35">/</span>
                        <span class="text-sm font-black text-white">{{ $pool->name }}</span>
                    </div>
                    <h1 class="mt-3 text-2xl font-black tracking-normal md:text-3xl">{{ $pool->name }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-white/70">
                        جزئیات IPهای این Pool، وضعیت مصرف، ماشین متصل، مشتری و زمان‌های رزرو و تخصیص.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-white/65" dir="ltr">
                        <span>{{ $pool->start_ip }}{{ $pool->end_ip ? ' - '.$pool->end_ip : '' }}</span>
                        <span>gw {{ $pool->gateway }} /{{ $pool->prefix_length }}</span>
                        <span>{{ $pool->network_bridge }}</span>
                        <span>DNS {{ $pool->nameservers ?: '1.1.1.1' }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('admin.ip-pools.edit', $pool) }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E] transition hover:bg-slate-100">ویرایش</a>
                    <a href="{{ route('admin.ip-pools.index') }}" class="rounded-lg border border-white/15 bg-white/10 px-5 py-3 text-sm font-black text-white transition hover:bg-white/15">بازگشت</a>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] p-4">
                <p class="text-xs font-black text-[#0069FF]">Used</p>
                <p class="mt-2 text-2xl font-black text-[#031B4E]">{{ $assigned + $reserved }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-black text-slate-500">Available</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $available }}</p>
            </div>
            <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                <p class="text-xs font-black text-amber-700">Reserved</p>
                <p class="mt-2 text-2xl font-black text-amber-900">{{ $reserved }}</p>
            </div>
            <div class="rounded-lg bg-slate-950 p-4 text-white">
                <p class="text-xs font-black text-white/60">Utilization</p>
                <p class="mt-2 text-2xl font-black">{{ $usedPercent }}٪</p>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">IP Addresses</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $pool->proxmoxServer?->name ?: 'بدون Proxmox' }} · {{ $pool->node ?: 'all nodes' }}</p>
                </div>
                <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $pool->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $pool->is_active ? 'فعال' : 'غیرفعال' }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="px-5 py-4">IP Address</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Machine</th>
                            <th class="px-5 py-4">Customer</th>
                            <th class="px-5 py-4">Plan</th>
                            <th class="px-5 py-4">Reserved</th>
                            <th class="px-5 py-4">Assigned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pool->addresses as $address)
                            @php
                                $vm = $address->virtualMachine;
                                $statusClass = match ($address->status) {
                                    'assigned' => 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]',
                                    'reserved' => 'bg-amber-50 text-amber-700 ring-amber-100',
                                    'released' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                    default => 'bg-white text-slate-600 ring-slate-200',
                                };
                            @endphp
                            <tr class="transition hover:bg-[#F8FBFF]">
                                <td class="whitespace-nowrap px-5 py-4 font-mono font-black text-slate-950" dir="ltr">{{ $address->address }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="rounded-md px-2.5 py-1 text-xs font-black ring-1 {{ $statusClass }}">{{ strtoupper($address->status) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($vm)
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="block font-black text-slate-950 transition hover:text-[#0069FF]" dir="ltr">{{ $vm->display_name }}</a>
                                        <span class="mt-1 block text-xs text-slate-500" dir="ltr">{{ $vm->status }} / {{ $vm->provisioning_status }}</span>
                                    @else
                                        <span class="text-slate-400">بدون ماشین</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($vm?->customer)
                                        <a href="{{ route('admin.customers.show', $vm->customer) }}" class="font-bold text-[#0069FF] transition hover:text-[#0050D0]">{{ $vm->customer->name }}</a>
                                    @else
                                        <span class="text-slate-400">بدون مشتری</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm?->bundle?->name ?: ($vm ? $vm->cpu_cores.' CPU / '.$vm->ram_gb.'GB' : '—') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">{{ $address->reserved_at?->format('Y/m/d H:i') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">{{ $address->assigned_at?->format('Y/m/d H:i') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm font-bold text-slate-500">برای این Pool هنوز IP ساخته نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
