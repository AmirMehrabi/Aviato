@extends('customer.layout')

@section('title', 'سرورها')
@section('header_title', 'ماشین های ابری')
@section('header_subtitle', 'مدیریت VPSها، وضعیت، IP و هزینه ماهانه')

@php
    $activeNav = 'servers';
@endphp

@section('search_data')
[
    {
        "title": "ساخت ماشین",
        "description": "انتخاب پلن VPS و شروع مسیر ساخت",
        "type": "عملیات",
        "url": @json(route('customer.servers.create', [], false)),
        "keywords": "ساخت ماشین vps server"
    }@if ($servers->count()),@endif
@foreach ($servers as $server)
    {
        "title": @json($server->name),
        "description": @json(($server->ip_address ?: 'بدون IP').' - '.($server->node ?: 'نامشخص')),
        "type": "VM",
        "url": @json(route('customer.servers.index', [], false).'?search='.$server->name),
        "keywords": @json($server->name.' '.$server->hostname.' '.$server->ip_address.' '.$server->node.' '.$server->status)
    }@if (! $loop->last),@endif
@endforeach
]
@endsection

@section('content')
    @if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
    @if (session('provisioning_password'))<div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">Password اولیه فقط همین حالا نمایش داده می‌شود: <span dir="ltr">{{ session('provisioning_password') }}</span></div>@endif
    <section class="grid gap-3 md:grid-cols-4">
        @foreach ([
            ['label' => 'کل ماشین ها', 'value' => $summary['total'], 'hint' => 'همه VPSهای حساب', 'tone' => 'text-slate-950'],
            ['label' => 'روشن', 'value' => $summary['running'], 'hint' => 'CPU/RAM فعال', 'tone' => 'text-[#0069FF]'],
            ['label' => 'خاموش', 'value' => $summary['stopped'], 'hint' => 'فقط Disk/IP', 'tone' => 'text-slate-950'],
            ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($summary['pending_usage']), 'hint' => 'برداشت بعدی', 'tone' => $summary['pending_usage'] > 0 ? 'text-amber-600' : 'text-emerald-600'],
        ] as $metric)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                <p class="mt-2 truncate text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                <p class="mt-1 text-xs font-bold text-slate-400">{{ $metric['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">فهرست سرورها</h2>
                <p class="mt-1 text-sm text-slate-500">جستجو و فیلتر روی VPSهای همین حساب انجام می شود.</p>
            </div>
            <a href="{{ route('customer.servers.create', [], false) }}" class="inline-flex w-fit justify-center rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">
                ساخت ماشین
            </a>
        </div>

        <form class="grid gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 md:grid-cols-[minmax(0,1fr)_220px_110px]">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="جستجو نام، hostname، IP یا node..." class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10">
            <select name="status" class="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10">
                <option value="">همه وضعیت ها</option>
                <option value="running" @selected(($filters['status'] ?? '') === 'running')>روشن</option>
                <option value="stopped" @selected(($filters['status'] ?? '') === 'stopped')>خاموش</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>تعلیق</option>
            </select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-black text-white">فیلتر</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-right text-sm">
                <thead class="border-b border-slate-200 bg-white text-xs font-black text-slate-500">
                    <tr>
                        <th class="px-5 py-3">ماشین</th>
                        <th class="px-5 py-3">موقعیت</th>
                        <th class="px-5 py-3">منابع</th>
                        <th class="px-5 py-3">Provision</th>
                        <th class="px-5 py-3">وضعیت</th>
                        <th class="px-5 py-3 text-left">هزینه ماهانه</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($servers as $server)
                        @php
                            $monthlyCost = $server->isRunning()
                                ? $billing->estimateMonthly($server)
                                : $billing->estimateStoppedMonthly($server);
                            $statusLabel = match ($server->status) {
                                'running' => 'روشن',
                                'stopped' => 'خاموش',
                                'suspended' => 'تعلیق',
                                default => $server->status,
                            };
                            $statusClass = match ($server->status) {
                                'running' => 'bg-emerald-50 text-emerald-700',
                                'suspended' => 'bg-red-50 text-red-600',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="transition hover:bg-[#F8FBFF]">
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-black text-slate-950" dir="ltr">{{ $server->name }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $server->ip_address ?: 'بدون IP' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-bold text-slate-700">{{ $server->node ?: 'نامشخص' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $server->proxmoxServer?->name ?: 'local' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-bold text-slate-700">{{ $server->cpu_cores }} CPU / {{ $server->ram_gb }}GB RAM</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $server->disk_gb }}GB Disk · {{ $server->bundle?->name ?: 'Custom' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-black text-[#0069FF]">{{ $server->provisioning_status }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-left font-black text-slate-950">{{ $wallets->format($monthlyCost) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <p class="font-black text-slate-950">هنوز ماشینی برای این حساب ثبت نشده است.</p>
                                <p class="mt-2 text-sm text-slate-500">با ساخت اولین VPS می توانید مصرف و هزینه را از همین صفحه پیگیری کنید.</p>
                                <a href="{{ route('customer.servers.create', [], false) }}" class="mt-4 inline-flex rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ساخت اولین ماشین</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $servers->links() }}
        </div>
    </section>
@endsection
