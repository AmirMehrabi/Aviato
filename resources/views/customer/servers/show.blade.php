@extends('customer.layout')

@section('title', 'جزئیات سرور')
@section('header_title', 'جزئیات '.$server->name)
@section('header_subtitle', 'مشخصات، وضعیت و هزینه تقریبی ماهانه سرور')

@php
    $activeNav = 'servers';
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
    $monthlyCost = $server->isRunning()
        ? $billing->estimateMonthly($server)
        : $billing->estimateStoppedMonthly($server);
@endphp

@section('search_data')
[
    {
        "title": "بازگشت به فهرست سرورها",
        "description": "مشاهده همه ماشین های این حساب",
        "type": "صفحه",
        "url": @json(route('customer.servers.index', [], false)),
        "keywords": "servers list vps"
    }
]
@endsection

@section('content')
    <section class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                بازگشت به فهرست
            </a>
            <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">نام ماشین</p>
                <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $server->name }}</p>
                <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $server->hostname ?: '-' }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">IP</p>
                <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $server->ip_address ?: 'بدون IP' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $server->reservedIpAddress?->pool?->name ?: 'بدون Pool' }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
                <p class="text-xs font-black text-slate-500">هزینه ماهانه تقریبی</p>
                <p class="mt-2 font-black text-slate-950">{{ $wallets->format($monthlyCost) }}</p>
                <p class="mt-1 text-xs text-slate-500">براساس وضعیت فعلی VM</p>
            </article>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <table class="min-w-full text-right text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr><th class="w-52 px-5 py-3 text-xs font-black text-slate-500">VMID</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->vmid ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">Proxmox</th><td class="px-5 py-3 font-semibold text-slate-800">{{ $server->proxmoxServer?->name ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">Node</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->node ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">Image</th><td class="px-5 py-3 font-semibold text-slate-800">{{ $server->cloudImage?->name ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">Bundle</th><td class="px-5 py-3 font-semibold text-slate-800">{{ $server->bundle?->name ?: 'Custom' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">منابع</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->cpu_cores }} CPU / {{ $server->ram_gb }}GB RAM / {{ $server->disk_gb }}GB Disk</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">Provisioning</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->provisioning_status ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">آخرین شروع</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->last_started_at?->format('Y/m/d H:i') ?: '-' }}</td></tr>
                    <tr><th class="px-5 py-3 text-xs font-black text-slate-500">آخرین توقف</th><td class="px-5 py-3 font-semibold text-slate-800" dir="ltr">{{ $server->last_stopped_at?->format('Y/m/d H:i') ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <form action="{{ route('customer.servers.destroy', $server, false) }}" method="POST" onsubmit="return confirm('این سرور حذف شود؟ IP آن به IP Pool بازگردانده می شود.');" class="rounded-xl border border-red-200 bg-red-50 p-4">
            @csrf
            @method('DELETE')
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-bold text-red-800">با حذف سرور، IP رزرو شده آن آزاد می شود و به IP Pool برمی گردد.</p>
                <button type="submit" class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-700 sm:w-auto">
                    حذف سرور
                </button>
            </div>
        </form>
    </section>
@endsection
