@php
    $tabItems = [
        ['key' => 'overview', 'label' => 'نمای کلی', 'href' => route('customer.servers.show', $server, false)],
        ['key' => 'resources', 'label' => 'منابع', 'href' => route('customer.servers.tab', [$server, 'tab' => 'resources'], false)],
        ['key' => 'billing', 'label' => 'صورتحساب', 'href' => route('customer.servers.tab', [$server, 'tab' => 'billing'], false)],
        ['key' => 'network', 'label' => 'شبکه', 'href' => route('customer.network.show', $server, false)],
        ['key' => 'upgrade', 'label' => 'ارتقا', 'href' => route('customer.servers.tab', [$server, 'tab' => 'upgrade'], false)],
        ['key' => 'rebuild', 'label' => 'بازسازی', 'href' => route('customer.servers.tab', [$server, 'tab' => 'rebuild'], false)],
        ['key' => 'delete', 'label' => 'حذف', 'href' => route('customer.servers.tab', [$server, 'tab' => 'delete'], false)],
        ['key' => 'activity', 'label' => 'فعالیت‌ها', 'href' => route('customer.servers.tab', [$server, 'tab' => 'activity'], false)],
    ];
    $consoleReady = $server->isProxmox() && ! $server->isLxc() && $server->proxmoxServer && $server->node && $server->vmid && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && ! $server->isLifecycleLocked();
    $providerReady = $server->isHetzner()
        ? ($server->infrastructureLocation?->hetznerAccount && $server->remote_id)
        : ($server->proxmoxServer && $server->node && $server->vmid);
    $powerReady = ($canManageServer ?? false) && ! $server->isLxc() && ! $server->isActionLocked() && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && $providerReady && ($server->isRunning() || ! $customer->isSuspended());
    $actionPending = $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING || ($server->isDeleting() && ! $server->delete_failed_at && ! $server->deleteAttemptIsStale());
    $pendingOperation = $server->operations()->whereNotNull('active_vm_id')->first();
    $latestPowerOperation = $server->operations()->where('action', '!=', 'upgrade')->latest('id')->first();
    $locationName = $server->infrastructureLocation?->name ?: $server->proxmoxServer?->name ?: '—';
@endphp
<div class="w-full min-w-0 bg-white"
    x-data="customerServerDetailStatus({
        url: @js(route('customer.servers.statuses', [], false)),
        id: @js($server->uuid),
        pending: @js($actionPending),
        status: @js($server->status),
        operation: @js($pendingOperation?->id),
        operationUpdatedAt: @js($pendingOperation?->updated_at?->toISOString()),
        passwordResetStatus: @js($server->password_reset_status),
        provisioningPending: @js($server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING),
    })">
    <div class="border-b border-slate-200 px-4 py-3 md:px-6 lg:px-8">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 text-[11px] font-black text-slate-400" aria-label="مسیر صفحه">
            <a href="{{ route('dashboard', [], false) }}" class="transition hover:text-[#0069FF]">کنسول ابری</a>
            <svg class="size-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            <a href="{{ route('customer.servers.index', [], false) }}" class="transition hover:text-[#0069FF]">سرورها</a>
            <svg class="size-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            <span class="max-w-full truncate text-slate-700 underline decoration-slate-200 underline-offset-4" dir="ltr">{{ $server->display_name }}</span>
        </nav>
    </div>

    <header class="border-b border-slate-200 px-4 pt-8 md:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 pb-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 space-y-4">
                <div class="flex min-w-0 items-center gap-3">
                    @include('customer.servers.partials.display-name')
                    @if ($consoleReady)
                        <a href="{{ route('customer.servers.console.show', $server, false) }}" title="باز کردن کنسول" aria-label="باز کردن کنسول سرور {{ $server->display_name }}" class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 17 6-6-6-6M12 19h8"/></svg>
                        </a>
                    @else
                        <span title="کنسول در وضعیت فعلی در دسترس نیست" aria-label="کنسول در وضعیت فعلی در دسترس نیست" class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-300">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 17 6-6-6-6M12 19h8"/></svg>
                        </span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm font-bold text-slate-500">
                    <span class="inline-flex items-center gap-1" dir="ltr">
                        <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                        {{ $server->hostname ?: $server->name }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        {{ $locationName }}
                    </span>
                    <div class="flex flex-wrap items-center gap-2 border-r border-slate-200 pr-4 text-xs font-black">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ $server->isRunning() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}"><span class="size-1.5 rounded-full {{ $server->isRunning() ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $statusLabel ?? ($server->isRunning() ? 'روشن' : 'خاموش') }}</span>
                        <span class="rounded-full px-2.5 py-1 {{ $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY ? 'bg-blue-50 text-[#0069FF]' : 'bg-amber-50 text-amber-800' }}">{{ $provisioningLabel ?? $server->provisioning_status }}</span>
                        @if (data_get($server->wallet_restriction, 'wallet_stopped'))
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700">خاموش به‌دلیل بدهی کیف پول</span>
                        @elseif (data_get($server->wallet_restriction, 'network_frozen_at'))
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">شبکه قطع به‌دلیل کیف پول</span>
                        @endif
                    </div>
                </div>
            </div>
            @if ($powerReady)
                @include('customer.servers.partials.power-actions')
            @endif
        </div>
        <nav class="mx-auto max-w-7xl overflow-x-auto" aria-label="بخش‌های سرور">
            <div class="flex min-w-max items-center gap-8">
                @foreach ($tabItems as $item)
                    <a href="{{ $item['href'] }}" @if($tab === $item['key']) aria-current="page" @endif class="whitespace-nowrap border-b-2 py-4 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF] {{ $tab === $item['key'] ? 'border-[#0069FF] font-black text-[#0069FF]' : 'border-transparent font-bold text-slate-500 hover:text-slate-800' }} {{ $item['key'] === 'delete' && $tab !== 'delete' ? 'hover:text-red-600' : '' }}">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </nav>
    </header>
</div>

@include('customer.servers.partials.operation-banner')
