@php
    $canManageServer = $canManageServer ?? false;
    $headingClass = 'min-w-0 wrap-anywhere text-2xl font-black tracking-tight text-slate-950 sm:text-3xl';
    $actionClass = 'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm font-bold text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] disabled:pointer-events-none disabled:opacity-50 sm:w-auto sm:px-4';
    $displayNameInput = old('display_name', $server->display_name);
    $displayNameInput = is_string($displayNameInput) ? $displayNameInput : ($displayNameInput === null ? '' : $server->display_name);
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
    $powerReady = $canManageServer && ! $server->isLxc() && ! $server->isActionLocked() && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && $providerReady && ($server->isRunning() || ! $customer->isSuspended());
    $showRunningActions = $powerReady && $server->isRunning();
    $showPowerRadios = $powerReady && in_array($server->status, [\App\Models\VirtualMachine::STATUS_RUNNING, \App\Models\VirtualMachine::STATUS_STOPPED], true);
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
        <nav class="mx-auto flex max-w-7xl items-center gap-2 text-[11px] font-black text-slate-400" aria-label="مسیر صفحه">
            <a href="{{ route('dashboard', [], false) }}" class="shrink-0 transition hover:text-[#0069FF]">کنسول ابری</a>
            <svg class="size-3.5 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            <a href="{{ route('customer.servers.index', [], false) }}" class="shrink-0 transition hover:text-[#0069FF]">سرورها</a>
            <svg class="size-3.5 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            <span class="min-w-0 truncate text-slate-700 underline decoration-slate-200 underline-offset-4" dir="ltr">{{ $server->display_name }}</span>
        </nav>
    </div>

    <header class="border-b border-slate-200 px-4 pt-5 sm:pt-8 md:px-6 lg:px-8"
        @if ($canManageServer)
            x-data="{
                editing: @js($errors->renameVm->has('display_name')),
                saving: false,
                menuOpen: false,
                name: @js($displayNameInput),
                start() { this.editing = true; this.$nextTick(() => { this.$refs.name.focus(); this.$refs.name.select(); }); },
                cancel() { if (this.saving) return; this.editing = false; this.name = @js($server->display_name); this.$nextTick(() => this.$refs.more.focus()); }
            }" x-init="if (editing) $nextTick(() => $refs.name.focus())"
        @endif
    >
        <div class="mx-auto max-w-7xl space-y-4 pb-4 sm:space-y-5 sm:pb-5">
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    @include('customer.servers.partials.display-name')
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm font-bold text-slate-500">
                        <span class="inline-flex min-w-0 max-w-full items-center gap-1" dir="ltr">
                            <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                            <bdi class="wrap-anywhere">{{ $server->hostname ?: $server->name }}</bdi>
                        </span>
                        <span class="inline-flex min-w-0 max-w-full items-center gap-1">
                            <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span class="wrap-anywhere">{{ $locationName }}</span>
                        </span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-2 sm:max-w-[50%] sm:shrink-0 sm:justify-end">
                    @unless ($showPowerRadios)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-black {{ $server->isRunning() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}"><span class="size-1.5 rounded-full {{ $server->isRunning() ? 'bg-emerald-500' : 'bg-slate-400' }}" aria-hidden="true"></span>{{ $statusLabel ?? ($server->isRunning() ? 'روشن' : 'خاموش') }}</span>
                    @endunless
                    @if ($powerReady)
                        @include('customer.servers.partials.power-status')
                    @endif
                    <div class="flex flex-wrap items-center gap-2 text-xs font-black">
                        <span class="rounded-full px-2.5 py-1 {{ $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY ? 'bg-blue-50 text-[#0069FF]' : 'bg-amber-50 text-amber-800' }}">{{ $provisioningLabel ?? $server->provisioning_status }}</span>
                        @if (data_get($server->wallet_restriction, 'wallet_stopped'))
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700">خاموش به‌دلیل بدهی کیف پول</span>
                        @elseif (data_get($server->wallet_restriction, 'network_frozen_at'))
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">شبکه قطع به‌دلیل کیف پول</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 [&>*:only-child]:col-span-2 sm:flex sm:flex-wrap sm:justify-end">
                @if ($showRunningActions)
                    @include('customer.servers.partials.power-actions')
                @endif
                @php
                    $consoleIcon = '<svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 17 6-6-6-6M12 19h8"/></svg>';
                @endphp
                @if ($consoleReady)
                    <a href="{{ route('customer.servers.console.show', $server, false) }}" aria-label="باز کردن کنسول سرور {{ $server->display_name }}" class="{{ $actionClass }}">
                        {!! $consoleIcon !!}
                        کنسول
                    </a>
                @else
                    <button type="button" disabled aria-label="کنسول در وضعیت فعلی در دسترس نیست" class="{{ $actionClass }}">
                        {!! $consoleIcon !!}
                        کنسول
                    </button>
                @endif
                @if ($canManageServer)
                    @include('customer.servers.partials.more-actions')
                @endif
            </div>
        </div>
        <nav class="mx-auto max-w-7xl overflow-x-auto" aria-label="بخش‌های سرور">
            <div class="flex min-w-max items-center gap-5 sm:gap-8">
                @foreach ($tabItems as $item)
                    <a href="{{ $item['href'] }}" @if($tab === $item['key']) aria-current="page" @endif class="whitespace-nowrap border-b-2 py-4 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF] {{ $tab === $item['key'] ? 'border-[#0069FF] font-black text-[#0069FF]' : 'border-transparent font-bold text-slate-500 hover:text-slate-800' }} {{ $item['key'] === 'delete' && $tab !== 'delete' ? 'hover:text-red-600' : '' }}">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </nav>
    </header>
</div>

@include('customer.servers.partials.operation-banner')
