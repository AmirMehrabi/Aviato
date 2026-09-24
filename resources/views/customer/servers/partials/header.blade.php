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
@endphp
<div class="mx-auto mb-6 max-w-6xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60"
    x-data="{ pending: @js($actionPending), async poll() { if (!this.pending) return; try { const response = await fetch(@js(route('customer.servers.statuses', [], false)) + '?ids[]=' + encodeURIComponent(@js($server->uuid)), { headers: { Accept: 'application/json' } }); if (response.ok) { const data = await response.json(); const next = data.servers?.[0]; if (next && !next.action_pending && !next.provisioning_pending) { window.location.reload(); return; } } } catch (error) {} setTimeout(() => this.poll(), 5000); } }"
    x-init="poll()">
    <div class="px-5 pt-5 md:px-7 md:pt-6">
        <nav class="mb-5 flex items-center gap-2 text-xs font-bold text-slate-500" aria-label="مسیر صفحه">
            <a href="{{ route('customer.servers.index', [], false) }}" class="hover:text-[#0069FF]">سرورها</a>
            <svg class="size-3 rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m12.8 4.2-5.8 5.8 5.8 5.8-1.1 1.1-6.9-6.9 6.9-6.9z"/></svg>
            <span class="truncate text-slate-800" dir="ltr">{{ $server->display_name }}</span>
        </nav>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="min-w-0 break-all text-2xl font-black tracking-tight text-slate-950 md:text-3xl" dir="ltr">{{ $server->display_name }}</h1>
                    @if ($consoleReady)
                        <a href="{{ route('customer.servers.console.show', $server, false) }}" title="باز کردن کنسول" aria-label="باز کردن کنسول سرور {{ $server->display_name }}" class="inline-grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="15" rx="2"/><path d="m7 9 3 3-3 3m6 0h4M8 22h8"/></svg>
                        </a>
                    @else
                        <span title="کنسول در وضعیت فعلی در دسترس نیست" aria-label="کنسول در وضعیت فعلی در دسترس نیست" class="inline-grid size-10 shrink-0 place-items-center rounded-xl border border-slate-100 bg-slate-50 text-slate-300">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="15" rx="2"/><path d="m7 9 3 3-3 3m6 0h4M8 22h8"/></svg>
                        </span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
                    <span dir="ltr">{{ $server->hostname ?: $server->name }}</span>
                    <span dir="ltr">{{ $server->ip_address ?: 'IP در انتظار' }}</span>
                    <span>{{ $server->infrastructureLocation?->name ?: $server->proxmoxServer?->name ?: '—' }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-black">
                    <span class="rounded-full px-3 py-1.5 {{ $server->isRunning() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $statusLabel ?? ($server->isRunning() ? 'روشن' : 'خاموش') }}</span>
                    <span class="rounded-full px-3 py-1.5 {{ $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY ? 'bg-blue-50 text-[#0069FF]' : 'bg-amber-50 text-amber-800' }}">{{ $provisioningLabel ?? $server->provisioning_status }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($powerReady)
                    <form method="POST" action="{{ $server->isRunning() ? route('customer.servers.stop', $server, false) : route('customer.servers.start', $server, false) }}" x-data="{ confirming: false }" @submit.prevent="if (confirming) $el.submit(); else confirming = true">
                        @csrf
                        @if ($server->isRunning())<input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">@endif
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black text-white {{ $server->isRunning() ? 'bg-slate-800 hover:bg-slate-950' : 'bg-[#0069FF] hover:bg-[#0050D0]' }}">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 2v10M5.6 5.6a9 9 0 1 0 12.8 0"/></svg>
                            <span x-text="confirming ? 'برای تأیید دوباره بزنید' : @js($server->isRunning() ? 'خاموش کردن' : 'روشن کردن')">{{ $server->isRunning() ? 'خاموش کردن' : 'روشن کردن' }}</span>
                        </button>
                    </form>
                @endif
                <a href="{{ route('customer.monitoring.index', ['server' => $server->uuid], false) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 hover:bg-slate-50">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>مانیتورینگ
                </a>
            </div>
        </div>
    </div>
    <nav class="mt-5 overflow-x-auto border-t border-slate-100 px-5 md:px-7" aria-label="بخش‌های سرور">
        <div class="flex min-w-max items-center gap-1">
            @foreach ($tabItems as $item)
                <a href="{{ $item['href'] }}" @if($tab === $item['key']) aria-current="page" @endif class="whitespace-nowrap border-b-2 px-3 py-3.5 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0069FF] {{ $tab === $item['key'] ? 'border-[#0069FF] text-[#0069FF]' : 'border-transparent text-slate-500 hover:border-slate-200 hover:text-slate-900' }} {{ $item['key'] === 'delete' && $tab !== 'delete' ? 'hover:text-red-600' : '' }}">{{ $item['label'] }}</a>
            @endforeach
        </div>
    </nav>
</div>
