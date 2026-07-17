@extends('customer.layout')

@section('title', 'سرورها')
@section('header_title', 'ماشین های ابری')
@section('header_subtitle', 'وضعیت، اتصال، هزینه و عملیات ماشین های مجازی شما')
@section('breadcrumbs')
    <span class="truncate text-slate-700">سرورها</span>
@endsection

@php
    $activeNav = 'servers';
    $hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null);
    $serverStatusRows = $serverRows->map(fn ($server) => [
        'id' => $server['id'],
        'status' => $server['status'],
        'status_label' => $server['status_label'],
        'status_class' => $server['status_class'],
        'provisioning_label' => $server['provisioning_label'],
        'provisioning_class' => $server['provisioning_class'],
        'provisioning_pending' => $server['provisioning_pending'],
        'action_pending' => $server['provisioning_pending'] || ($server['is_deleting'] && ! $server['delete_failed'] && ! $server['delete_stale']),
        'is_deleting' => $server['is_deleting'],
        'delete_failed' => $server['delete_failed'],
        'delete_stale' => $server['delete_stale'],
        'is_deleted' => $server['is_deleted'],
        'ip' => $server['ip'],
        'ssh_ready' => $server['ssh_ready'],
        'ssh_label' => $server['ssh_ready'] ? 'SSH آماده' : 'اتصال در انتظار',
        'ssh_class' => $server['ssh_ready'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
    ])->values();
    $attentionItems = collect([
        $summary['failed'] > 0 ? ['tone' => 'red', 'text' => $summary['failed'].' ماشین آماده استفاده نشده است؛ وضعیت آن را بررسی کنید.'] : null,
        $summary['delete_failed'] > 0 ? ['tone' => 'red', 'text' => $summary['delete_failed'].' حذف ناموفق مانده است؛ از صفحه همان سرور دوباره تلاش کنید.'] : null,
        $summary['delete_stale'] > 0 ? ['tone' => 'red', 'text' => $summary['delete_stale'].' حذف بیش از حد منتظر مانده است؛ از صفحه همان سرور دوباره تلاش کنید.'] : null,
        $summary['pending'] > 0 ? ['tone' => 'blue', 'text' => $summary['pending'].' ماشین هنوز در حال آماده سازی است؛ SSH بعد از آماده شدن فعال می شود.'] : null,
        $summary['deleting'] > 0 ? ['tone' => 'amber', 'text' => $summary['deleting'].' ماشین در حال حذف است؛ Billing آن متوقف شده و وضعیت از همین صفحه به‌روزرسانی می‌شود.'] : null,
        $summary['pending_usage'] > 0 ? ['tone' => 'amber', 'text' => 'مصرف ثبت نشده فعلی: '.$wallets->format($summary['pending_usage'])] : null,
    ])->filter()->values();
@endphp

@section('search_data')
[
    {
        "title": "ساخت ماشین",
        "description": "انتخاب پلن ماشین مجازی و شروع مسیر ساخت",
        "type": "عملیات",
        "url": @json(route('customer.servers.create', [], false)),
        "keywords": "ساخت ماشین vps server"
    }@if ($serverRows->isNotEmpty()),@endif
@foreach ($serverRows as $server)
    {
        "title": @json($server['name']),
        "description": @json($server['ip'].' - '.$server['resources']),
        "type": "ماشین مجازی",
        "url": @json($server['show_url']),
        "keywords": @json($server['name'].' '.$server['internal_name'].' '.$server['hostname'].' '.$server['ip'].' '.$server['status'])
    }@if (! $loop->last),@endif
@endforeach
]
@endsection

@section('content')
    <div
        x-data="customerServerStatus({
            url: @js(route('customer.servers.statuses', [], false)),
            servers: @js($serverStatusRows),
        })"
        class="space-y-5"
    >
        @if ($attentionItems->isNotEmpty())
            <section class="grid gap-3 lg:grid-cols-3">
                @foreach ($attentionItems as $item)
                    <div class="rounded-2xl border px-4 py-3 text-sm font-bold leading-7 {{ $item['tone'] === 'red' ? 'border-red-200 bg-red-50 text-red-800' : ($item['tone'] === 'blue' ? 'border-blue-200 bg-blue-50 text-[#0050D0]' : 'border-amber-200 bg-amber-50 text-amber-900') }}">
                        {{ $item['text'] }}
                    </div>
                @endforeach
            </section>
        @endif

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['label' => 'کل ماشین ها', 'value' => $summary['total'], 'hint' => 'همه ماشین های این فضا', 'tone' => 'text-slate-950'],
                ['label' => 'روشن', 'value' => $summary['running'], 'hint' => 'CPU/RAM فعال', 'tone' => 'text-[#0069FF]'],
                ['label' => 'خاموش', 'value' => $summary['stopped'], 'hint' => 'دیسک و IP همچنان هزینه دارند', 'tone' => 'text-slate-950'],
                ['label' => 'در حال آماده سازی', 'value' => $summary['pending'], 'hint' => 'تا تکمیل، SSH فعال نیست', 'tone' => $summary['pending'] > 0 ? 'text-[#0069FF]' : 'text-slate-950'],
                ['label' => 'برآورد ماهانه', 'value' => $wallets->format($summary['monthly_spend']), 'hint' => 'بر اساس وضعیت و دیسک های فعال', 'tone' => 'text-emerald-700'],
            ] as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                    <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 truncate text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    <p class="mt-1 truncate text-xs font-bold text-slate-400">{{ $metric['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-xl font-black text-slate-950">فهرست ماشین ها</h2>
                    <p class="mt-1 text-sm leading-7 text-slate-500">{{ $servers->total() }} ماشین در این فضا؛ جستجو بر اساس نام، IP یا hostname.</p>
                </div>
                <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex w-fit justify-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">
                    ساخت ماشین
                </a>
            </div>

            <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 xl:grid-cols-[minmax(0,1fr)_auto_auto]">
                <div class="relative">
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                    </svg>
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="جستجو نام، hostname یا IP..." aria-label="جستجوی ماشین ها" class="h-12 w-full rounded-xl border border-slate-200 bg-white pr-11 pl-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10">
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        '' => 'همه',
                        'running' => 'روشن',
                        'stopped' => 'خاموش',
                        'suspended' => 'تعلیق',
                        'deleting' => 'در حال حذف',
                    ] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="{{ $value }}" class="peer sr-only" @checked(($filters['status'] ?? '') === $value)>
                            <span class="inline-flex h-12 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-600 transition peer-checked:border-[#0069FF] peer-checked:bg-[#EBF3FF] peer-checked:text-[#0069FF] hover:border-[#B8D6FF]">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="flex gap-2">
                    <button class="inline-flex h-12 flex-1 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-[#0069FF] xl:flex-none">اعمال فیلتر</button>
                    @if ($hasFilters)
                        <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex h-12 flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50 xl:flex-none">پاک کردن</a>
                    @endif
                </div>
            </form>

            @if ($serverRows->isEmpty())
                <div class="px-5 py-14 text-center">
                    @if ($summary['total'] === 0)
                        <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-[#EBF3FF] text-[#0069FF]">
                            <svg class="size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 8a6 6 0 0 1 11.7-1.9A5 5 0 0 1 18 16H7a5 5 0 0 1-1-9.9Z" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9 16v3m3-3v3m3-3v3M8 21h8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3 class="mt-5 text-2xl font-black text-slate-950">هنوز ماشینی ندارید</h3>
                        <p class="mt-2 text-sm font-bold leading-7 text-slate-500">با ساخت اولین ماشین مجازی، وضعیت، IP، هزینه و دسترسی SSH آن از همین صفحه قابل پیگیری است.</p>
                        <a href="{{ route('customer.servers.create', [], false) }}" class="mt-5 inline-flex rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ساخت اولین ماشین</a>
                    @else
                        <h3 class="text-xl font-black text-slate-950">نتیجه ای با این فیلتر پیدا نشد</h3>
                        <p class="mt-2 text-sm font-bold leading-7 text-slate-500">عبارت جستجو یا وضعیت انتخاب شده را تغییر دهید.</p>
                        <a href="{{ route('customer.servers.index', [], false) }}" class="mt-5 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن فیلترها</a>
                    @endif
                </div>
            @else
                <div class="grid gap-4 p-5 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($serverRows as $server)
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-[#B8D6FF] hover:shadow-lg hover:shadow-[#0069FF]/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ $server['show_url'] }}" class="block truncate text-lg font-black text-slate-950 transition hover:text-[#0069FF]" dir="ltr">{{ $server['name'] }}</a>
                                    <p class="mt-1 truncate text-xs font-bold text-slate-500" dir="ltr">{{ $server['hostname'] }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span class="inline-flex items-center gap-2 rounded-xl px-2.5 py-1 text-xs font-black" :class="server(@js($server['id'])).status_class">
                                        <span x-show="server(@js($server['id'])).is_deleting" class="size-3 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-600"></span>
                                        <span x-text="server(@js($server['id'])).status_label">{{ $server['status_label'] }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-xl px-2.5 py-1 text-xs font-black" :class="server(@js($server['id'])).provisioning_class">
                                        <span x-show="server(@js($server['id'])).provisioning_pending" class="size-3 animate-spin rounded-full border-2 border-[#0069FF]/30 border-t-[#0069FF]"></span>
                                        <span x-text="server(@js($server['id'])).provisioning_label">{{ $server['provisioning_label'] }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-slate-500">IP Address</p>
                                        <p class="mt-1 truncate text-base font-black text-slate-950" dir="ltr" x-text="server(@js($server['id'])).ip">{{ $server['ip'] }}</p>
                                    </div>
                                    <span class="rounded-xl px-3 py-1.5 text-xs font-black" :class="server(@js($server['id'])).ssh_class" x-text="server(@js($server['id'])).ssh_label">{{ $server['ssh_ready'] ? 'SSH آماده' : 'اتصال در انتظار' }}</span>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                @foreach ([
                                    ['label' => 'CPU', 'value' => $server['cpu_cores'].' هسته'],
                                    ['label' => 'RAM', 'value' => $server['ram_gb'].' گیگ'],
                                    ['label' => 'دیسک', 'value' => $server['disk_gb'].' گیگ'],
                                ] as $resource)
                                    <div class="rounded-xl border border-slate-100 bg-white px-2 py-3">
                                        <p class="text-[11px] font-black text-slate-400">{{ $resource['label'] }}</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">{{ $resource['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-slate-500">موقعیت</span>
                                    <span class="truncate font-black text-slate-950">{{ $server['location'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-slate-500">سیستم عامل</span>
                                    <span class="truncate font-black text-slate-950">{{ $server['image'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-slate-500">پلن</span>
                                    <span class="truncate font-black text-slate-950">{{ $server['plan'] }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-slate-500">برآورد ماهانه</span>
                                    <span class="font-black text-slate-950">{{ $server['is_deleting'] ? 'متوقف شده' : $wallets->format($server['monthly_cost']) }}</span>
                                </div>
                                @if ($server['extra_disk_count'] > 0)
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="font-bold text-slate-500">دیسک اضافه</span>
                                        <span class="font-black text-slate-950">{{ $server['extra_disk_count'] }} عدد · +{{ $wallets->format($server['extra_disk_monthly_cost']) }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-slate-500">محاسبه</span>
                                    <span class="font-black text-slate-950">{{ $server['billing_hint'] }}</span>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-2 xl:grid-cols-4">
                                <a href="{{ $server['show_url'] }}" class="inline-flex justify-center rounded-xl bg-slate-950 px-3 py-2.5 text-xs font-black text-white transition hover:bg-[#0069FF]">جزئیات و اتصال</a>
                                @if ($server['console_ready'])
                                    <a href="{{ $server['console_url'] }}" class="inline-flex justify-center rounded-xl bg-[#0069FF] px-3 py-2.5 text-xs font-black text-white transition hover:bg-[#0050D0]">کنسول</a>
                                @else
                                    <span class="inline-flex cursor-not-allowed justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-black text-slate-400">کنسول</span>
                                @endif
                                <a href="{{ $server['monitoring_url'] }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">مانیتورینگ</a>
                                <a href="{{ $server['backup_url'] }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">بکاپ</a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $servers->links() }}
                </div>
            @endif
        </section>
    </div>

    <script>
    function customerServerStatus(config) {
        return {
            url: config.url,
            servers: Object.fromEntries((config.servers || []).map((server) => [server.id, server])),
            interval: null,
            init() {
                if (!Object.keys(this.servers).length) return;
                this.refresh();
                this.interval = window.setInterval(() => this.refresh(), 5000);
            },
            server(id) {
                return this.servers[id] || {
                    status_label: '-',
                    status_class: 'bg-slate-100 text-slate-600',
                    provisioning_label: '-',
                    provisioning_class: 'bg-slate-100 text-slate-600',
                    provisioning_pending: false,
                    action_pending: false,
                    is_deleting: false,
                    delete_stale: false,
                    is_deleted: false,
                    ip: 'بدون IP',
                    ssh_ready: false,
                    ssh_label: 'اتصال در انتظار',
                    ssh_class: 'bg-amber-50 text-amber-700',
                };
            },
            refresh() {
                const ids = Object.keys(this.servers);
                if (!ids.length) return;

                const params = new URLSearchParams();
                ids.forEach((id) => params.append('ids[]', id));

                fetch(`${this.url}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(response))
                    .then((data) => {
                        (data.servers || []).forEach((server) => {
                            this.servers[server.id] = server;
                        });

                        const hasPending = Object.values(this.servers).some((server) => server.action_pending);
                        if (!hasPending && this.interval) {
                            window.clearInterval(this.interval);
                            this.interval = null;
                        }
                    })
                    .catch(() => {});
            },
        };
    }
    </script>
@endsection
