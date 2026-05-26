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
        'deleting' => 'در حال حذف',
        'deleted' => 'حذف شده',
        default => $server->status,
    };
    $statusClass = match ($server->status) {
        'running' => 'bg-emerald-50 text-emerald-700',
        'suspended' => 'bg-red-50 text-red-600',
        'deleting' => 'bg-amber-50 text-amber-700',
        'deleted' => 'bg-slate-100 text-slate-500',
        default => 'bg-slate-100 text-slate-600',
    };
    $monthlyCost = $server->isActionLocked()
        ? 0
        : ($server->isRunning()
        ? $billing->estimateMonthly($server)
        : $billing->estimateStoppedMonthly($server));
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
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                بازگشت به فهرست
            </a>
            <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black {{ $statusClass }}">
                @if ($server->isDeleting())<span class="size-3 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-600"></span>@endif
                {{ $statusLabel }}
            </span>
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
                <p class="mt-2 font-black text-slate-950">{{ $server->isActionLocked() ? 'قفل حذف' : $wallets->format($monthlyCost) }}</p>
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

        @if ($server->isDeleting())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-amber-900">حذف این سرور در حال انجام است.</p>
                        <p class="mt-1 text-xs font-bold text-amber-800">تا پایان خاموش سازی و حذف از Proxmox، هیچ عملیات دیگری روی این سرور فعال نیست.</p>
                        @if ($server->delete_failed_at && $server->delete_error)
                            <p class="mt-2 text-xs font-bold text-red-700">آخرین خطا: {{ $server->delete_error }}</p>
                        @endif
                    </div>
                    <span class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-100 px-4 py-2.5 text-sm font-black text-amber-800 sm:w-auto">
                        <span class="size-4 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-700"></span>
                        در حال حذف
                    </span>
                </div>
            </div>
        @else
            <form action="{{ route('customer.servers.destroy', $server, false) }}" method="POST" x-data="{ submitting: false }" x-on:submit="submitting = true" class="rounded-xl border border-red-200 bg-red-50 p-4">
                @csrf
                @method('DELETE')
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-red-800">با حذف سرور، VM ابتدا خاموش و سپس از Proxmox حذف می شود.</p>
                        <p class="mt-1 text-xs font-bold text-red-700">پس از حذف موفق، IP رزرو شده آزاد می شود. بکاپ ها جداگانه نگهداری می شوند.</p>
                    </div>
                    <button type="submit" x-bind:disabled="submitting" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-700 disabled:cursor-wait disabled:opacity-70 sm:w-auto">
                        <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        <span x-text="submitting ? 'در حال ثبت...' : 'حذف سرور'">حذف سرور</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
@endsection
