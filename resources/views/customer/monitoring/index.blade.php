@extends('customer.layout')

@section('title', 'مانیتورینگ')
@section('header_title', 'مانیتورینگ')
@section('header_subtitle', 'وضعیت زنده، نمودار مصرف و سلامت بکاپ VPSها')

@php
    $activeNav = 'monitoring';
@endphp

@section('search_data')
[
    {"title":"مانیتورینگ","description":"نمودار مصرف و سلامت VPSها","type":"صفحه","url":@json(route('customer.monitoring.index', [], false)),"keywords":"monitoring metrics cpu ram network مانیتورینگ"}
    @foreach ($servers as $server)
        ,{"title":@json('مانیتورینگ '.$server->name),"description":@json(($server->ip_address ?: 'بدون IP').' - '.($server->node ?: 'نامشخص')),"type":"VM","url":@json(route('customer.monitoring.index', ['server' => $server->uuid], false)),"keywords":@json($server->name.' '.$server->hostname.' '.$server->ip_address.' '.$server->node.' monitoring')}
    @endforeach
]
@endsection

@section('content')
    <div
        x-data="customerMonitoring({
            servers: @js($serverOptions),
            selectedId: @js($selected?->uuid),
        })"
        x-init="init()"
        class="space-y-5"
    >
        @if ($servers->isEmpty())
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="font-black text-slate-950">برای مشاهده مانیتورینگ ابتدا VPS بسازید.</p>
                <p class="mt-2 text-sm text-slate-500">بعد از آماده شدن ماشین، نمودارهای CPU، RAM، شبکه و وضعیت بکاپ از این صفحه نمایش داده می‌شود.</p>
                <a href="{{ route('customer.servers.create', [], false) }}" class="mt-4 inline-flex rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ساخت ماشین مجازی</a>
            </section>
        @else
            <section class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
                <aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                    <div class="border-b border-slate-200 p-5">
                        <p class="text-xs font-black text-slate-500">انتخاب VPS</p>
                        <h2 class="mt-2 text-lg font-black text-slate-950">مانیتورینگ ماشین</h2>
                    </div>
                    <div class="max-h-[520px] divide-y divide-slate-100 overflow-y-auto">
                        <template x-for="server in servers" :key="server.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-5 py-4 text-right transition hover:bg-[#F8FBFF]"
                                :class="selectedId === server.id ? 'bg-[#EBF3FF]' : 'bg-white'"
                                @click="selectServer(server.id)"
                            >
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg text-xs font-black" :class="server.status === 'running' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" x-text="server.status === 'running' ? 'ON' : 'OFF'"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-black text-slate-950" dir="ltr" x-text="server.name"></span>
                                    <span class="mt-1 block truncate text-xs font-bold text-slate-500" dir="ltr" x-text="`${server.ip_address || 'no-ip'} · VMID ${server.vmid || '—'}`"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </aside>

                <section class="space-y-4">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-500">VPS انتخاب‌شده</p>
                                <h2 class="mt-1 truncate text-xl font-black text-slate-950" dir="ltr" x-text="selected?.name || '—'"></h2>
                                <p class="mt-1 truncate text-xs font-bold text-slate-500" dir="ltr" x-text="selected ? `${selected.ip_address || 'no-ip'} · ${selected.node || 'node'} · ${selected.cpu_cores} CPU / ${selected.ram_gb}GB RAM / ${selected.disk_gb}GB Disk` : ''"></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <select x-model="timeframe" @change="load()" class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold outline-none focus:border-[#0069FF]">
                                    <option value="hour">یک ساعت اخیر</option>
                                    <option value="day">یک روز اخیر</option>
                                    <option value="week">یک هفته اخیر</option>
                                    <option value="month">یک ماه اخیر</option>
                                    <option value="year">یک سال اخیر</option>
                                </select>
                                <button type="button" @click="load()" class="rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">بروزرسانی</button>
                            </div>
                        </div>

                        <div x-show="error" class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-8 text-center text-sm font-bold text-slate-500">در حال دریافت داده از Proxmox...</div>

                        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-5 md:grid-cols-2 xl:grid-cols-5">
                            <template x-for="card in cards" :key="card.label">
                                <article class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-black text-slate-500" x-text="card.label"></p>
                                    <p class="mt-2 truncate text-xl font-black text-slate-950" dir="ltr" x-text="card.value"></p>
                                    <p class="mt-1 truncate text-xs font-bold text-slate-400" x-text="card.hint"></p>
                                </article>
                            </template>
                        </div>

                        <div x-show="!loading && samples.length === 0 && !error" class="p-8 text-center text-sm font-bold text-slate-500">برای این بازه زمانی نمونه‌ای از Proxmox دریافت نشد.</div>

                        <div x-show="samples.length > 0" class="grid gap-5 p-5 xl:grid-cols-2">
                            <template x-for="graph in graphs" :key="graph.key">
                                <article class="rounded-xl border border-slate-200 bg-white p-5">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="font-black text-slate-950" x-text="graph.label"></h3>
                                            <p class="mt-1 text-xs font-bold text-slate-400" x-text="graph.help"></p>
                                        </div>
                                        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500" x-text="`${samples.length} نقطه`"></span>
                                    </div>
                                    <svg viewBox="0 0 720 250" class="h-64 w-full overflow-visible rounded-lg bg-slate-950 p-2" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient :id="`${graph.key}-monitoring-fill`" x1="0" x2="0" y1="0" y2="1">
                                                <stop offset="0%" :stop-color="graph.color" stop-opacity="0.34"></stop>
                                                <stop offset="100%" :stop-color="graph.color" stop-opacity="0.02"></stop>
                                            </linearGradient>
                                        </defs>
                                        <g class="text-slate-700">
                                            <line x1="34" y1="28" x2="34" y2="212" stroke="currentColor" stroke-width="1"></line>
                                            <line x1="34" y1="212" x2="700" y2="212" stroke="currentColor" stroke-width="1"></line>
                                            <line x1="34" y1="151" x2="700" y2="151" stroke="currentColor" stroke-width="1" stroke-dasharray="5 8"></line>
                                            <line x1="34" y1="90" x2="700" y2="90" stroke="currentColor" stroke-width="1" stroke-dasharray="5 8"></line>
                                        </g>
                                        <path :d="areaPath(graph)" :fill="`url(#${graph.key}-monitoring-fill)`"></path>
                                        <path :d="linePath(graph)" fill="none" :stroke="graph.color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <text x="38" y="22" fill="#94a3b8" font-size="12" font-weight="800" x-text="graph.maxLabel"></text>
                                        <text x="38" y="238" fill="#94a3b8" font-size="12" font-weight="800" x-text="timeLabel(samples[0]?.time)"></text>
                                        <text x="610" y="238" fill="#94a3b8" font-size="12" font-weight="800" x-text="timeLabel(samples[samples.length - 1]?.time)"></text>
                                    </svg>
                                </article>
                            </template>
                        </div>
                    </div>
                </section>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-black text-slate-950">هشدارهای سلامت</h2>
                            <p class="mt-1 text-sm text-slate-500">هشدارهای V1 فقط در همین صفحه نمایش داده می‌شوند.</p>
                        </div>
                        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500" x-text="`${alerts.length} مورد`"></span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <template x-for="alert in alerts" :key="alert.key">
                            <div class="rounded-lg border px-4 py-3 text-sm font-bold" :class="alert.tone === 'red' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'" x-text="alert.message"></div>
                        </template>
                        <div x-show="alerts.length === 0" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">هشدار فعالی برای این VPS وجود ندارد.</div>
                    </div>
                </div>

                <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="font-black text-slate-950">سلامت بکاپ</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">برنامه خودکار</span>
                            <span class="font-black text-slate-950" x-text="backup.policy_enabled ? 'فعال' : 'غیرفعال'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">نگهداری</span>
                            <span class="font-black text-slate-950" x-text="backup.retention_count ? `${backup.retention_count} نسخه` : '—'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">بکاپ آماده</span>
                            <span class="font-black text-slate-950" x-text="backup.ready_count ?? 0"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">اجرای بعدی</span>
                            <span class="font-black text-slate-950" dir="ltr" x-text="formatDate(backup.next_run_at)"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-500">آخرین وضعیت</span>
                            <span class="font-black text-slate-950" x-text="backup.last_status || '—'"></span>
                        </div>
                    </div>
                    <p x-show="backup.last_error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-800" x-text="backup.last_error"></p>
                    <a href="{{ route('customer.backups.index', [], false) }}" class="mt-5 inline-flex w-full justify-center rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-black text-white">مدیریت بکاپ</a>
                </aside>
            </section>
        @endif
    </div>

    <script>
        function customerMonitoring(config) {
            return {
                servers: config.servers || [],
                selectedId: config.selectedId,
                timeframe: 'hour',
                loading: false,
                error: null,
                samples: [],
                latest: {},
                backup: {},
                graphs: [
                    { key: 'cpu', label: 'CPU', help: 'درصد مصرف پردازنده', color: '#0069FF', max: 100, maxLabel: '100%' },
                    { key: 'memory', label: 'RAM', help: 'درصد مصرف حافظه', color: '#00A67E', max: 100, maxLabel: '100%' },
                    { key: 'network', label: 'Network', help: 'مجموع ورودی و خروجی', color: '#f59e0b', max: null, maxLabel: 'auto' },
                    { key: 'diskio', label: 'Disk I/O', help: 'مجموع خواندن و نوشتن دیسک', color: '#fb7185', max: null, maxLabel: 'auto' },
                ],
                init() {
                    this.selectedId ||= this.servers[0]?.id || null;
                    this.backup = this.selected?.backup_health || {};
                    if (this.selectedId) this.load();
                },
                get selected() {
                    return this.servers.find((server) => server.id === this.selectedId) || null;
                },
                get cards() {
                    return [
                        { label: 'وضعیت', value: this.latest.status || this.selected?.status || '—', hint: this.formatUptime(this.latest.uptime_seconds) },
                        { label: 'CPU', value: this.formatPercent(this.latest.cpu_percent), hint: `${this.selected?.cpu_cores || '—'} vCPU` },
                        { label: 'RAM', value: this.formatPercent(this.latest.memory_percent), hint: `${this.latest.memory_used || '—'} / ${this.latest.memory_total || `${this.selected?.ram_gb || '—'} GB`}` },
                        { label: 'Network', value: `↓ ${this.formatRate(this.latest.netin_bytes_per_second)}`, hint: `↑ ${this.formatRate(this.latest.netout_bytes_per_second)}` },
                        { label: 'Disk I/O', value: `R ${this.formatRate(this.latest.diskread_bytes_per_second)}`, hint: `W ${this.formatRate(this.latest.diskwrite_bytes_per_second)}` },
                    ];
                },
                get alerts() {
                    const items = [];
                    if ((this.latest.status || this.selected?.status) && (this.latest.status || this.selected?.status) !== 'running') {
                        items.push({ key: 'down', tone: 'red', message: 'این VPS در حال حاضر روشن نیست.' });
                    }
                    if (Number(this.latest.cpu_percent || 0) >= 90) {
                        items.push({ key: 'cpu', tone: 'amber', message: 'مصرف CPU بالاتر از ۹۰٪ است.' });
                    }
                    if (Number(this.latest.memory_percent || 0) >= 90) {
                        items.push({ key: 'memory', tone: 'amber', message: 'مصرف RAM بالاتر از ۹۰٪ است.' });
                    }
                    if (!this.backup.policy_enabled) {
                        items.push({ key: 'backup-disabled', tone: 'amber', message: 'بکاپ خودکار برای این VPS فعال نیست.' });
                    }
                    if (this.backup.last_status === 'failed') {
                        items.push({ key: 'backup-failed', tone: 'red', message: 'آخرین بکاپ این VPS ناموفق بوده است.' });
                    }
                    return items;
                },
                selectServer(id) {
                    this.selectedId = id;
                    this.samples = [];
                    this.latest = {};
                    this.backup = this.selected?.backup_health || {};
                    this.error = null;
                    const url = new URL(window.location.href);
                    url.searchParams.set('server', id);
                    window.history.replaceState({}, '', url.toString());
                    this.load();
                },
                async load() {
                    if (!this.selected?.metrics_url) return;
                    this.loading = true;
                    this.error = null;
                    try {
                        const params = new URLSearchParams({ timeframe: this.timeframe });
                        const response = await fetch(`${this.selected.metrics_url}?${params.toString()}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.message || payload.error || 'Monitoring data could not be loaded.');
                        }
                        const data = payload.data || {};
                        this.samples = data.samples || [];
                        this.latest = data.latest || {};
                        this.backup = data.backup_health || this.selected.backup_health || {};
                    } catch (error) {
                        this.error = error.message || 'Monitoring data could not be loaded.';
                    } finally {
                        this.loading = false;
                    }
                },
                value(sample, graph) {
                    if (!sample) return 0;
                    if (graph.key === 'cpu') return Number(sample.cpu || 0) * 100;
                    if (graph.key === 'memory') return Number(sample.maxmem || 0) > 0 ? (Number(sample.mem || 0) / Number(sample.maxmem)) * 100 : 0;
                    if (graph.key === 'network') return Number(sample.netin || 0) + Number(sample.netout || 0);
                    if (graph.key === 'diskio') return Number(sample.diskread || 0) + Number(sample.diskwrite || 0);
                    return 0;
                },
                scaleMax(graph) {
                    if (graph.max) return graph.max;
                    const max = Math.max(...this.samples.map((sample) => this.value(sample, graph)), 1);
                    graph.maxLabel = ['network', 'diskio'].includes(graph.key) ? this.formatRate(max) : Math.ceil(max * 10) / 10;
                    return max * 1.12;
                },
                points(graph) {
                    const max = this.scaleMax(graph);
                    return this.samples.map((sample, index) => {
                        const x = 34 + (index / Math.max(this.samples.length - 1, 1)) * 666;
                        const y = 212 - (Math.min(this.value(sample, graph), max) / max) * 184;
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
                    return `${this.linePath(graph)} L ${points[points.length - 1][0].toFixed(2)} 212 L ${points[0][0].toFixed(2)} 212 Z`;
                },
                formatPercent(value) {
                    return value === null || value === undefined ? '—' : `${Number(value).toFixed(1)}%`;
                },
                formatRate(value) {
                    if (value === null || value === undefined) return '—';
                    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
                    let size = Number(value);
                    let unit = 0;
                    while (size >= 1024 && unit < units.length - 1) {
                        size /= 1024;
                        unit++;
                    }
                    return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unit]}`;
                },
                formatUptime(seconds) {
                    if (!seconds) return 'بدون uptime';
                    const days = Math.floor(Number(seconds) / 86400);
                    const hours = Math.floor((Number(seconds) % 86400) / 3600);
                    return days > 0 ? `${days}d ${hours}h uptime` : `${hours}h uptime`;
                },
                formatDate(value) {
                    if (!value) return '—';
                    return new Date(value).toLocaleString('fa-IR');
                },
                timeLabel(value) {
                    if (!value) return '';
                    return new Date(Number(value) * 1000).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
                },
            };
        }
    </script>
@endsection
