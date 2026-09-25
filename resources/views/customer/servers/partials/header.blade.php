@php
    $tabItems = [
        ['key' => 'overview', 'label' => 'نمای کلی', 'href' => route('customer.servers.show', $server, false)],
        ['key' => 'resources', 'label' => 'منابع', 'href' => route('customer.servers.tab', [$server, 'tab' => 'resources'], false)],
        ['key' => 'billing', 'label' => 'صورتحساب', 'href' => route('customer.servers.tab', [$server, 'tab' => 'billing'], false)],
        ['key' => 'network', 'label' => 'شبکه', 'href' => route('customer.network.show', $server, false)],
        ['key' => 'upgrade', 'label' => 'ارتقا', 'href' => route('customer.servers.tab', [$server, 'tab' => 'upgrade'], false)],
        ['key' => 'rebuild', 'label' => 'بازسازی', 'href' => route('customer.servers.tab', [$server, 'tab' => 'rebuild'], false)],
        ['key' => 'delete', 'label' => 'حذف', 'href' => route('customer.servers.tab', [$server, 'tab' => 'delete'], false)],
        ['key' => 'activity', 'label' => 'فعالیت', 'href' => route('customer.servers.tab', [$server, 'tab' => 'activity'], false)],
    ];
    $consoleReady = $server->isProxmox() && ! $server->isLxc() && $server->proxmoxServer && $server->node && $server->vmid && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && ! $server->isActionLocked();
    $providerReady = $server->isHetzner()
        ? ($server->infrastructureLocation?->hetznerAccount && $server->remote_id)
        : ($server->proxmoxServer && $server->node && $server->vmid);
    $powerReady = ($canManageServer ?? false) && ! $server->isLxc() && ! $server->isActionLocked() && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && $providerReady && ($server->isRunning() || ! $customer->isSuspended());
    $actionPending = $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING || ($server->isDeleting() && ! $server->delete_failed_at && ! $server->deleteAttemptIsStale());
    $locationName = $server->infrastructureLocation?->name ?: $server->proxmoxServer?->name ?: '—';
@endphp
<div class="w-full min-w-0 bg-white"
    x-data="{ pending: @js($actionPending), async poll() { if (!this.pending) return; try { const response = await fetch(@js(route('customer.servers.statuses', [], false)) + '?ids[]=' + encodeURIComponent(@js($server->uuid)), { headers: { Accept: 'application/json' } }); if (response.ok) { const data = await response.json(); const next = data.servers?.[0]; if (next && !next.action_pending && !next.provisioning_pending) { window.location.reload(); return; } } } catch (error) {} setTimeout(() => this.poll(), 5000); } }"
    x-init="poll()">
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
                    <h1 class="min-w-0 break-all text-3xl font-black tracking-tight text-slate-950" dir="ltr">{{ $server->display_name }}</h1>
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
                    </div>
                </div>
            </div>
            @if ($powerReady)
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ $server->isRunning() ? route('customer.servers.stop', $server, false) : route('customer.servers.start', $server, false) }}" x-data="{ confirming: false }" @submit.prevent="if (confirming) $el.submit(); else confirming = true">
                        @csrf
                        @if ($server->isRunning())<input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">@endif
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-black transition {{ $server->isRunning() ? 'border border-red-200 bg-red-50 text-red-600 hover:bg-red-100' : 'bg-[#0069FF] text-white hover:bg-[#0050D0]' }}">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 2v10M5.6 5.6a9 9 0 1 0 12.8 0"/></svg>
                            <span x-text="confirming ? 'برای تأیید دوباره بزنید' : @js($server->isRunning() ? 'خاموش کردن' : 'روشن کردن')">{{ $server->isRunning() ? 'خاموش کردن' : 'روشن کردن' }}</span>
                        </button>
                    </form>
                    @if ($server->isRunning())
                        <form method="POST" action="{{ route('customer.servers.restart', $server, false) }}" x-data="{ confirming: false }" @submit.prevent="if (confirming) $el.submit(); else confirming = true">
                            @csrf
                            <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 6.3M20 4v7h-7"/></svg>
                                <span x-text="confirming ? 'برای تأیید دوباره بزنید' : 'ری‌استارت'">ری‌استارت</span>
                            </button>
                        </form>
                    @endif
                </div>
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
