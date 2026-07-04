@extends('layouts.admin')

@section('title', 'مدیریت Proxmox')

@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="{
        search: '',
        datacenter: '',
        environment: '',
        connection: '',
        sync: '',
        active: '',
        clearFilters() { this.search = ''; this.datacenter = ''; this.environment = ''; this.connection = ''; this.sync = ''; this.active = ''; },
        matches(server) {
            const haystack = [server.name, server.cluster, server.host, server.datacenter, server.environment, server.tags].join(' ').toLowerCase();
            const searchMatch = !this.search || haystack.includes(this.search.toLowerCase());
            return searchMatch
                && (!this.datacenter || server.datacenter === this.datacenter)
                && (!this.environment || server.environment === this.environment)
                && (!this.connection || server.connection === this.connection)
                && (!this.sync || server.sync === this.sync)
                && (!this.active || server.active === this.active);
        }
    }"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">سرورها و کلاسترهای Proxmox</h1>
            <p class="mt-2 text-sm text-slate-500">مدیریت endpointها، وضعیت اتصال، syncهای معوق و ظرفیت دیتاسنتر.</p>
        </div>
        <a href="{{ route('admin.proxmox-servers.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">افزودن سرور</a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['کل endpointها', $stats['total']], ['آنلاین', $stats['online']], ['آفلاین', $stats['offline']], ['در انتظار Sync', $stats['pending']]] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input x-model.debounce.150ms="search" placeholder="جستجوی زنده: نام، کلاستر، IP، دیتاسنتر، تگ..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select x-model="datacenter" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه دیتاسنترها</option>
                @foreach ($datacenters as $datacenter)
                    <option value="{{ $datacenter }}">{{ $datacenter }}</option>
                @endforeach
            </select>
            <select x-model="environment" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه محیط‌ها</option>
                @foreach ($environments as $environment)
                    <option value="{{ $environment }}">{{ $environment }}</option>
                @endforeach
            </select>
            <select x-model="connection" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه اتصال‌ها</option>
                <option value="online">آنلاین</option>
                <option value="offline">آفلاین</option>
                <option value="unknown">نامشخص</option>
            </select>
            <select x-model="sync" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه وضعیت‌های همگام‌سازی</option>
                <option value="synced">همگام</option>
                <option value="pending">در انتظار</option>
                <option value="failed">ناموفق</option>
            </select>
            <select x-model="active" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه</option>
                <option value="1">فعال</option>
                <option value="0">غیرفعال</option>
            </select>
            <button type="button" @click="clearFilters()" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</button>
        </div>
    </section>

    <section class="mt-6 grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($servers as $server)
            @php
                $counts = $server->last_status['counts'] ?? [];
                $connectionColor = ['online' => 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]', 'offline' => 'bg-red-50 text-red-700 ring-red-200', 'unknown' => 'bg-slate-100 text-slate-600 ring-slate-200'][$server->connection_status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                $syncColor = ['synced' => 'bg-[#EBF3FF] text-[#0069FF]', 'pending' => 'bg-amber-50 text-amber-700', 'failed' => 'bg-red-50 text-red-700'][$server->sync_status] ?? 'bg-slate-100 text-slate-600';
                $serverPayload = [
                    'name' => $server->name,
                    'cluster' => $server->cluster_name ?: '',
                    'host' => $server->host,
                    'datacenter' => $server->datacenter ?: '',
                    'environment' => $server->environment ?: '',
                    'connection' => $server->connection_status,
                    'sync' => $server->sync_status,
                    'active' => $server->is_active ? '1' : '0',
                    'tags' => implode(' ', $server->tags ?? []),
                ];
            @endphp
            <article
                x-cloak
                x-show='matches(@json($serverPayload))'
                x-transition.opacity.scale.95
                class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-200/70"
            >
                <div class="border-b border-slate-100 bg-gradient-to-l from-slate-50 to-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md px-2.5 py-1 text-xs font-black ring-1 {{ $connectionColor }}">{{ \App\Support\AdminUi::status($server->connection_status) }}</span>
                                <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $syncColor }}">{{ \App\Support\AdminUi::status($server->sync_status) }}</span>
                                @if($server->maintenance_mode)
                                    <span class="rounded-md bg-sky-50 px-2.5 py-1 text-xs font-black text-sky-700">حالت نگهداری</span>
                                @endif
                            </div>
                            <h2 class="mt-4 truncate text-xl font-black text-slate-950">{{ $server->name }}</h2>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $server->cluster_name ?: 'Standalone node' }} · {{ $server->environment }}</p>
                        </div>
                        <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-[#031B4E] text-lg font-black text-white shadow-lg shadow-[#031B4E]/20">P</div>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-bold text-slate-500">نودها</p>
                            <p class="mt-1 text-2xl font-black text-slate-950">{{ $counts['nodes'] ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-bold text-slate-500">ماشین‌ها</p>
                            <p class="mt-1 text-2xl font-black text-slate-950">{{ $counts['virtual_machines'] ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-bold text-slate-500">Endpoint</span>
                            <span class="font-mono text-slate-800" dir="ltr">{{ $server->host }}:{{ $server->port }}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-bold text-slate-500">Datacenter</span>
                            <span class="font-black text-slate-800">{{ $server->datacenter ?: '—' }}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-bold text-slate-500">Last seen</span>
                            <span class="text-slate-600">{{ $server->last_seen_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-bold text-slate-500">Placement limits</span>
                            <span class="font-mono text-xs text-slate-700" dir="ltr">CPU {{ $server->cpu_threshold_percent }}% · RAM {{ $server->ram_threshold_percent }}% · Disk {{ $server->disk_threshold_percent }}%</span>
                        </div>
                    </div>

                    @if(! empty($server->tags))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($server->tags as $tag)
                                <span class="rounded-full bg-[#EBF3FF] px-3 py-1 text-xs font-black text-[#0069FF]">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 pt-1">
                        <a href="{{ route('admin.proxmox-servers.show', $server) }}" class="flex-1 rounded-lg bg-[#0069FF] px-4 py-3 text-center text-sm font-black text-white transition hover:bg-[#0050D0]">نمایش</a>
                        <a href="{{ route('admin.proxmox-servers.edit', $server) }}" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">ویرایش</a>
                        <form method="POST" action="{{ route('admin.proxmox-servers.sync', $server) }}">@csrf <button class="rounded-lg bg-amber-50 px-4 py-3 text-sm font-black text-amber-700 transition hover:bg-amber-100">همگام‌سازی</button></form>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center lg:col-span-2 2xl:col-span-3">
                <h2 class="text-xl font-black text-slate-900">هنوز endpoint ثبت نشده است</h2>
                <p class="mt-2 text-slate-500">اولین سرور یا کلاستر Proxmox را اضافه کنید.</p>
                <a href="{{ route('admin.proxmox-servers.create') }}" class="mt-5 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">افزودن سرور</a>
            </div>
        @endforelse
    </section>
</div>
@endsection
