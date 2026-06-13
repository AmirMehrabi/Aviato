@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'نمایش VM')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
@if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
@if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>@endif
@if (session('provisioning_password'))<div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">Password اولیه فقط همین حالا نمایش داده می‌شود: <span dir="ltr">{{ session('provisioning_password') }}</span></div>@endif
@php
    $walletBlocked = ($wallet?->balance ?? 0) < \App\Models\AppSetting::customerWalletNegativeThreshold();
@endphp
<div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
    <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
    <div class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-bold text-white/60">VM #{{ $vm->id }} · Billing: {{ $billingCustomer?->name ?: '—' }}</p>
            <h1 class="mt-1 text-2xl font-black md:text-4xl" dir="ltr">{{ $vm->name }}</h1>
            <p class="mt-3 leading-8 text-white/75" dir="ltr">{{ $vm->ip_address ?: 'no-ip' }} · {{ $vm->proxmoxServer?->name ?: 'local only' }} · {{ $vm->provisioning_status }}</p>
            <p class="mt-2 text-sm font-bold text-white/70">Project: {{ $vm->project?->name ?: '—' }} · Owner: {{ $vm->project?->owner?->name ?: '—' }} · Created by: {{ $vm->creator?->name ?: '—' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.virtual-machines.edit', $vm) }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#031B4E]">ویرایش</a>
            <a href="{{ route('admin.virtual-machines.transfer.show', $vm) }}" class="rounded-lg bg-purple-100 px-5 py-3 text-sm font-black text-purple-900 hover:bg-purple-200">Transfer Ownership</a>
            @if($vm->proxmoxServer && $vm->node && $vm->vmid && $vm->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && ! $vm->isActionLocked())
                <a href="{{ route('admin.virtual-machines.console.show', $vm) }}" class="rounded-lg bg-sky-300 px-5 py-3 text-sm font-black text-sky-950">Console</a>
            @endif

            @if($vm->provisioning_status === 'failed' && $vm->cloud_image_id)
                <form method="POST" action="{{ route('admin.virtual-machines.retry-provisioning', $vm) }}" onsubmit="return confirm('Retry provisioning for this VM?')">
                    @csrf
                    <button class="rounded-lg bg-sky-300 px-5 py-3 text-sm font-black text-sky-950">Retry provisioning</button>
                </form>
            @elseif($vm->isRunning())
                <form method="POST" action="{{ route('admin.virtual-machines.stop', $vm) }}">
                    @csrf
                    <button class="rounded-lg bg-amber-300 px-5 py-3 text-sm font-black text-amber-950">خاموش کردن</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.virtual-machines.start', $vm) }}">
                    @csrf
                    <button @disabled($walletBlocked) class="rounded-lg bg-[#B8D6FF] px-5 py-3 text-sm font-black text-[#031B4E] disabled:cursor-not-allowed disabled:opacity-50">روشن کردن</button>
                </form>
            @endif
            @if(! $vm->isDeleted() && (! $vm->isDeleting() || $vm->delete_failed_at || $vm->deleteAttemptIsStale()))
                <form method="POST" action="{{ route('admin.virtual-machines.destroy', $vm) }}" onsubmit="return confirm('Delete this VM from Proxmox and the panel? If it is already missing from Proxmox, the panel record will still be removed.');">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg bg-red-400 px-5 py-3 text-sm font-black text-red-950">حذف سرور</button>
                </form>
            @endif
        </div>
    </div>
</div>
<section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
@foreach([
['label' => 'وضعیت', 'value' => $vm->status === \App\Models\VirtualMachine::STATUS_SUSPENDED ? 'تعلیق' : ($vm->isRunning() ? 'روشن' : 'خاموش'), 'tone' => $vm->status === \App\Models\VirtualMachine::STATUS_SUSPENDED ? 'text-red-600' : ($vm->isRunning() ? 'text-[#0069FF]' : 'text-slate-700')],
['label' => 'هزینه ماهانه در وضعیت فعلی', 'value' => $money->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)), 'tone' => 'text-[#0069FF]'],
['label' => 'هزینه ماهانه اگر خاموش باشد', 'value' => $money->format($billing->estimateStoppedMonthly($vm)), 'tone' => 'text-amber-700'],
['label' => 'مصرف محاسبه نشده', 'value' => $money->format($billing->currentAccrued($vm)), 'tone' => 'text-slate-950'],
['label' => 'Billing Customer', 'value' => $billingCustomer?->name ?: '—', 'tone' => 'text-slate-950'],
] as $card)<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold text-slate-500">{{ $card['label'] }}</p><p class="mt-3 text-xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p></div>@endforeach
</section>
@if ($walletBlocked)
    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
        کیف پول فضای کاری این VM منفی است. روشن کردن آن تا شارژ شدن کیف پول ممکن نیست.
    </div>
@endif
<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-xl font-black">مشخصات سخت‌افزار و Billing</h2><div class="mt-5 grid gap-3 md:grid-cols-4"><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-2xl font-black">{{ $vm->cpu_cores }}</p><p class="text-xs text-slate-500">CPU Core فقط روشن</p></div><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-2xl font-black">{{ $vm->ram_gb }}GB</p><p class="text-xs text-slate-500">RAM فقط روشن</p></div><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-2xl font-black">{{ $vm->disk_gb }}GB</p><p class="text-xs text-slate-500">Disk همیشه</p></div><div class="rounded-xl bg-slate-50 p-4 text-center"><p class="text-2xl font-black">{{ $vm->ip_count }}</p><p class="text-xs text-slate-500">IP همیشه</p></div></div><div class="mt-5 rounded-xl border border-dashed border-slate-300 p-4"><p class="font-black">باندل</p><p class="mt-2 text-sm text-slate-600">{{ $vm->bundle ? $vm->bundle->name . ' - ' . $money->format($vm->bundle->monthly_price) . ' / ماه روشن' : 'Custom pricing از قیمت منابع' }}</p></div></section><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-xl font-black">Proxmox</h2><div class="mt-5 space-y-3 text-sm"><p><span class="font-bold text-slate-500">Server:</span> {{ $vm->proxmoxServer?->name ?: '—' }}</p><p><span class="font-bold text-slate-500">Node:</span> <span dir="ltr">{{ $vm->node ?: '—' }}</span></p><p><span class="font-bold text-slate-500">VMID:</span> <span dir="ltr">{{ $vm->vmid ?: '—' }}</span></p><p><span class="font-bold text-slate-500">Template:</span> <span dir="ltr">{{ $vm->template_vmid ?: '—' }}</span></p><p><span class="font-bold text-slate-500">Image:</span> <span dir="ltr">{{ $vm->cloudImage?->name ?: $vm->iso_volume ?: $vm->os_template ?: '—' }}</span></p><p><span class="font-bold text-slate-500">Storage:</span> <span dir="ltr">{{ $vm->storage ?: '—' }}</span></p><p><span class="font-bold text-slate-500">Bridge:</span> <span dir="ltr">{{ $vm->network_bridge ?: '—' }}</span></p><p><span class="font-bold text-slate-500">Login:</span> <span dir="ltr">{{ $vm->login_username ?: '—' }}</span></p></div></section></div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black">دیسک های اضافه</h2>
        <div class="mt-5 space-y-3">
            @forelse($vm->disks as $disk)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div><p class="font-black" dir="ltr">{{ $disk->disk_device }} · {{ $disk->size_gb }}GB</p><p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $disk->storage ?: 'default' }}</p></div>
                    <span class="rounded-lg px-3 py-1 text-xs font-black {{ $disk->status === 'ready' ? 'bg-[#EBF3FF] text-[#0069FF]' : ($disk->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700') }}">{{ $disk->status }}</span>
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-500">دیسک اضافه ای ثبت نشده است.</p>
            @endforelse
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black">Upgrade Orders</h2>
        <div class="mt-5 space-y-3">
            @forelse($vm->upgradeOrders as $order)
                <div class="rounded-xl border border-slate-100 p-4 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-black">{{ $order->type }} #{{ $order->id }}</p>
                        <span class="rounded-lg px-3 py-1 text-xs font-black {{ $order->status === 'succeeded' ? 'bg-[#EBF3FF] text-[#0069FF]' : ($order->status === 'failed' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700') }}">{{ $order->status }}</span>
                    </div>
                    <p class="mt-2 text-xs font-bold text-slate-500">Delta: {{ $money->format($order->estimated_monthly_delta) }} / month · Applied: {{ $order->applied_at?->format('Y/m/d H:i') ?: '—' }}</p>
                    @if($order->failure_reason)<p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $order->failure_reason }}</p>@endif
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-500">No upgrade orders yet.</p>
            @endforelse
        </div>
    </section>
</div>
</div>
@endsection
