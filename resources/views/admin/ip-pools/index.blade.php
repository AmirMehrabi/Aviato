@extends('layouts.admin')

@section('title', 'IPAM')

@section('content')
    <div class="px-4 py-6 md:px-8 lg:px-10">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-lg border border-[#B8D6FF] bg-[#031B4E] p-5 text-white shadow-sm lg:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase text-white/60">IP Address Management</p>
                    <h1 class="mt-3 text-2xl font-black tracking-normal md:text-3xl">IPAM و Poolها</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-white/70">
                        وضعیت هر آدرس IP، ماشین مصرف‌کننده، مشتری، زمان رزرو و تخصیص را در سطح هر Pool ببینید.
                    </p>
                </div>
                <a href="{{ route('admin.ip-pools.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white shadow-sm shadow-[#0069FF]/30 transition hover:bg-[#0050D0]">
                    Pool جدید
                </a>
            </div>
        </section>

        <div class="mt-6 space-y-6">
            @forelse($pools as $pool)
                @php
                    $available = $pool->addresses->whereIn('status', ['available', 'released'])->count();
                    $assigned = $pool->addresses->where('status', 'assigned')->count();
                    $reserved = $pool->addresses->where('status', 'reserved')->count();
                    $total = $pool->addresses->count();
                    $usedPercent = $total > 0 ? (int) round((($assigned + $reserved) / $total) * 100) : 0;
                @endphp

                <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-black text-slate-950">{{ $pool->name }}</h2>
                                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $pool->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">{{ $pool->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                    <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">{{ $total }} IP</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ $pool->proxmoxServer?->name ?: 'بدون Proxmox' }} · {{ $pool->node ?: 'all nodes' }} · {{ $pool->network_bridge }}</p>
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-slate-500" dir="ltr">
                                    <span>{{ $pool->start_ip }}{{ $pool->end_ip ? ' - '.$pool->end_ip : '' }}</span>
                                    <span>gw {{ $pool->gateway }} /{{ $pool->prefix_length }}</span>
                                    <span>DNS {{ $pool->nameservers ?: '1.1.1.1' }}</span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <a href="{{ route('admin.ip-pools.edit', $pool) }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">ویرایش</a>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-4">
                            <div class="rounded-lg bg-[#EBF3FF] p-4">
                                <p class="text-xs font-black text-[#0069FF]">Used</p>
                                <p class="mt-2 text-2xl font-black text-[#031B4E]">{{ $assigned + $reserved }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4">
                                <p class="text-xs font-black text-slate-500">Available</p>
                                <p class="mt-2 text-2xl font-black text-slate-950">{{ $available }}</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-4">
                                <p class="text-xs font-black text-amber-700">Reserved</p>
                                <p class="mt-2 text-2xl font-black text-amber-900">{{ $reserved }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-950 p-4 text-white">
                                <p class="text-xs font-black text-white/60">Utilization</p>
                                <p class="mt-2 text-2xl font-black">{{ $usedPercent }}٪</p>
                            </div>
                        </div>
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
                                                <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="block font-black text-slate-950 transition hover:text-[#0069FF]" dir="ltr">{{ $vm->name }}</a>
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
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
                    <p class="text-lg font-black text-slate-950">IP pool ثبت نشده است.</p>
                    <p class="mt-2 text-sm text-slate-500">برای رزرو خودکار IP در Provisioning، اولین Pool را بسازید.</p>
                    <a href="{{ route('admin.ip-pools.create') }}" class="mt-5 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">Pool جدید</a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
