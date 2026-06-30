@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'نمایش سرور Proxmox')

@section('content')
@php
    $inventory = $summary ?? $server->remote_inventory ?? [];
    $counts = $inventory['counts'] ?? $server->last_status['counts'] ?? [];
    $nodes = $inventory['nodes'] ?? [];
    $vms = $inventory['virtual_machines'] ?? [];
    $storages = $inventory['storage'] ?? [];
    $backups = $inventory['backups'] ?? [];
    $endpointErrors = $inventory['endpoint_errors'] ?? [];
    $staleAnomalies = $staleAnomalies ?? collect();
    $staleIds = $staleAnomalies->pluck('id')->values();
@endphp

<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="proxmoxMetrics({
        url: @js(route('admin.proxmox-servers.metrics', $server)),
        initialNode: @js($nodes[0]['node'] ?? $nodes[0]['name'] ?? null),
        staleIds: @js($staleIds),
        confirmAction: null,
    })"
    @keydown.escape.window="confirmAction = null"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Hero header --}}
    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-20 top-0 size-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    @php
                        $envLabels = ['production' => 'تولید', 'staging' => 'آزمایشی', 'development' => 'توسعه'];
                    @endphp
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-white">{{ $envLabels[$server->environment] ?? $server->environment }}</span>
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-white">{{ $server->datacenter ?: 'بدون DC' }}</span>
                    @php
                        $connStatus = match($server->connection_status) {
                            'online' => 'bg-emerald-400/20 text-emerald-300',
                            'offline' => 'bg-red-400/20 text-red-300',
                            default => 'bg-amber-400/20 text-amber-300',
                        };
                        $connLabel = match($server->connection_status) {
                            'online' => 'آنلاین',
                            'offline' => 'آفلاین',
                            default => 'ناشناخته',
                        };
                    @endphp
                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $connStatus }}">{{ $connLabel }}</span>
                    @if($fallback)
                        <span class="rounded-md bg-amber-400/20 px-2.5 py-1 text-xs font-black text-amber-300">کش پشتیبان</span>
                    @endif
                    @if($server->maintenance_mode)
                        <span class="rounded-md bg-red-400/20 px-2.5 py-1 text-xs font-black text-red-300">تعمیر و نگهداری</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-black md:text-4xl">{{ $server->name }}</h1>
                <p class="mt-2 font-mono text-sm text-white/70" dir="ltr">{{ $server->baseUrl() }} · {{ $server->proxmoxUser() }}</p>
                @if($server->synced_at)
                    <p class="mt-1 text-xs text-white/50">آخرین همگام‌سازی: {{ $server->synced_at->diffForHumans() }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    @click="confirmAction = 'sync'"
                    class="rounded-lg bg-white px-5 py-3 text-sm font-bold text-[#031B4E] transition hover:bg-slate-100"
                >همگام‌سازی</button>
                <a href="{{ route('admin.proxmox-servers.edit', $server) }}" class="rounded-lg bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/20">ویرایش</a>
                <button
                    type="button"
                    @click="confirmAction = 'delete'"
                    class="rounded-lg bg-red-500/20 px-5 py-3 text-sm font-bold text-red-300 transition hover:bg-red-500/30"
                >حذف</button>
            </div>
        </div>

        {{-- Stats cards --}}
        <div class="relative mt-6 grid gap-4 md:grid-cols-2 2xl:grid-cols-5">
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-bold text-white/60">نودها</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['nodes'] ?? count($nodes) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-bold">
                    <span class="rounded bg-emerald-400/20 px-2 py-1 text-emerald-300">آنلاین {{ $counts['online_nodes'] ?? 0 }}</span>
                    <span class="rounded bg-red-400/20 px-2 py-1 text-red-300">آفلاین {{ $counts['offline_nodes'] ?? 0 }}</span>
                </div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-bold text-white/60">ماشین‌های مجازی</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['virtual_machines'] ?? count($vms) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-bold">
                    <span class="rounded bg-emerald-400/20 px-2 py-1 text-emerald-300">روشن {{ $counts['running_virtual_machines'] ?? 0 }}</span>
                    <span class="rounded bg-slate-400/20 px-2 py-1 text-slate-300">خاموش {{ $counts['offline_virtual_machines'] ?? 0 }}</span>
                </div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-bold text-white/60">ذخیره‌سازی</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['storage'] ?? count($storages) }}</p>
                <p class="mt-3 text-xs text-white/50">ZFS, LVM, NFS, directory...</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-bold text-white/60">پشتیبان‌ها</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['backups'] ?? count($backups) }}</p>
                <p class="mt-3 text-xs text-white/50">از استوریج‌های پشتیبان‌گیر</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-bold text-white/60">درآمد ماهانه</p>
                <p class="mt-3 text-2xl font-black text-emerald-300">{{ $money->format($runningMonthlyRevenue) }}</p>
                <p class="mt-1 text-xs text-white/50">خاموش: {{ $money->format($stoppedStorageRevenue) }}</p>
            </div>
        </div>
    </div>

    @if(! empty($endpointErrors))
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
            <span class="font-black">برخی endpointهای Proxmox در دسترس نبودند:</span>
            {{ collect($endpointErrors)->map(fn($error, $path) => $path.' '.$error)->implode(' · ') }}
        </div>
    @endif

    @if($server->sync_error)
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-700">
            <span class="font-black">خطای آخرین همگام‌سازی:</span> {{ $server->sync_error }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="mt-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <button type="button" class="rounded-xl px-5 py-3 text-sm font-bold transition" :class="activeTab === 'overview' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'overview'">نمای کلی</button>
        <button type="button" class="rounded-xl px-5 py-3 text-sm font-bold transition" :class="activeTab === 'vms' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'vms'">ماشین‌های مجازی</button>
        <button type="button" class="rounded-xl px-5 py-3 text-sm font-bold transition" :class="activeTab === 'performance' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="openPerformance()">نمودار عملکرد</button>
        <button type="button" class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition" :class="activeTab === 'anomalies' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'anomalies'">
            ناهنجاری‌ها
            <span class="rounded-md px-2 py-0.5 text-xs font-bold" :class="activeTab === 'anomalies' ? 'bg-white/20 text-white' : '{{ $staleAnomalies->isNotEmpty() ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-500' }}'">{{ $staleAnomalies->count() }}</span>
        </button>
    </div>

    {{-- Overview tab --}}
    <div x-show="activeTab === 'overview'" class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            {{-- Nodes --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">نودها</h2>
                        <p class="mt-1 text-sm text-slate-500">وضعیت آنلاین/آفلاین، فشار CPU و حافظه.</p>
                    </div>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-bold text-slate-500">
                            <tr>
                                <th class="py-3">نود</th>
                                <th class="py-3">وضعیت</th>
                                <th class="py-3">CPU</th>
                                <th class="py-3">حافظه</th>
                                <th class="py-3">آپتایم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($nodes as $node)
                                <tr>
                                    <td class="py-3 font-black text-slate-950">{{ $node['node'] ?? $node['name'] ?? '—' }}</td>
                                    <td class="py-3">
                                        @php
                                            $nodeStatus = ($node['status'] ?? null) === 'online'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-red-50 text-red-700';
                                            $nodeStatusLabel = ($node['status'] ?? null) === 'online' ? 'آنلاین' : 'آفلاین';
                                        @endphp
                                        <span class="rounded-md px-2.5 py-1 text-xs font-bold {{ $nodeStatus }}">{{ $nodeStatusLabel }}</span>
                                    </td>
                                    <td class="py-3 text-slate-700">{{ isset($node['cpu']) ? round($node['cpu'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3 text-slate-700">{{ isset($node['mem'], $node['maxmem']) && $node['maxmem'] > 0 ? round($node['mem'] / $node['maxmem'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3 text-slate-700" dir="ltr">{{ isset($node['uptime']) ? floor($node['uptime'] / 86400).'d' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">نودی یافت نشد. هنگام در دسترس بودن سرور، همگام‌سازی کنید.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Remote VMs from inventory --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">ماشین‌های مجازی Proxmox</h2>
                        <p class="mt-1 text-sm text-slate-500">VMهای شناسایی شده از موجودی Proxmox.</p>
                    </div>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-bold text-slate-500">
                            <tr>
                                <th class="py-3">VM</th>
                                <th class="py-3">نود</th>
                                <th class="py-3">وضعیت</th>
                                <th class="py-3">CPU</th>
                                <th class="py-3">حافظه</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($vms as $vm)
                                <tr>
                                    <td class="py-3 font-black text-slate-950">{{ $vm['name'] ?? $vm['vmid'] ?? '—' }}</td>
                                    <td class="py-3 text-slate-700">{{ $vm['node'] ?? '—' }}</td>
                                    <td class="py-3">
                                        @php
                                            $vmInvStatus = ($vm['status'] ?? null) === 'running'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-slate-100 text-slate-600';
                                            $vmInvStatusLabel = ($vm['status'] ?? null) === 'running' ? 'روشن' : 'خاموش';
                                        @endphp
                                        <span class="rounded-md px-2.5 py-1 text-xs font-bold {{ $vmInvStatus }}">{{ $vmInvStatusLabel }}</span>
                                    </td>
                                    <td class="py-3 text-slate-700">{{ isset($vm['cpu']) ? round($vm['cpu'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3 text-slate-700">{{ isset($vm['mem'], $vm['maxmem']) && $vm['maxmem'] > 0 ? round($vm['mem'] / $vm['maxmem'] * 100, 1).'%' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">ماشین مجازی یافت نشد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            {{-- Storage --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">ذخیره‌سازی</h2>
                <p class="mt-1 text-sm text-slate-500">ظرفیت و نوع محتوا.</p>
                <div class="mt-5 space-y-3">
                    @forelse ($storages as $storage)
                        @php
                            $usedPercent = isset($storage['used'], $storage['total']) && $storage['total'] > 0 ? round($storage['used'] / $storage['total'] * 100) : null;
                        @endphp
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950">{{ $storage['storage'] ?? '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $storage['node'] ?? '—' }} · {{ $storage['type'] ?? 'unknown' }}</p>
                                </div>
                                <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600" dir="ltr">{{ $storage['content'] ?? '—' }}</span>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-[#0069FF]" style="width: {{ $usedPercent ?? 0 }}%"></div>
                            </div>
                            <div class="mt-2 flex justify-between text-xs font-bold text-slate-500">
                                <span>{{ $usedPercent !== null ? $usedPercent.'% استفاده' : 'ناشناخته' }}</span>
                                <span>{{ ($storage['active'] ?? 0) ? 'فعال' : 'غیرفعال' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">اطلاعات ذخیره‌سازی موجود نیست.</p>
                    @endforelse
                </div>
            </section>

            {{-- Backups --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">پشتیبان‌ها</h2>
                <p class="mt-1 text-sm text-slate-500">فایل‌های پشتیبان شناسایی شده.</p>
                <div class="mt-5 space-y-3">
                    @forelse (array_slice($backups, 0, 10) as $backup)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="truncate font-mono text-xs font-bold text-slate-900" dir="ltr">{{ $backup['volid'] ?? $backup['filename'] ?? 'backup' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
                                <span dir="ltr">{{ $backup['node'] ?? '—' }}</span>
                                <span dir="ltr">{{ $backup['storage'] ?? '—' }}</span>
                                <span dir="ltr">{{ $backup['format'] ?? $backup['content'] ?? 'backup' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">پشتیبانی یافت نشد.</p>
                    @endforelse
                </div>
            </section>

            {{-- Desired State --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">وضعیت مطلوب</h2>
                <pre class="mt-4 max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-left text-xs leading-6 text-white" dir="ltr">{{ json_encode($server->desired_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>
        </aside>
    </div>

    {{-- VMs billing tab --}}
    <section x-cloak x-show="activeTab === 'vms'" class="mt-6 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">ماشین‌های مجازی این سرور</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">لیست VMهای ثبت شده در پنل روی این سرور و درآمد ماهانه آنها.</p>
                </div>
                <div class="flex flex-wrap gap-4">
                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
                        <p class="text-xs font-bold text-slate-500">درآمد ماهانه (روشن)</p>
                        <p class="mt-1 text-lg font-black text-emerald-600">{{ $money->format($runningMonthlyRevenue) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
                        <p class="text-xs font-bold text-slate-500">هزینه ذخیره‌سازی (خاموش)</p>
                        <p class="mt-1 text-lg font-black text-amber-700">{{ $money->format($stoppedStorageRevenue) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-center">
                        <p class="text-xs font-bold text-slate-500">کل</p>
                        <p class="mt-1 text-lg font-black text-[#0069FF]">{{ $money->format($runningMonthlyRevenue + $stoppedStorageRevenue) }}</p>
                    </div>
                </div>
            </div>

            @if($serverVms->isEmpty())
                <div class="grid gap-4 p-8 md:grid-cols-[80px_minmax(0,1fr)] md:items-center">
                    <div class="grid size-16 place-items-center rounded-2xl bg-slate-100 text-2xl font-black text-slate-400">0</div>
                    <div>
                        <h3 class="text-xl font-black text-slate-950">VMای در این سرور ثبت نشده</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-500">هیچ ماشین مجازی متصل به این سرور در پنل وجود ندارد.</p>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-bold text-slate-500">
                            <tr>
                                <th class="py-3">VM</th>
                                <th class="py-3">مالک</th>
                                <th class="py-3">وضعیت</th>
                                <th class="py-3">منابع</th>
                                <th class="py-3">باندل</th>
                                <th class="py-3">هزینه ماهانه</th>
                                <th class="py-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($serverVms as $vm)
                                @php
                                    $vmStatusLabel = match($vm->status) {
                                        \App\Models\VirtualMachine::STATUS_RUNNING => 'روشن',
                                        \App\Models\VirtualMachine::STATUS_STOPPED => 'خاموش',
                                        \App\Models\VirtualMachine::STATUS_SUSPENDED => 'تعلیق',
                                        \App\Models\VirtualMachine::STATUS_DELETING => 'در حال حذف',
                                        default => $vm->status,
                                    };
                                    $vmStatusTone = match($vm->status) {
                                        \App\Models\VirtualMachine::STATUS_RUNNING => 'bg-emerald-50 text-emerald-700',
                                        \App\Models\VirtualMachine::STATUS_SUSPENDED => 'bg-red-50 text-red-700',
                                        \App\Models\VirtualMachine::STATUS_DELETING => 'bg-amber-50 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                    $vmOwner = $vm->project?->owner?->name ?? $vm->customer?->name ?? '—';
                                    $monthlyCost = $vm->isRunning()
                                        ? $billing->estimateMonthly($vm) + $vm->disks->where('status', \App\Models\VmDisk::STATUS_READY)->sum(fn (\App\Models\VmDisk $d): int => (int) round($billing->extraDiskHourly($d) * \App\Models\ResourceRate::hoursPerMonth()))
                                        : $billing->estimateStoppedMonthly($vm) + $vm->disks->where('status', \App\Models\VmDisk::STATUS_READY)->sum(fn (\App\Models\VmDisk $d): int => (int) round($billing->extraDiskHourly($d) * \App\Models\ResourceRate::hoursPerMonth()));
                                @endphp
                                <tr class="align-top">
                                    <td class="py-4">
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="font-black text-[#0069FF] hover:underline">{{ $vm->display_name }}</a>
                                        <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $vm->name }}</p>
                                    </td>
                                    <td class="py-4 text-slate-700">{{ $vmOwner }}</td>
                                    <td class="py-4">
                                        <span class="rounded-md px-2.5 py-1 text-xs font-bold {{ $vmStatusTone }}">{{ $vmStatusLabel }}</span>
                                    </td>
                                    <td class="py-4 text-slate-700">
                                        <span dir="ltr">{{ $vm->cpu_cores }}C · {{ $vm->ram_gb }}GB · {{ $vm->disk_gb }}GB</span>
                                    </td>
                                    <td class="py-4 text-slate-700">{{ $vm->bundle?->name ?? '—' }}</td>
                                    <td class="py-4">
                                        <p class="font-bold text-slate-950">{{ $money->format($monthlyCost) }}</p>
                                    </td>
                                    <td class="py-4">
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-200">مشاهده</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    {{-- Anomalies tab --}}
    <section x-cloak x-show="activeTab === 'anomalies'" class="mt-6 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">رکوردهای منسوخ</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">رکوردهای محلی VM که VMID آنها در سرور Proxmox یافت نشد. پاکسازی، Proxmox را دوباره بررسی می‌کند قبل از تغییر وضعیت صورتحساب یا IP.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg px-3 py-2 text-xs font-bold {{ $staleAnomalySource === 'live' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-amber-50 text-amber-700' }}">{{ $staleAnomalySource === 'live' ? 'اسکن زنده' : 'موجودی کش‌شده' }}</span>
                    <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">بروزرسانی موجودی</button></form>
                </div>
            </div>

            @if($staleAnomalies->isEmpty())
                <div class="grid gap-4 p-8 md:grid-cols-[80px_minmax(0,1fr)] md:items-center">
                    <div class="grid size-16 place-items-center rounded-2xl bg-[#EBF3FF] text-2xl font-black text-[#0069FF]">0</div>
                    <div>
                        <h3 class="text-xl font-black text-slate-950">رکورد منسوخی یافت نشد</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-500">VMIDهای محلی این سرور با آخرین موجودی موجود در پنل مطابقت دارند.</p>
                    </div>
                </div>
            @else
                <div class="grid gap-4 border-b border-slate-100 bg-red-50/60 p-5 lg:grid-cols-3">
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-bold text-slate-500">ناهنجاری‌ها</p>
                        <p class="mt-2 text-3xl font-black text-red-700">{{ $staleAnomalies->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-bold text-slate-500">IPهای رزرو شده</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $staleAnomalies->filter(fn($vm) => $vm->reservedIpAddress)->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-bold text-slate-500">بسته شدن احتمالی صورتحساب</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $staleAnomalies->filter(fn($vm) => (int) ($vm->unbilled_amount ?? 0) > 0 || $vm->last_billed_at)->count() }}</p>
                    </div>
                </div>

                <form id="bulk-stale-cleanup" method="POST" action="{{ route('admin.proxmox-servers.stale-virtual-machines.destroy-bulk', $server) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>

                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">
                            <input type="checkbox" class="size-4 rounded border-slate-300 text-[#0069FF]" :checked="allStaleSelected()" @change="toggleAllStale($event.target.checked)">
                            انتخاب همه
                        </label>
                        <span class="text-sm font-bold text-slate-500" x-text="`${selectedStale.length} انتخاب شده`"></span>
                    </div>
                    <button type="submit" form="bulk-stale-cleanup" class="rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="selectedStale.length === 0" onclick="return confirm('آیا رکوردهای انتخاب شده حذف شوند؟ این کار IPها را آزاد و صورتحساب را می‌بندد.')">حذف انتخاب شده</button>
                </div>

                <template x-for="id in selectedStale" :key="`stale-${id}`">
                    <input type="hidden" name="vm_ids[]" :value="id" form="bulk-stale-cleanup">
                </template>

                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-bold text-slate-500">
                            <tr>
                                <th class="py-3"></th>
                                <th class="py-3">VM</th>
                                <th class="py-3">مشتری</th>
                                <th class="py-3">VMID</th>
                                <th class="py-3">IP</th>
                                <th class="py-3">وضعیت صورتحساب</th>
                                <th class="py-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($staleAnomalies as $vm)
                                <tr class="align-top">
                                    <td class="py-4">
                                        <input type="checkbox" value="{{ $vm->id }}" class="size-4 rounded border-slate-300 text-[#0069FF]" :checked="selectedStale.includes({{ $vm->id }})" @change="toggleStale({{ $vm->id }}, $event.target.checked)">
                                    </td>
                                    <td class="py-4">
                                        <p class="font-black text-slate-950">{{ $vm->display_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $vm->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $vm->hostname ?: 'بدون هاست‌نیم' }} · {{ \App\Support\AdminUi::status($vm->status) }}</p>
                                    </td>
                                    <td class="py-4 text-slate-700">{{ $vm->customer?->name ?? '—' }}</td>
                                    <td class="py-4"><span class="rounded-md bg-red-50 px-2.5 py-1 font-mono text-xs font-bold text-red-700" dir="ltr">{{ $vm->vmid }}</span></td>
                                    <td class="py-4">
                                        <p class="font-mono" dir="ltr">{{ $vm->ip_address ?: '—' }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-400">{{ $vm->reservedIpAddress ? 'آزاد خواهد شد' : 'IP رزرو نشده' }}</p>
                                    </td>
                                    <td class="py-4">
                                        <p class="font-bold text-slate-700">آخرین صدور: {{ $vm->last_billed_at?->diffForHumans() ?? 'هرگز' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">صادر نشده: {{ number_format((int) ($vm->unbilled_amount ?? 0)) }}</p>
                                    </td>
                                    <td class="py-4">
                                        <form method="POST" action="{{ route('admin.proxmox-servers.stale-virtual-machines.destroy', [$server, $vm]) }}" onsubmit="return confirm('آیا این رکورد محلی حذف شود؟ قبل از پاکسازی، Proxmox دوباره بررسی خواهد شد.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg bg-red-50 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-100">حذف رکورد محلی</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    {{-- Performance tab --}}
    <section x-cloak x-show="activeTab === 'performance'" class="mt-6 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">عملکرد نود</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">مصرف CPU، بار سرور، مصرف حافظه و ترافیک شبکه از داده‌های RRD Proxmox.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="node" @change="load()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <template x-for="item in nodes" :key="item">
                            <option :value="item" x-text="item"></option>
                        </template>
                    </select>
                    <select x-model="timeframe" @change="load()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <option value="hour">ساعت اخیر</option>
                        <option value="day">روز اخیر</option>
                        <option value="week">هفته اخیر</option>
                        <option value="month">ماه اخیر</option>
                        <option value="year">سال اخیر</option>
                    </select>
                    <button type="button" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#0050D0]" @click="load()">بروزرسانی</button>
                </div>
            </div>

            <div class="grid gap-4 border-b border-slate-100 bg-slate-50 p-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">CPU</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="formatPercent(latest.cpu_percent)"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">بار سرور</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="latest.load ?? '—'"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">حافظه</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="formatPercent(latest.memory_percent)"></p>
                    <p class="mt-1 text-xs font-bold text-slate-400" x-text="latest.memory_used && latest.memory_total ? `${latest.memory_used} / ${latest.memory_total}` : ''"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">شبکه</p>
                    <p class="mt-2 text-sm font-bold text-[#0069FF]">ورودی <span x-text="formatRate(latest.netin_bytes_per_second)"></span></p>
                    <p class="mt-1 text-sm font-bold text-amber-600">خروجی <span x-text="formatRate(latest.netout_bytes_per_second)"></span></p>
                </div>
            </div>

            <div x-show="loading" class="p-8 text-center text-sm font-bold text-slate-500">در حال بارگذاری نمودارها...</div>
            <div x-show="error" class="m-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" x-text="error"></div>
            <div x-show="!loading && !error && samples.length === 0" class="p-8 text-center text-sm font-bold text-slate-500">داده‌ای از Proxmox برای این نود/بازه زمانی دریافت نشد.</div>

            <div x-show="!loading && samples.length > 0" class="grid gap-5 p-5 xl:grid-cols-2">
                <template x-for="graph in graphs" :key="graph.key">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-black text-slate-950" x-text="graph.label"></h3>
                                <p class="mt-1 text-xs font-bold text-slate-400" x-text="graph.help"></p>
                            </div>
                            <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600" x-text="`${samples.length} نقطه`"></span>
                        </div>
                        <svg viewBox="0 0 720 260" class="h-72 w-full overflow-visible rounded-xl bg-slate-950 p-2" preserveAspectRatio="none">
                            <defs>
                                <linearGradient :id="`${graph.key}-fill`" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" :stop-color="graph.color" stop-opacity="0.34"></stop>
                                    <stop offset="100%" :stop-color="graph.color" stop-opacity="0.02"></stop>
                                </linearGradient>
                            </defs>
                            <g class="text-slate-700">
                                <line x1="34" y1="30" x2="34" y2="222" stroke="currentColor" stroke-width="1"></line>
                                <line x1="34" y1="222" x2="700" y2="222" stroke="currentColor" stroke-width="1"></line>
                                <line x1="34" y1="158" x2="700" y2="158" stroke="currentColor" stroke-width="1" stroke-dasharray="5 8"></line>
                                <line x1="34" y1="94" x2="700" y2="94" stroke="currentColor" stroke-width="1" stroke-dasharray="5 8"></line>
                            </g>
                            <path :d="areaPath(graph)" :fill="`url(#${graph.key}-fill)`"></path>
                            <path :d="linePath(graph)" fill="none" :stroke="graph.color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                            <text x="38" y="24" fill="#94a3b8" font-size="12" font-weight="800" x-text="graph.maxLabel"></text>
                            <text x="38" y="246" fill="#94a3b8" font-size="12" font-weight="800" x-text="timeLabel(samples[0]?.time)"></text>
                            <text x="620" y="246" fill="#94a3b8" font-size="12" font-weight="800" x-text="timeLabel(samples[samples.length - 1]?.time)"></text>
                        </svg>
                    </div>
                </template>
            </div>
        </div>
    </section>

    {{-- Confirmation modal --}}
    <div
        x-show="confirmAction"
        x-transition.opacity
        @click.self="confirmAction = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" @click.stop>
            <template x-if="confirmAction === 'sync'">
                <div>
                    <h3 class="text-lg font-black text-slate-950">همگام‌سازی با Proxmox</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید موجودی سرور را از Proxmox بروزرسانی کنید؟</p>
                    <div class="mt-6 flex gap-3">
                        <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}" class="flex-1">@csrf <button class="w-full rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#0050D0]">بله، همگام‌سازی کن</button></form>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
            <template x-if="confirmAction === 'delete'">
                <div>
                    <h3 class="text-lg font-black text-red-700">حذف سرور</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید این سرور Proxmox را از پنل حذف کنید؟ <strong class="text-red-700">این عمل غیرقابل بازگشت است.</strong></p>
                    <div class="mt-6 flex gap-3">
                        <form method="POST" action="{{ route('admin.proxmox-servers.destroy', $server) }}" class="flex-1">@csrf @method('DELETE') <button class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700">بله، حذف کن</button></form>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
    function proxmoxMetrics(config) {
        return {
            activeTab: 'overview',
            confirmAction: null,
            url: config.url,
            node: config.initialNode,
            nodes: config.initialNode ? [config.initialNode] : [],
            staleIds: config.staleIds || [],
            selectedStale: [],
            timeframe: 'hour',
            samples: [],
            latest: {},
            loading: false,
            error: null,
            loaded: false,
            graphs: [
                { key: 'cpu', label: 'مصرف CPU', help: 'درصد استفاده', color: '#34d399', max: 100, maxLabel: '100%' },
                { key: 'loadavg', label: 'بار سرور', help: 'میانگین بار', color: '#f59e0b', max: null, maxLabel: 'auto' },
                { key: 'memory', label: 'مصرف حافظه', help: 'درصد استفاده', color: '#38bdf8', max: 100, maxLabel: '100%' },
                { key: 'network', label: 'ترافیک شبکه', help: 'ترافیک ورودی/خروجی در ثانیه', color: '#fb7185', max: null, maxLabel: 'auto' },
            ],
            openPerformance() {
                this.activeTab = 'performance';
                if (!this.loaded) {
                    this.load();
                }
            },
            toggleStale(id, checked) {
                id = Number(id);
                this.selectedStale = checked
                    ? [...new Set([...this.selectedStale, id])]
                    : this.selectedStale.filter((item) => item !== id);
            },
            toggleAllStale(checked) {
                this.selectedStale = checked ? [...this.staleIds] : [];
            },
            allStaleSelected() {
                return this.staleIds.length > 0 && this.selectedStale.length === this.staleIds.length;
            },
            async load() {
                this.loading = true;
                this.error = null;

                try {
                    const params = new URLSearchParams({ timeframe: this.timeframe });
                    if (this.node) params.set('node', this.node);

                    const response = await fetch(`${this.url}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'امکان بارگذاری نمودارها نیست.');
                    }

                    const data = payload.data || {};
                    this.samples = data.samples || [];
                    this.latest = data.latest || {};
                    this.nodes = data.nodes?.length ? data.nodes : this.nodes;
                    this.node = data.node || this.node;
                    this.loaded = true;
                } catch (error) {
                    this.error = error.message || 'امکان بارگذاری نمودارها نیست.';
                } finally {
                    this.loading = false;
                }
            },
            value(sample, graph) {
                if (!sample) return 0;
                if (graph.key === 'cpu') return Number(sample.cpu || 0) * 100;
                if (graph.key === 'loadavg') return Number(sample.loadavg || sample.load || 0);
                if (graph.key === 'memory') return Number(sample.maxmem || 0) > 0 ? (Number(sample.mem || 0) / Number(sample.maxmem)) * 100 : 0;
                if (graph.key === 'network') return Number(sample.netin || 0) + Number(sample.netout || 0);
                return 0;
            },
            scaleMax(graph) {
                if (graph.max) return graph.max;
                const max = Math.max(...this.samples.map((sample) => this.value(sample, graph)), 1);
                graph.maxLabel = graph.key === 'network' ? this.formatRate(max) : Math.ceil(max * 10) / 10;
                return max * 1.12;
            },
            points(graph) {
                const max = this.scaleMax(graph);
                return this.samples.map((sample, index) => {
                    const x = 34 + (index / Math.max(this.samples.length - 1, 1)) * 666;
                    const y = 222 - (Math.min(this.value(sample, graph), max) / max) * 192;
                    return [x, y];
                });
            },
            linePath(graph) {
                const points = this.points(graph);
                if (!points.length) return '';
                return points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point[0].toFixed(2)} ${point[1].toFixed(2)}`).join(' ');
            },
            areaPath(graph) {
                const points = this.points(graph);
                if (!points.length) return '';
                return `${this.linePath(graph)} L ${points[points.length - 1][0].toFixed(2)} 222 L ${points[0][0].toFixed(2)} 222 Z`;
            },
            formatPercent(value) {
                return value === null || value === undefined ? '—' : `${Number(value).toFixed(1)}%`;
            },
            formatRate(value) {
                if (value === null || value === undefined) return '—';
                const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
                let amount = Number(value);
                let unit = 0;
                while (amount >= 1024 && unit < units.length - 1) {
                    amount /= 1024;
                    unit++;
                }
                return `${amount.toFixed(amount >= 10 ? 0 : 1)} ${units[unit]}`;
            },
            timeLabel(timestamp) {
                if (!timestamp) return '';
                return new Date(Number(timestamp) * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },
        };
    }
</script>
@endsection
