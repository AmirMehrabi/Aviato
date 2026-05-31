@extends('layouts.admin')

@section('title', 'نمایش Proxmox')

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
    })"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ session('error') }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-20 top-0 size-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-white">{{ $server->environment }}</span>
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-white">{{ $server->datacenter ?: 'No DC' }}</span>
                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $server->connection_status === 'online' ? 'bg-[#B8D6FF] text-[#031B4E]' : 'bg-amber-300 text-amber-950' }}">{{ strtoupper($server->connection_status) }}</span>
                    @if($fallback)
                        <span class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-900">Fallback cache</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-black md:text-4xl">{{ $server->name }}</h1>
                <p class="mt-2 font-mono text-sm text-white/70" dir="ltr">{{ $server->baseUrl() }} · {{ $server->proxmoxUser() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E] hover:bg-slate-100">Sync now</button></form>
                <a href="{{ route('admin.proxmox-servers.edit', $server) }}" class="rounded-lg border border-white/15 bg-white/10 px-5 py-3 text-sm font-black text-white hover:bg-white/15">ویرایش</a>
                <form method="POST" action="{{ route('admin.proxmox-servers.destroy', $server) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE') <button class="rounded-lg bg-red-400/15 px-5 py-3 text-sm font-black text-red-50 hover:bg-red-400/25">حذف</button></form>
            </div>
        </div>

        <div class="relative mt-6 grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/65">Nodes</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['nodes'] ?? count($nodes) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-black"><span class="rounded bg-[#B8D6FF] px-2 py-1 text-[#031B4E]">Online {{ $counts['online_nodes'] ?? 0 }}</span><span class="rounded bg-red-200 px-2 py-1 text-red-900">Offline {{ $counts['offline_nodes'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/65">Virtual Machines</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['virtual_machines'] ?? count($vms) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-black"><span class="rounded bg-[#B8D6FF] px-2 py-1 text-[#031B4E]">Online {{ $counts['running_virtual_machines'] ?? 0 }}</span><span class="rounded bg-red-200 px-2 py-1 text-red-900">Offline {{ $counts['offline_virtual_machines'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/65">Storage Pools</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['storage'] ?? count($storages) }}</p>
                <p class="mt-3 text-xs font-bold text-white/60">local, directory, LVM, ZFS, NFS...</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/65">Backups</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['backups'] ?? count($backups) }}</p>
                <p class="mt-3 text-xs font-bold text-white/60">Detected from backup-capable storages</p>
            </div>
        </div>
    </div>

    @if(! empty($endpointErrors))
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
            <span class="font-black">Some Proxmox endpoints were not accessible:</span>
            {{ collect($endpointErrors)->map(fn($error, $path) => $path.' '.$error)->implode(' · ') }}
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <button type="button" class="rounded-xl px-5 py-3 text-sm font-black transition" :class="activeTab === 'overview' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'overview'">Overview</button>
        <button type="button" class="rounded-xl px-5 py-3 text-sm font-black transition" :class="activeTab === 'performance' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="openPerformance()">Performance graphs</button>
        <button type="button" class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-black transition" :class="activeTab === 'anomalies' ? 'bg-[#0069FF] text-white shadow-lg shadow-[#0069FF]/20' : 'text-slate-600 hover:bg-slate-50'" @click="activeTab = 'anomalies'">
            Anomalies
            <span class="rounded-md px-2 py-0.5 text-xs" :class="activeTab === 'anomalies' ? 'bg-white/20 text-white' : '{{ $staleAnomalies->isNotEmpty() ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-500' }}'">{{ $staleAnomalies->count() }}</span>
        </button>
    </div>

    <div x-show="activeTab === 'overview'" class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black">Nodes</h2>
                        <p class="mt-1 text-sm text-slate-500">Online/offline state, CPU and memory pressure.</p>
                    </div>
                    <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-50" disabled>Manage selected</button>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-black text-slate-500"><tr><th class="py-3">Node</th><th class="py-3">Status</th><th class="py-3">CPU</th><th class="py-3">Memory</th><th class="py-3">Uptime</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($nodes as $node)
                                <tr>
                                    <td class="py-3 font-black">{{ $node['node'] ?? $node['name'] ?? '—' }}</td>
                                    <td class="py-3"><span class="rounded-md px-2.5 py-1 text-xs font-black {{ ($node['status'] ?? null) === 'online' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-red-50 text-red-700' }}">{{ strtoupper($node['status'] ?? 'unknown') }}</span></td>
                                    <td class="py-3">{{ isset($node['cpu']) ? round($node['cpu'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3">{{ isset($node['mem'], $node['maxmem']) && $node['maxmem'] > 0 ? round($node['mem'] / $node['maxmem'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3">{{ isset($node['uptime']) ? floor($node['uptime'] / 86400).'d' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">No nodes found. Sync when the server is reachable.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black">Virtual Machines</h2>
                        <p class="mt-1 text-sm text-slate-500">Running and stopped guests across the cluster.</p>
                    </div>
                    <div class="flex gap-2"><button class="rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-black text-white" disabled>Start</button><button class="rounded-lg bg-red-50 px-4 py-2 text-sm font-black text-red-700" disabled>Stop</button></div>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-black text-slate-500"><tr><th class="py-3">VM</th><th class="py-3">Node</th><th class="py-3">Status</th><th class="py-3">CPU</th><th class="py-3">Memory</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($vms as $vm)
                                <tr>
                                    <td class="py-3 font-black">{{ $vm['name'] ?? $vm['vmid'] ?? '—' }}</td>
                                    <td class="py-3">{{ $vm['node'] ?? '—' }}</td>
                                    <td class="py-3"><span class="rounded-md px-2.5 py-1 text-xs font-black {{ ($vm['status'] ?? null) === 'running' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-600' }}">{{ strtoupper($vm['status'] ?? 'unknown') }}</span></td>
                                    <td class="py-3">{{ isset($vm['cpu']) ? round($vm['cpu'] * 100, 1).'%' : '—' }}</td>
                                    <td class="py-3">{{ isset($vm['mem'], $vm['maxmem']) && $vm['maxmem'] > 0 ? round($vm['mem'] / $vm['maxmem'] * 100, 1).'%' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">No virtual machines found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="font-black">Storage</h2><p class="mt-1 text-sm text-slate-500">Capacity and content flags.</p></div>
                    <button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700" disabled>Add storage</button>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($storages as $storage)
                        @php($usedPercent = isset($storage['used'], $storage['total']) && $storage['total'] > 0 ? round($storage['used'] / $storage['total'] * 100) : null)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3"><div><p class="font-black">{{ $storage['storage'] ?? '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $storage['node'] ?? '—' }} · {{ $storage['type'] ?? 'unknown' }}</p></div><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ $storage['content'] ?? '—' }}</span></div>
                            <div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-[#0069FF]" style="width: {{ $usedPercent ?? 0 }}%"></div></div>
                            <div class="mt-2 flex justify-between text-xs font-bold text-slate-500"><span>{{ $usedPercent !== null ? $usedPercent.'% used' : 'usage unknown' }}</span><span>{{ ($storage['active'] ?? 0) ? 'active' : 'inactive' }}</span></div>
                            <div class="mt-3 flex gap-2"><button class="rounded-md bg-slate-100 px-3 py-2 text-xs font-black text-slate-700" disabled>Edit</button><button class="rounded-md bg-amber-50 px-3 py-2 text-xs font-black text-amber-700" disabled>Prune</button></div>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">No storage inventory available.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="font-black">Backups</h2><p class="mt-1 text-sm text-slate-500">Backup files found on backup-capable storage.</p></div>
                    <button class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white" disabled>Run backup</button>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse (array_slice($backups, 0, 10) as $backup)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="truncate font-mono text-xs font-black text-slate-900" dir="ltr">{{ $backup['volid'] ?? $backup['filename'] ?? 'backup' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold text-slate-500"><span>{{ $backup['node'] ?? '—' }}</span><span>{{ $backup['storage'] ?? '—' }}</span><span>{{ $backup['format'] ?? $backup['content'] ?? 'backup' }}</span></div>
                            <div class="mt-3 flex gap-2"><button class="rounded-md bg-slate-100 px-3 py-2 text-xs font-black text-slate-700" disabled>Restore</button><button class="rounded-md bg-red-50 px-3 py-2 text-xs font-black text-red-700" disabled>Delete</button></div>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">No backups found or token cannot access backup content.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black">Pending Desired State</h2>
                <pre class="mt-4 max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-left text-xs leading-6 text-white" dir="ltr">{{ json_encode($server->desired_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>

            @if($server->sync_error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-7 text-red-700"><span class="font-black">Last error:</span> {{ $server->sync_error }}</div>
            @endif
        </aside>
    </div>

    <section x-cloak x-show="activeTab === 'anomalies'" class="mt-6 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-black">Stale Application Records</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">Local VM records whose VMID was not found on this Proxmox server. Cleanup re-checks Proxmox live before changing billing or IP state.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg px-3 py-2 text-xs font-black {{ $staleAnomalySource === 'live' ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-amber-50 text-amber-700' }}">{{ $staleAnomalySource === 'live' ? 'Live scan' : 'Cached inventory' }}</span>
                    <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-50">Refresh inventory</button></form>
                </div>
            </div>

            @if($staleAnomalies->isEmpty())
                <div class="grid gap-4 p-8 md:grid-cols-[80px_minmax(0,1fr)] md:items-center">
                    <div class="grid size-16 place-items-center rounded-2xl bg-[#EBF3FF] text-2xl font-black text-[#0069FF]">0</div>
                    <div>
                        <h3 class="text-xl font-black text-slate-950">No stale VM records found</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-500">The local VMIDs on this server match the latest inventory available to the panel.</p>
                    </div>
                </div>
            @else
                <div class="grid gap-4 border-b border-slate-200 bg-red-50/60 p-5 lg:grid-cols-3">
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-black text-slate-500">Anomalies</p>
                        <p class="mt-2 text-3xl font-black text-red-700">{{ $staleAnomalies->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-black text-slate-500">Reserved or assigned IPs</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $staleAnomalies->filter(fn($vm) => $vm->reservedIpAddress)->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <p class="text-xs font-black text-slate-500">Potential billing closes</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $staleAnomalies->filter(fn($vm) => (int) ($vm->unbilled_amount ?? 0) > 0 || $vm->last_billed_at)->count() }}</p>
                    </div>
                </div>

                <form id="bulk-stale-cleanup" method="POST" action="{{ route('admin.proxmox-servers.stale-virtual-machines.destroy-bulk', $server) }}" onsubmit="return confirm('Delete the selected stale records from the application? This releases their IPs and closes accrued billing.');">
                    @csrf
                    @method('DELETE')
                </form>

                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700">
                            <input type="checkbox" class="size-4 rounded border-slate-300 text-[#0069FF]" :checked="allStaleSelected()" @change="toggleAllStale($event.target.checked)">
                            Select all stale records
                        </label>
                        <span class="text-sm font-bold text-slate-500" x-text="`${selectedStale.length} selected`"></span>
                    </div>
                    <button type="submit" form="bulk-stale-cleanup" class="rounded-lg bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="selectedStale.length === 0">Delete selected</button>
                </div>

                <template x-for="id in selectedStale" :key="`stale-${id}`">
                    <input type="hidden" name="vm_ids[]" :value="id" form="bulk-stale-cleanup">
                </template>

                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-black text-slate-500">
                            <tr>
                                <th class="py-3"></th>
                                <th class="py-3">VM</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3">VMID</th>
                                <th class="py-3">IP</th>
                                <th class="py-3">Billing state</th>
                                <th class="py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($staleAnomalies as $vm)
                                <tr class="align-top">
                                    <td class="py-4">
                                        <input type="checkbox" value="{{ $vm->id }}" class="size-4 rounded border-slate-300 text-[#0069FF]" :checked="selectedStale.includes({{ $vm->id }})" @change="toggleStale({{ $vm->id }}, $event.target.checked)">
                                    </td>
                                    <td class="py-4">
                                        <p class="font-black text-slate-950">{{ $vm->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $vm->hostname ?: 'No hostname' }} · {{ $vm->status }}</p>
                                    </td>
                                    <td class="py-4">{{ $vm->customer?->name ?? '—' }}</td>
                                    <td class="py-4"><span class="rounded-md bg-red-50 px-2.5 py-1 font-mono text-xs font-black text-red-700">{{ $vm->vmid }}</span></td>
                                    <td class="py-4">
                                        <p class="font-mono" dir="ltr">{{ $vm->ip_address ?: '—' }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-400">{{ $vm->reservedIpAddress ? 'Will be released' : 'No reserved IP' }}</p>
                                    </td>
                                    <td class="py-4">
                                        <p class="font-bold text-slate-700">Last billed: {{ $vm->last_billed_at?->diffForHumans() ?? 'never' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Unbilled: {{ number_format((int) ($vm->unbilled_amount ?? 0)) }}</p>
                                    </td>
                                    <td class="py-4">
                                        <form method="POST" action="{{ route('admin.proxmox-servers.stale-virtual-machines.destroy', [$server, $vm]) }}" onsubmit="return confirm('Delete this stale record from the application? Proxmox will be checked again before cleanup.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg bg-red-50 px-4 py-2 text-sm font-black text-red-700 transition hover:bg-red-100">Delete local record</button>
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

    <section x-cloak x-show="activeTab === 'performance'" class="mt-6 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-black">Node Performance</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">CPU Usage, Server Load, Memory Usage, and Network Traffic from Proxmox RRD data.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="node" @change="load()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <template x-for="item in nodes" :key="item">
                            <option :value="item" x-text="item"></option>
                        </template>
                    </select>
                    <select x-model="timeframe" @change="load()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <option value="hour">Last hour</option>
                        <option value="day">Last day</option>
                        <option value="week">Last week</option>
                        <option value="month">Last month</option>
                        <option value="year">Last year</option>
                    </select>
                    <button type="button" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]" @click="load()">Refresh</button>
                </div>
            </div>

            <div class="grid gap-4 border-b border-slate-200 bg-slate-50 p-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">CPU</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="formatPercent(latest.cpu_percent)"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Server Load</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="latest.load ?? '—'"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Memory</p>
                    <p class="mt-2 text-2xl font-black text-[#0069FF]" x-text="formatPercent(latest.memory_percent)"></p>
                    <p class="mt-1 text-xs font-bold text-slate-400" x-text="latest.memory_used && latest.memory_total ? `${latest.memory_used} / ${latest.memory_total}` : ''"></p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Network</p>
                    <p class="mt-2 text-sm font-black text-[#0069FF]">In <span x-text="formatRate(latest.netin_bytes_per_second)"></span></p>
                    <p class="mt-1 text-sm font-black text-amber-600">Out <span x-text="formatRate(latest.netout_bytes_per_second)"></span></p>
                </div>
            </div>

            <div x-show="loading" class="p-8 text-center text-sm font-bold text-slate-500">Loading Proxmox graph data...</div>
            <div x-show="error" class="m-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" x-text="error"></div>
            <div x-show="!loading && !error && samples.length === 0" class="p-8 text-center text-sm font-bold text-slate-500">No graph samples were returned by Proxmox for this node/timeframe.</div>

            <div x-show="!loading && samples.length > 0" class="grid gap-5 p-5 xl:grid-cols-2">
                <template x-for="graph in graphs" :key="graph.key">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-black" x-text="graph.label"></h3>
                                <p class="mt-1 text-xs font-bold text-slate-400" x-text="graph.help"></p>
                            </div>
                            <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600" x-text="`${samples.length} points`"></span>
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
</div>

<script>
    function proxmoxMetrics(config) {
        return {
            activeTab: 'overview',
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
                { key: 'cpu', label: 'CPU Usage', help: 'Percent used', color: '#34d399', max: 100, maxLabel: '100%' },
                { key: 'loadavg', label: 'Server Load', help: 'Load average', color: '#f59e0b', max: null, maxLabel: 'auto' },
                { key: 'memory', label: 'Memory Usage', help: 'Percent used', color: '#38bdf8', max: 100, maxLabel: '100%' },
                { key: 'network', label: 'Network Traffic', help: 'Total in/out bytes per second', color: '#fb7185', max: null, maxLabel: 'auto' },
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
                        throw new Error(payload.message || 'Unable to load metrics.');
                    }

                    const data = payload.data || {};
                    this.samples = data.samples || [];
                    this.latest = data.latest || {};
                    this.nodes = data.nodes?.length ? data.nodes : this.nodes;
                    this.node = data.node || this.node;
                    this.loaded = true;
                } catch (error) {
                    this.error = error.message || 'Unable to load metrics.';
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
