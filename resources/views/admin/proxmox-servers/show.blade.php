@extends('layouts.admin')

@section('title', 'نمایش Proxmox')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-[#F1F7F5] px-2.5 py-1 text-xs font-black text-[#105D52]">{{ $server->environment }}</span>
                    <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">{{ $server->datacenter ?: 'No DC' }}</span>
                    @if($fallback)
                        <span class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">Fallback cache</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-black text-slate-950">{{ $server->name }}</h1>
                <p class="mt-2 font-mono text-sm text-slate-500" dir="ltr">{{ $server->baseUrl() }} · {{ $server->proxmoxUser() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white hover:bg-[#0D4C44]">Sync now</button></form>
                <a href="{{ route('admin.proxmox-servers.edit', $server) }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">ویرایش</a>
                <form method="POST" action="{{ route('admin.proxmox-servers.destroy', $server) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE') <button class="rounded-lg bg-red-50 px-5 py-3 text-sm font-black text-red-700 hover:bg-red-100">حذف</button></form>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @php($counts = $server->last_status['counts'] ?? [])
            @foreach ([['اتصال', strtoupper($server->connection_status)], ['Sync', strtoupper($server->sync_status)], ['Node', $counts['nodes'] ?? '—'], ['VM', $counts['virtual_machines'] ?? '—']] as [$label, $value])
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-black text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <h2 class="font-black">Inventory</h2>
                <p class="mt-1 text-sm text-slate-500">Live data when online, cached fallback when offline.</p>
            </div>
            <div class="overflow-x-auto p-5">
                @php($nodes = $summary['nodes'] ?? $server->remote_inventory['nodes'] ?? [])
                <table class="min-w-full text-right text-sm">
                    <thead class="text-xs font-black text-slate-500"><tr><th class="py-3">Node</th><th class="py-3">Status</th><th class="py-3">CPU</th><th class="py-3">Memory</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($nodes as $node)
                            <tr><td class="py-3 font-black">{{ $node['node'] ?? $node['name'] ?? '—' }}</td><td class="py-3">{{ $node['status'] ?? '—' }}</td><td class="py-3">{{ isset($node['cpu']) ? round($node['cpu'] * 100, 1).'%' : '—' }}</td><td class="py-3">{{ isset($node['mem'], $node['maxmem']) && $node['maxmem'] > 0 ? round($node['mem'] / $node['maxmem'] * 100, 1).'%' : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">No inventory yet. Sync when the server is reachable.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black">Pending Desired State</h2>
                <pre class="mt-4 max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-left text-xs leading-6 text-emerald-100" dir="ltr">{{ json_encode($server->desired_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            @if($server->sync_error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-7 text-red-700"><span class="font-black">Last error:</span> {{ $server->sync_error }}</div>
            @endif
        </aside>
    </div>
</div>
@endsection
