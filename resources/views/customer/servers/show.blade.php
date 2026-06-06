@extends('customer.layout')

@section('title', 'جزئیات سرور')
@section('header_title', $server->name)
@section('header_subtitle', 'اتصال، وضعیت، منابع و هزینه این VPS')
@section('breadcrumbs')
    <a href="{{ route('customer.servers.index', [], false) }}" class="transition hover:text-[#0069FF]">سرورها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
    <span class="truncate text-slate-700" dir="ltr">{{ $server->name }}</span>
@endsection

@php
    $activeNav = 'servers';
    $hasIp = filled($server->ip_address);
    $hasPassword = filled($server->login_password);
    $hasSshKey = filled($server->ssh_public_key);
    $isLocked = $server->isActionLocked();
    $deleteAttemptIsStale = $server->deleteAttemptIsStale();
    $monitoringUrl = route('customer.monitoring.index', ['server' => $server->uuid], false);
    $backupUrl = route('customer.backups.index', [], false);
    $consoleReady = $server->proxmoxServer && $server->node && $server->vmid && $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && ! $isLocked;
    $consoleUrl = route('customer.servers.console.show', $server, false);
    $statusUrl = route('customer.servers.statuses', [], false);
    $isRebuilding = $server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING && filled(data_get($server->remote_state, 'rebuild_started_at'));
    $rebuildError = data_get($server->remote_state, 'rebuild_error');
    $formattedRebuildFee = $rebuildFee > 0 ? $wallets->format($rebuildFee) : 'بدون هزینه';
    $canRebuild = ! $isLocked
        && $server->provisioning_status !== \App\Models\VirtualMachine::PROVISION_PENDING
        && ! $hasPendingUpgrade
        && $server->proxmoxServer
        && $server->cloudImage
        && $server->cloudImage->is_active
        && $server->node
        && $server->vmid
        && $server->template_vmid;
    $formattedMonthlyCost = $server->isDeleting() ? 'متوقف شده' : $wallets->format($monthlyCost);
    $backupFrequency = match ($backupSummary['frequency']) {
        'daily' => 'روزانه',
        'weekly' => 'هفتگی',
        default => 'تنظیم نشده',
    };
    $latestBackupStatus = match ($backupSummary['latest_status']) {
        'queued' => 'در صف',
        'running' => 'در حال اجرا',
        'ready' => 'آماده',
        'failed' => 'ناموفق',
        'deleted' => 'حذف شده',
        default => 'بدون بکاپ',
    };
@endphp

@section('search_data')
[
    {
        "title": "بازگشت به فهرست سرورها",
        "description": "مشاهده همه ماشین های این حساب",
        "type": "صفحه",
        "url": @json(route('customer.servers.index', [], false)),
        "keywords": "servers list vps"
    },
    {
        "title": "مانیتورینگ {{ $server->name }}",
        "description": "نمودار مصرف CPU، RAM و شبکه",
        "type": "صفحه",
        "url": @json($monitoringUrl),
        "keywords": @json('monitoring metrics '.$server->name.' '.$server->ip_address)
    }
]
@endsection

@section('content')
    <section
        x-data="{
            copied: null,
            revealPassword: false,
            statusUrl: @js($statusUrl),
            serverId: @js($server->uuid),
            serverState: {
                status_label: @js($statusLabel),
                status_class: @js($statusClass),
                provisioning_label: @js($provisioningLabel),
                provisioning_class: @js($provisioningClass),
                provisioning_pending: @js($server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING),
                action_pending: @js($server->provisioning_status === \App\Models\VirtualMachine::PROVISION_PENDING || ($server->isDeleting() && $server->delete_failed_at === null && ! $deleteAttemptIsStale)),
                is_rebuilding: @js($isRebuilding),
                hostname: @js($server->hostname ?: 'hostname-not-set'),
                vmid: @js($server->vmid ?: '-'),
                node: @js($server->node ?: 'node-not-set'),
                ip: @js($server->ip_address ?: 'Pending'),
            },
            copy(value, key) {
                if (!value) return;
                navigator.clipboard?.writeText(value);
                this.copied = key;
                window.setTimeout(() => this.copied = null, 1800);
            },
            pollStatus() {
                if (!this.serverState.action_pending && !this.serverState.provisioning_pending) return;
                window.setTimeout(() => this.fetchStatus(), 3000);
            },
            async fetchStatus() {
                try {
                    const response = await fetch(`${this.statusUrl}?ids[]=${encodeURIComponent(this.serverId)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) return this.pollStatus();
                    const payload = await response.json();
                    const next = payload.servers?.[0];
                    if (!next) return;
                    const wasPending = this.serverState.provisioning_pending;
                    this.serverState = { ...this.serverState, ...next };
                    if (wasPending && !next.provisioning_pending) {
                        window.setTimeout(() => window.location.reload(), 600);
                        return;
                    }
                } finally {
                    this.pollStatus();
                }
            },
        }"
        x-init="pollStatus()"
        class="space-y-5"
    >
        <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="relative overflow-hidden bg-[#031B4E] p-6 text-white">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(0,105,255,.5),transparent_30%),radial-gradient(circle_at_86%_12%,rgba(0,166,126,.25),transparent_25%)]"></div>
                <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px] xl:items-end">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-xs font-black" :class="serverState.status_class">
                                @if ($server->isDeleting())<span class="size-3 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-600"></span>@endif
                                <span x-text="serverState.status_label">{{ $statusLabel }}</span>
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-xs font-black" :class="serverState.provisioning_class">
                                <span x-show="serverState.provisioning_pending" class="size-3 animate-spin rounded-full border-2 border-[#0069FF]/30 border-t-[#0069FF]"></span>
                                <span x-text="serverState.provisioning_label">{{ $provisioningLabel }}</span>
                            </span>
                        </div>
                        <h2 class="mt-5 truncate text-4xl font-black leading-tight" dir="ltr">{{ $server->name }}</h2>
                        <p class="mt-2 truncate text-sm font-bold text-[#9DB4DC]" dir="ltr" x-text="`${serverState.hostname} · VMID ${serverState.vmid || '-'} · ${serverState.node}`">{{ $server->hostname ?: 'hostname-not-set' }} · VMID {{ $server->vmid ?: '-' }} · {{ $server->node ?: 'node-not-set' }}</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-black text-[#9DB4DC]">IP Address</p>
                                <p class="mt-2 truncate text-xl font-black" dir="ltr" x-text="serverState.ip">{{ $server->ip_address ?: 'Pending' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-black text-[#9DB4DC]">Resources</p>
                                <p class="mt-2 truncate text-xl font-black" dir="ltr">{{ $server->cpu_cores }} CPU / {{ $server->ram_gb }}GB</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-black text-[#9DB4DC]">Monthly</p>
                                <p class="mt-2 truncate text-xl font-black">{{ $formattedMonthlyCost }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white p-5 text-slate-950 shadow-2xl shadow-slate-950/20">
                        <p class="text-xs font-black text-slate-500">دستور اتصال سریع</p>
                        @if ($sshCommand)
                            <div class="mt-3 flex items-center gap-2 rounded-2xl bg-slate-950 p-3 text-left text-white" dir="ltr">
                                <code class="min-w-0 flex-1 truncate text-sm font-black">{{ $sshCommand }}</code>
                                <button type="button" @click="copy(@js($sshCommand), 'ssh')" class="shrink-0 rounded-xl bg-white/10 px-3 py-2 text-xs font-black text-white transition hover:bg-white/20">
                                    <span x-show="copied !== 'ssh'">Copy</span>
                                    <span x-show="copied === 'ssh'">Copied</span>
                                </button>
                            </div>
                            <p class="mt-3 text-xs font-bold leading-6 text-slate-500">اگر اتصال برقرار نشد، آماده بودن Provisioning و باز بودن SSH در سیستم عامل را بررسی کنید.</p>
                        @else
                            <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="font-black text-amber-900">IP هنوز آماده نیست.</p>
                                <p class="mt-2 text-xs font-bold leading-6 text-amber-800">بعد از پایان آماده سازی، دستور SSH اینجا نمایش داده می شود.</p>
                            </div>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($consoleReady)
                                <a href="{{ $consoleUrl }}" class="inline-flex flex-1 justify-center rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0050D0]">Console</a>
                            @else
                                <span class="inline-flex flex-1 cursor-not-allowed justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-black text-slate-400">Console</span>
                            @endif
                            <a href="{{ $monitoringUrl }}" class="inline-flex flex-1 justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">مانیتورینگ</a>
                            <a href="{{ $backupUrl }}" class="inline-flex flex-1 justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">بکاپ ها</a>
                            <a href="{{ route('customer.servers.index', [], false) }}" class="inline-flex flex-1 justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">سرورها</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,.9fr)]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black text-[#0069FF]">Access</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">اطلاعات اتصال و SSH</h2>
                    </div>
                    <span class="rounded-xl px-3 py-1.5 text-xs font-black {{ $hasIp ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $hasIp ? 'قابل اتصال' : 'در انتظار IP' }}</span>
                </div>

                <div class="mt-5 grid gap-3">
                    <div class="flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-black text-slate-500">IP Address</p>
                            <p class="mt-1 truncate text-lg font-black text-slate-950" dir="ltr">{{ $server->ip_address ?: 'بدون IP' }}</p>
                        </div>
                        <button type="button" @click="copy(@js($server->ip_address), 'ip')" @disabled(! $hasIp) class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="copied !== 'ip'">کپی IP</span>
                            <span x-show="copied === 'ip'">کپی شد</span>
                        </button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="text-xs font-black text-slate-500">Username</p>
                            <div class="mt-2 flex items-center gap-2">
                                <code class="min-w-0 flex-1 truncate text-left text-base font-black text-slate-950" dir="ltr">{{ $server->login_username ?: '-' }}</code>
                                <button type="button" @click="copy(@js($server->login_username), 'user')" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-black text-slate-600 hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                                    <span x-show="copied !== 'user'">Copy</span>
                                    <span x-show="copied === 'user'">Copied</span>
                                </button>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="text-xs font-black text-slate-500">Password</p>
                            @if ($hasPassword)
                                <div class="mt-2 flex items-center gap-2">
                                    <code class="min-w-0 flex-1 truncate text-left text-base font-black text-slate-950" dir="ltr" x-text="revealPassword ? @js($server->login_password) : '••••••••••••'"></code>
                                    <button type="button" @click="revealPassword = !revealPassword" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-black text-slate-600 hover:bg-[#EBF3FF] hover:text-[#0069FF]" x-text="revealPassword ? 'Hide' : 'Show'"></button>
                                    <button type="button" @click="copy(@js($server->login_password), 'password')" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-black text-slate-600 hover:bg-[#EBF3FF] hover:text-[#0069FF]">
                                        <span x-show="copied !== 'password'">Copy</span>
                                        <span x-show="copied === 'password'">Copied</span>
                                    </button>
                                </div>
                            @else
                                <p class="mt-2 text-sm font-bold text-slate-500">رمز عبور ذخیره نشده؛ احتمالا اتصال با SSH Key انجام می شود.</p>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-black text-slate-500">SSH Public Key</p>
                                @if ($hasSshKey)
                                    <p class="mt-2 truncate text-sm font-bold text-slate-700" dir="ltr">{{ $server->ssh_public_key }}</p>
                                @else
                                    <p class="mt-2 text-sm font-bold text-slate-500">کلید SSH برای این ماشین ثبت نشده است.</p>
                                @endif
                            </div>
                            <button type="button" @click="copy(@js($server->ssh_public_key), 'key')" @disabled(! $hasSshKey) class="inline-flex shrink-0 justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="copied !== 'key'">کپی کلید</span>
                                <span x-show="copied === 'key'">کپی شد</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-1">
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="font-black text-slate-950">هزینه و منابع</h2>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <p class="text-[11px] font-black text-slate-400">CPU</p>
                            <p class="mt-1 text-lg font-black text-slate-950" dir="ltr">{{ $server->cpu_cores }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <p class="text-[11px] font-black text-slate-400">RAM</p>
                            <p class="mt-1 text-lg font-black text-slate-950" dir="ltr">{{ $server->ram_gb }}GB</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <p class="text-[11px] font-black text-slate-400">Disk</p>
                            <p class="mt-1 text-lg font-black text-slate-950" dir="ltr">{{ $server->disk_gb }}GB</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">پلن</span><span class="font-black text-slate-950">{{ $server->bundle?->name ?: 'Custom' }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">هزینه ماهانه</span><span class="font-black text-slate-950">{{ $formattedMonthlyCost }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">حالت محاسبه</span><span class="font-black text-slate-950">{{ $server->isRunning() ? 'مصرف کامل منابع' : 'دیسک و IP پایدار' }}</span></div>
                    </div>
                </article>

                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black text-[#0069FF]">Upgrade</p>
                            <h2 class="mt-1 font-black text-slate-950">ارتقای منابع</h2>
                        </div>
                        @if ($hasPendingUpgrade)
                            <span class="rounded-xl bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-700">در حال انجام</span>
                        @endif
                    </div>
                    <p class="mt-3 text-xs font-bold leading-6 text-slate-500">قبل از ارتقا، مصرف قبلی تسویه می شود و از بعد از اعمال موفق، هزینه ساعتی جدید محاسبه می شود.</p>

                    <form method="POST" action="{{ route('customer.servers.upgrades.bundle.store', $server, false) }}" class="mt-4 space-y-3" x-data="{ submitting: false }" x-on:submit="submitting = true">
                        @csrf
                        <label class="block text-xs font-black text-slate-500" for="vm_bundle_id">باندل جدید</label>
                        <select id="vm_bundle_id" name="vm_bundle_id" @disabled($isLocked || $hasPendingUpgrade || $eligibleBundles->isEmpty()) class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-800 focus:border-[#0069FF] focus:bg-white focus:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            @forelse ($eligibleBundles as $bundle)
                                @php($preview = $bundlePreviews[$bundle->id])
                                <option value="{{ $bundle->id }}">
                                    {{ $bundle->name }} - {{ $bundle->cpu_cores }} CPU / {{ $bundle->ram_gb }}GB RAM / {{ $bundle->disk_gb }}GB Disk - +{{ $wallets->format($preview['monthly_delta']) }}/ماه
                                </option>
                            @empty
                                <option value="">باندل بزرگتری موجود نیست</option>
                            @endforelse
                        </select>
                        <button type="submit" x-bind:disabled="submitting || {{ ($isLocked || $hasPendingUpgrade || $eligibleBundles->isEmpty()) ? 'true' : 'false' }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            <span x-text="submitting ? 'در حال ثبت...' : 'ثبت ارتقای باندل'">ثبت ارتقای باندل</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('customer.servers.upgrades.extra-disk.store', $server, false) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4" x-data="{ submitting: false }" x-on:submit="submitting = true">
                        @csrf
                        <label class="block text-xs font-black text-slate-500" for="size_gb">دیسک اضافه</label>
                        <select id="size_gb" name="size_gb" @disabled($isLocked || $hasPendingUpgrade) class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-800 focus:border-[#0069FF] focus:bg-white focus:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            @foreach ($extraDiskOptions as $option)
                                <option value="{{ $option['size_gb'] }}">{{ $option['size_gb'] }}GB - +{{ $wallets->format($option['monthly_delta']) }}/ماه</option>
                            @endforeach
                        </select>
                        <p class="text-xs font-bold leading-6 text-slate-500">دیسک اضافه در Proxmox attach می شود؛ داخل سیستم عامل باید پارتیشن بندی و mount شود.</p>
                        <button type="submit" x-bind:disabled="submitting || {{ ($isLocked || $hasPendingUpgrade) ? 'true' : 'false' }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-[#0069FF]/30 border-t-[#0069FF]"></span>
                            <span x-text="submitting ? 'در حال ثبت...' : 'افزودن دیسک'">افزودن دیسک</span>
                        </button>
                    </form>
                </article>

                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="font-black text-slate-950">سلامت بکاپ</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">برنامه خودکار</span><span class="font-black {{ $backupSummary['enabled'] ? 'text-emerald-700' : 'text-slate-950' }}">{{ $backupSummary['enabled'] ? 'فعال' : 'غیرفعال' }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">زمان بندی</span><span class="font-black text-slate-950">{{ $backupFrequency }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">نسخه آماده</span><span class="font-black text-slate-950">{{ $backupSummary['ready_count'] }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">آخرین وضعیت</span><span class="font-black text-slate-950">{{ $latestBackupStatus }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">اجرای بعدی</span><span class="font-black text-slate-950" dir="ltr">{{ $backupSummary['next_run_at']?->format('Y/m/d H:i') ?: '-' }}</span></div>
                    </div>
                    @if ($backupSummary['latest_error'])
                        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $backupSummary['latest_error'] }}</p>
                    @endif
                    <a href="{{ $backupUrl }}" class="mt-5 inline-flex w-full justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-[#0069FF]">مدیریت بکاپ</a>
                </article>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">جزئیات فنی</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach ([
                        ['label' => 'Proxmox', 'value' => $server->proxmoxServer?->name ?: '-', 'dir' => 'rtl'],
                        ['label' => 'Node', 'value' => $server->node ?: '-', 'dir' => 'ltr'],
                        ['label' => 'Storage', 'value' => $server->storage ?: '-', 'dir' => 'ltr'],
                        ['label' => 'Network Bridge', 'value' => $server->network_bridge ?: '-', 'dir' => 'ltr'],
                        ['label' => 'Image', 'value' => $server->cloudImage?->name ?: '-', 'dir' => 'rtl'],
                        ['label' => 'OS Template', 'value' => $server->os_template ?: '-', 'dir' => 'ltr'],
                    ] as $item)
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-black text-slate-500">{{ $item['label'] }}</p>
                            <p class="mt-2 truncate font-black text-slate-950" dir="{{ $item['dir'] }}">{{ $item['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">چرخه حیات</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">وضعیت VM</span><span class="rounded-xl px-3 py-1 text-xs font-black" :class="serverState.status_class" x-text="serverState.status_label">{{ $statusLabel }}</span></div>
                    <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">Provisioning</span><span class="inline-flex items-center gap-2 rounded-xl px-3 py-1 text-xs font-black" :class="serverState.provisioning_class"><span x-show="serverState.provisioning_pending" class="size-3 animate-spin rounded-full border-2 border-[#0069FF]/30 border-t-[#0069FF]"></span><span x-text="serverState.provisioning_label">{{ $provisioningLabel }}</span></span></div>
                    <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">آخرین مشاهده</span><span class="font-black text-slate-950" dir="ltr">{{ $server->last_seen_at?->format('Y/m/d H:i') ?: '-' }}</span></div>
                    <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">آخرین شروع</span><span class="font-black text-slate-950" dir="ltr">{{ $server->last_started_at?->format('Y/m/d H:i') ?: '-' }}</span></div>
                    <div class="flex items-center justify-between gap-3"><span class="font-bold text-slate-500">آخرین توقف</span><span class="font-black text-slate-950" dir="ltr">{{ $server->last_stopped_at?->format('Y/m/d H:i') ?: '-' }}</span></div>
                </div>
            </aside>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">دیسک های اضافه</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($server->disks as $disk)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm">
                            <div>
                                <p class="font-black text-slate-950" dir="ltr">{{ $disk->disk_device }} · {{ $disk->size_gb }}GB</p>
                                <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $disk->storage ?: 'default storage' }}</p>
                            </div>
                            <span class="rounded-xl px-3 py-1 text-xs font-black {{ $disk->status === 'ready' ? 'bg-emerald-50 text-emerald-700' : ($disk->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700') }}">{{ $disk->status }}</span>
                        </div>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500">دیسک اضافه ای برای این سرور ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">تاریخچه ارتقا</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($server->upgradeOrders as $order)
                        <div class="rounded-2xl border border-slate-100 p-4 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-black text-slate-950">{{ $order->type === 'bundle' ? 'ارتقای باندل' : 'دیسک اضافه' }}</p>
                                <span class="rounded-xl px-3 py-1 text-xs font-black {{ $order->status === 'succeeded' ? 'bg-emerald-50 text-emerald-700' : ($order->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700') }}">{{ $order->status }}</span>
                            </div>
                            <p class="mt-2 text-xs font-bold leading-6 text-slate-500">
                                @if ($order->type === 'bundle')
                                    مقصد: {{ $order->toBundle?->name ?: '-' }}
                                @else
                                    حجم: {{ $order->after_snapshot['size_gb'] ?? '-' }}GB
                                @endif
                                · افزایش ماهانه: {{ $wallets->format($order->estimated_monthly_delta) }}
                            </p>
                            @if ($order->failure_reason)
                                <p class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $order->failure_reason }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500">هنوز ارتقایی برای این سرور ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section
            class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-5"
            x-data="{
                rebuildDialogOpen: @js($errors->has('rebuild_confirmation') || $errors->has('login_password') || $errors->has('login_username') || $errors->has('hostname') || $errors->has('ssh_public_key')),
                rebuildConfirmation: @js(old('rebuild_confirmation', '')),
                rebuildConfirmationExpected: @js($server->name),
                rebuildAcknowledged: false,
                submitting: false,
                openRebuildDialog() {
                    this.rebuildConfirmation = '';
                    this.rebuildAcknowledged = false;
                    this.rebuildDialogOpen = true;
                    this.$nextTick(() => this.$refs.rebuildConfirmation?.focus());
                },
                closeRebuildDialog() {
                    if (this.submitting) return;
                    this.rebuildDialogOpen = false;
                    this.rebuildConfirmation = '';
                    this.rebuildAcknowledged = false;
                },
                canRebuild() {
                    return this.rebuildAcknowledged && this.rebuildConfirmation.trim() === this.rebuildConfirmationExpected;
                },
            }"
            @keydown.window.escape="closeRebuildDialog()"
        >
            <form action="{{ route('customer.servers.rebuild', $server, false) }}" method="POST" x-on:submit="submitting = true">
                @csrf
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-black text-amber-700">Rebuild</p>
                        <h2 class="mt-1 font-black text-amber-950">بازسازی سیستم عامل</h2>
                        <p class="mt-2 text-sm font-bold leading-7 text-amber-800">دیسک اصلی پاک می شود و VM با همان VMID، IP، منابع و Image فعلی دوباره ساخته می شود. بکاپ ها و دیسک های اضافه جداگانه نگهداری می شوند.</p>
                        <p class="mt-3 inline-flex rounded-xl bg-white px-3 py-2 text-xs font-black text-amber-800">هزینه بازسازی: {{ $formattedRebuildFee }}</p>
                        @if ($isRebuilding)
                            <p class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-black text-[#0069FF]">
                                <span class="size-3 animate-spin rounded-full border-2 border-[#0069FF]/30 border-t-[#0069FF]"></span>
                                بازسازی در حال انجام است
                            </p>
                        @elseif ($rebuildError)
                            <p class="mt-3 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700">آخرین خطای بازسازی: {{ $rebuildError }}</p>
                        @endif
                        @unless ($canRebuild)
                            <p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs font-bold text-amber-800">بازسازی فقط وقتی ممکن است که سرور آماده باشد، Image فعال باشد و عملیات حذف، provisioning یا ارتقا در جریان نباشد.</p>
                        @endunless
                    </div>
                    <button type="button" @click="openRebuildDialog()" @disabled(! $canRebuild) class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-amber-600 px-5 py-3 text-sm font-black text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60 lg:w-auto">
                        بازسازی سرور
                    </button>
                </div>

                <div
                    x-cloak
                    x-show="rebuildDialogOpen"
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="customer-rebuild-dialog-title"
                >
                    <div class="w-full max-w-2xl rounded-[1.5rem] border border-amber-200 bg-white p-5 shadow-2xl shadow-slate-950/30" @click.outside="closeRebuildDialog()">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-black text-amber-700">تأیید بازسازی</p>
                                <h3 id="customer-rebuild-dialog-title" class="mt-1 text-xl font-black text-slate-950">اطلاعات ورود جدید را بررسی کنید</h3>
                            </div>
                            <button type="button" @click="closeRebuildDialog()" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50" aria-label="بستن">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>

                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold leading-7 text-amber-900">
                            بازسازی، سیستم عامل و داده های داخل دیسک اصلی را حذف می کند. قبل از ثبت، از بکاپ های لازم مطمئن شوید. هزینه این بازسازی: {{ $formattedRebuildFee }}.
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="hostname" class="block text-xs font-black text-slate-500">Hostname</label>
                                <input id="hostname" name="hostname" type="text" value="{{ old('hostname', $server->hostname ?: $server->name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none" dir="ltr">
                                @error('hostname')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="login_username" class="block text-xs font-black text-slate-500">Username</label>
                                <input id="login_username" name="login_username" type="text" value="{{ old('login_username', $server->login_username ?: $server->cloudImage?->default_username) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none" dir="ltr">
                                @error('login_username')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="login_password" class="block text-xs font-black text-slate-500">Password</label>
                                <input id="login_password" name="login_password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none" dir="ltr" autocomplete="new-password">
                                <p class="mt-2 text-xs font-bold leading-6 text-slate-500">اگر خالی بماند رمز فعلی نگه داشته می شود؛ اگر رمز فعلی وجود نداشته باشد و SSH Key هم خالی باشد، رمز امن جدید ساخته می شود.</p>
                                @error('login_password')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="ssh_public_key" class="block text-xs font-black text-slate-500">SSH Public Key</label>
                                <textarea id="ssh_public_key" name="ssh_public_key" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none" dir="ltr">{{ old('ssh_public_key', $server->ssh_public_key) }}</textarea>
                                @error('ssh_public_key')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <label class="mt-5 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-700">
                            <input type="checkbox" x-model="rebuildAcknowledged" class="mt-1 size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                            <span>می دانم که دیسک اصلی این سرور پاک می شود و بازگشت فقط از بکاپ ممکن است.</span>
                        </label>

                        <div class="mt-4">
                            <label for="rebuild_confirmation" class="block text-xs font-black text-slate-500">نام سرور برای تأیید</label>
                            <input
                                id="rebuild_confirmation"
                                name="rebuild_confirmation"
                                type="text"
                                x-ref="rebuildConfirmation"
                                x-model="rebuildConfirmation"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="{{ $server->name }}"
                                class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none"
                                dir="ltr"
                            >
                            @error('rebuild_confirmation')
                                <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="button" @click="closeRebuildDialog()" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                انصراف
                            </button>
                            <button
                                type="submit"
                                x-bind:disabled="submitting || !canRebuild()"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-sm font-black text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            >
                                <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                <span x-text="submitting ? 'در حال ثبت...' : 'شروع بازسازی'">شروع بازسازی</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        @if ($server->isDeleting() && ! $server->delete_failed_at && ! $deleteAttemptIsStale)
            <section class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-black text-amber-950">حذف این سرور در حال انجام است</h2>
                        <p class="mt-2 text-sm font-bold leading-7 text-amber-800">در حال بررسی Proxmox، خاموش سازی و پاک کردن VM هستیم. Billing این سرور متوقف شده است.</p>
                    </div>
                    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-100 px-4 py-3 text-sm font-black text-amber-800 sm:w-auto">
                        <span class="size-4 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-700"></span>
                        در حال حذف
                    </span>
                </div>
            </section>
        @else
            <section
                class="rounded-[1.75rem] border border-red-200 bg-red-50 p-5"
                x-data="{
                    deleteDialogOpen: @js($errors->has('delete_confirmation')),
                    deleteConfirmation: @js(old('delete_confirmation', '')),
                    deleteConfirmationExpected: @js($server->name),
                    submitting: false,
                    openDeleteDialog() {
                        this.deleteConfirmation = '';
                        this.deleteDialogOpen = true;
                        this.$nextTick(() => this.$refs.deleteConfirmation?.focus());
                    },
                    closeDeleteDialog() {
                        if (this.submitting) return;
                        this.deleteDialogOpen = false;
                        this.deleteConfirmation = '';
                    },
                    canDelete() {
                        return this.deleteConfirmation.trim() === this.deleteConfirmationExpected;
                    },
                }"
                @keydown.window.escape="closeDeleteDialog()"
            >
                <form action="{{ route('customer.servers.destroy', $server, false) }}" method="POST" x-on:submit="submitting = true">
                    @csrf
                    @method('DELETE')
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-black text-red-600">Danger Zone</p>
                            <h2 class="mt-1 font-black text-red-950">{{ ($server->delete_failed_at || $deleteAttemptIsStale) ? 'تلاش دوباره برای حذف سرور' : 'حذف دائمی سرور' }}</h2>
                            <p class="mt-2 text-sm font-bold leading-7 text-red-800">VM ابتدا خاموش و سپس از Proxmox حذف می شود. پس از حذف موفق، IP رزرو شده آزاد می شود. بکاپ ها جداگانه نگهداری می شوند.</p>
                            @if ($server->delete_failed_at && $server->delete_error)
                                <p class="mt-3 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700">آخرین خطا: {{ $server->delete_error }}</p>
                            @endif
                        </div>
                        <div class="flex w-full shrink-0 flex-col gap-3 lg:w-auto">
                            <button type="button" @click="openDeleteDialog()" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 lg:w-auto">
                                <span>{{ ($server->delete_failed_at || $deleteAttemptIsStale) ? 'تلاش دوباره' : 'حذف سرور' }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="deleteDialogOpen"
                        x-transition.opacity
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-6"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="customer-delete-dialog-title"
                    >
                        <div class="w-full max-w-lg rounded-[1.5rem] border border-red-200 bg-white p-5 shadow-2xl shadow-slate-950/30" @click.outside="closeDeleteDialog()">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black text-red-600">تأیید حذف</p>
                                    <h3 id="customer-delete-dialog-title" class="mt-1 text-xl font-black text-slate-950">نام سرور را وارد کنید</h3>
                                </div>
                                <button type="button" @click="closeDeleteDialog()" class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50" aria-label="بستن">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>

                            <p class="mt-3 text-sm font-bold leading-7 text-slate-600">
                                برای حذف این سرور، دقیقاً نام
                                <span class="rounded-lg bg-slate-100 px-2 py-1 font-black text-slate-950" dir="ltr">{{ $server->name }}</span>
                                را وارد کنید. این کار قابل بازگشت نیست.
                            </p>

                            <div class="mt-4">
                                <label for="delete_confirmation" class="block text-xs font-black text-slate-500">تأیید حذف</label>
                                <input
                                    id="delete_confirmation"
                                    name="delete_confirmation"
                                    type="text"
                                    x-ref="deleteConfirmation"
                                    x-model="deleteConfirmation"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="{{ $server->name }}"
                                    class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none"
                                    dir="ltr"
                                >
                                @error('delete_confirmation')
                                    <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <button type="button" @click="closeDeleteDialog()" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                    انصراف
                                </button>
                                <button
                                    type="submit"
                                    x-bind:disabled="submitting || !canDelete()"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                >
                                    <span x-show="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    <span x-text="submitting ? 'در حال ثبت...' : 'حذف سرور'">حذف سرور</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        @endif
    </section>
@endsection
