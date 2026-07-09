@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'ماشین‌های مجازی')

@section('content')
@php
    $targetNodeOptions = collect($proxmoxServers ?? [])
        ->flatMap(function ($server) {
            return collect(data_get($server->remote_inventory, 'nodes', []))
                ->map(fn (array $node): ?string => $node['node'] ?? $node['name'] ?? null)
                ->filter()
                ->mapWithKeys(fn (string $node): array => [$node => $node.' - '.$server->name]);
        })
        ->sortKeys();
@endphp
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vmFilters', () => ({
        timer: null,

        fetchResults() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this._doFetch(true), 450);
        },

        fetchNow() {
            clearTimeout(this.timer);
            this._doFetch(true);
        },

        _doFetch(pushState) {
            const params = new URLSearchParams(new FormData(this.$refs.filters));
            const url = this.$refs.filters.action + '?' + params.toString();
            if (pushState) history.pushState({}, '', url);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => this._applyHtml(html));
        },

        _applyHtml(html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector('[x-ref="results"]');
            if (next) this.$refs.results.innerHTML = next.innerHTML;
            const input = doc.querySelector('input[name="search"]');
            if (input && this.$refs.filters.querySelector('input[name="search"]')) {
                this.$refs.filters.querySelector('input[name="search"]').value = input.value;
            }
            this.$refs.filters.querySelectorAll('select').forEach(sel => {
                const fresh = doc.querySelector('select[name="' + sel.name + '"]');
                if (fresh) sel.value = fresh.value;
            });
        },

        init() {
            window.addEventListener('popstate', () => {
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => this._applyHtml(html));
            });
        }
    }));
});
</script>

<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="vmFilters"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">ماشین‌های مجازی</h1>
            <p class="mt-2 text-sm text-slate-500">اتصال VM به مشتری، Proxmox و محاسبه PAYG.</p>
        </div>
        <a href="{{ route('admin.virtual-machines.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">VM جدید</a>
    </div>

    <form x-ref="filters" @submit.prevent method="GET" action="{{ route('admin.virtual-machines.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" @input="fetchResults()" placeholder="جستجو نام یا IP" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select name="customer_id" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه مشتریان</option>
                @foreach($customers as $id => $name)
                    <option value="{{ $id }}" @selected((string)($filters['customer_id'] ?? '') === (string)$id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="project_id" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه پروژه ها</option>
                @foreach($projects as $id => $name)
                    <option value="{{ $id }}" @selected((string)($filters['project_id'] ?? '') === (string)$id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="status" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه وضعیت‌ها</option>
                <option value="running" @selected(($filters['status'] ?? '') === 'running')>روشن</option>
                <option value="stopped" @selected(($filters['status'] ?? '') === 'stopped')>خاموش</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>تعلیق</option>
            </select>
            <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</a>
        </div>
    </form>

    <section x-ref="results" class="mt-6">
        <form method="POST" action="{{ route('admin.virtual-machines.move-node') }}">
            @csrf
            <div class="mb-3 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-center">
                <span class="text-sm font-black text-slate-700">انتقال Node برای VMهای انتخاب‌شده</span>
                <select name="target_node" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                    <option value="">انتخاب مقصد</option>
                    @foreach($targetNodeOptions as $node => $label)
                        <option value="{{ $node }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select name="mode" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                    <option value="reconcile_only">فقط تطبیق دیتابیس اگر VM روی مقصد وجود دارد</option>
                    <option value="migrate">Migration در Proxmox و سپس بروزرسانی دیتابیس</option>
                </select>
                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700">
                    <input type="checkbox" name="online" value="1" class="rounded border-slate-300 text-[#0069FF]">
                    Online migration
                </label>
                <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">اجرای انتقال</button>
            </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="px-5 py-4"></th>
                            <th class="px-5 py-4">VM</th>
                            <th class="px-5 py-4">فضای کاری</th>
                            <th class="px-5 py-4">مالک فضای کاری</th>
                            <th class="px-5 py-4">ایجادکننده</th>
                            <th class="px-5 py-4">مشتری صورتحساب</th>
                            <th class="px-5 py-4">منابع</th>
                            <th class="px-5 py-4">وضعیت</th>
                            <th class="px-5 py-4">هزینه ماهانه فعلی</th>
                            <th class="px-5 py-4">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($vms as $vm)
                            <tr>
                                <td class="px-5 py-4">
                                    <input
                                        type="checkbox"
                                        name="vm_ids[]"
                                        value="{{ $vm->id }}"
                                        class="rounded border-slate-300 text-[#0069FF]"
                                        @disabled(! $vm->isProxmox() || $vm->isActionLocked() || ! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid)
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="font-black text-slate-950" dir="ltr">{{ $vm->display_name }}</a>
                                    <span class="block text-xs text-slate-500" dir="ltr">{{ $vm->name }} · {{ $vm->ip_address ?: 'no-ip' }} · {{ $vm->proxmoxServer?->name ?: 'local' }} · {{ $vm->node ?: 'no-node' }}</span>
                                </td>
                                <td class="px-5 py-4 font-bold">{{ $vm->project?->name ?: '—' }}</td>
                                <td class="px-5 py-4">{{ $vm->project?->owner?->name ?: '—' }}</td>
                                <td class="px-5 py-4">{{ $vm->creator?->name ?: '—' }}</td>
                                <td class="px-5 py-4"><a class="font-bold text-[#0069FF]" href="{{ route('admin.customers.show', $vm->customer) }}">{{ $vm->customer?->name ?: '—' }}</a></td>
                                <td class="px-5 py-4">{{ $vm->cpu_cores }} CPU / {{ $vm->ram_gb }}GB / {{ $vm->disk_gb }}GB</td>
                                <td class="px-5 py-4">
    @if($vm->status === 'running')
        <span class="rounded-md bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">روشن</span>
    @elseif($vm->status === 'stopped')
        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">خاموش</span>
    @elseif($vm->status === 'suspended')
        <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-black text-red-600">تعلیق</span>
    @elseif($vm->status === 'deleting')
        <span class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">در حال حذف</span>
    @elseif($vm->status === 'deleted')
        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-500">حذف شده</span>
    @else
        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">{{ \App\Support\AdminUi::status($vm->status) }}</span>
    @endif
</td>
                                <td class="px-5 py-4 font-black">{{ $money->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }}</td>
                                <td class="px-5 py-4">
                                    <a class="font-black text-[#0069FF]" href="{{ route('admin.virtual-machines.show', $vm) }}">نمایش</a>
                                    <span class="mx-1 text-slate-300">·</span>
                                    <a class="font-black text-purple-600" href="{{ route('admin.virtual-machines.transfer.show', $vm) }}">انتقال</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-5 py-10 text-center text-slate-500">VM ثبت نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $vms->links() }}</div>
        </div>
        </form>
    </section>
</div>
@endsection
