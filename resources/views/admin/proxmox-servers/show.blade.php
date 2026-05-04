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
@endphp

<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ session('error') }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#0A3D37] p-6 text-white shadow-xl shadow-[#0A3D37]/15">
        <div class="absolute -left-20 top-0 size-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-emerald-50">{{ $server->environment }}</span>
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-black text-emerald-50">{{ $server->datacenter ?: 'No DC' }}</span>
                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $server->connection_status === 'online' ? 'bg-emerald-300 text-emerald-950' : 'bg-amber-300 text-amber-950' }}">{{ strtoupper($server->connection_status) }}</span>
                    @if($fallback)
                        <span class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-900">Fallback cache</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-black md:text-4xl">{{ $server->name }}</h1>
                <p class="mt-2 font-mono text-sm text-emerald-50/70" dir="ltr">{{ $server->baseUrl() }} · {{ $server->proxmoxUser() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#0A3D37] hover:bg-slate-100">Sync now</button></form>
                <a href="{{ route('admin.proxmox-servers.edit', $server) }}" class="rounded-lg border border-white/15 bg-white/10 px-5 py-3 text-sm font-black text-white hover:bg-white/15">ویرایش</a>
                <form method="POST" action="{{ route('admin.proxmox-servers.destroy', $server) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE') <button class="rounded-lg bg-red-400/15 px-5 py-3 text-sm font-black text-red-50 hover:bg-red-400/25">حذف</button></form>
            </div>
        </div>

        <div class="relative mt-6 grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-emerald-50/65">Nodes</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['nodes'] ?? count($nodes) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-black"><span class="rounded bg-emerald-300 px-2 py-1 text-emerald-950">Online {{ $counts['online_nodes'] ?? 0 }}</span><span class="rounded bg-red-200 px-2 py-1 text-red-900">Offline {{ $counts['offline_nodes'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-emerald-50/65">Virtual Machines</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['virtual_machines'] ?? count($vms) }}</p>
                <div class="mt-3 flex gap-2 text-xs font-black"><span class="rounded bg-emerald-300 px-2 py-1 text-emerald-950">Online {{ $counts['running_virtual_machines'] ?? 0 }}</span><span class="rounded bg-red-200 px-2 py-1 text-red-900">Offline {{ $counts['offline_virtual_machines'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-emerald-50/65">Storage Pools</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['storage'] ?? count($storages) }}</p>
                <p class="mt-3 text-xs font-bold text-emerald-50/60">local, directory, LVM, ZFS, NFS...</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-black text-emerald-50/65">Backups</p>
                <p class="mt-3 text-3xl font-black">{{ $counts['backups'] ?? count($backups) }}</p>
                <p class="mt-3 text-xs font-bold text-emerald-50/60">Detected from backup-capable storages</p>
            </div>
        </div>
    </div>

    @if(! empty($endpointErrors))
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
            <span class="font-black">Some Proxmox endpoints were not accessible:</span>
            {{ collect($endpointErrors)->map(fn($error, $path) => $path.' '.$error)->implode(' · ') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_420px]">
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
                                    <td class="py-3"><span class="rounded-md px-2.5 py-1 text-xs font-black {{ ($node['status'] ?? null) === 'online' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ strtoupper($node['status'] ?? 'unknown') }}</span></td>
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
                    <div class="flex gap-2"><button class="rounded-lg bg-[#105D52] px-4 py-2 text-sm font-black text-white" disabled>Start</button><button class="rounded-lg bg-red-50 px-4 py-2 text-sm font-black text-red-700" disabled>Stop</button></div>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs font-black text-slate-500"><tr><th class="py-3">VM</th><th class="py-3">Node</th><th class="py-3">Status</th><th class="py-3">CPU</th><th class="py-3">Memory</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($vms as $vm)
                                <tr>
                                    <td class="py-3 font-black">{{ $vm['name'] ?? $vm['vmid'] ?? '—' }}</td>
                                    <td class="py-3">{{ $vm['node'] ?? '—' }}</td>
                                    <td class="py-3"><span class="rounded-md px-2.5 py-1 text-xs font-black {{ ($vm['status'] ?? null) === 'running' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ strtoupper($vm['status'] ?? 'unknown') }}</span></td>
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
                            <div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-[#105D52]" style="width: {{ $usedPercent ?? 0 }}%"></div></div>
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
                    <button class="rounded-lg bg-[#105D52] px-3 py-2 text-xs font-black text-white" disabled>Run backup</button>
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
                <pre class="mt-4 max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-left text-xs leading-6 text-emerald-100" dir="ltr">{{ json_encode($server->desired_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>

            @if($server->sync_error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-7 text-red-700"><span class="font-black">Last error:</span> {{ $server->sync_error }}</div>
            @endif
        </aside>
    </div>
</div>
@endsection
