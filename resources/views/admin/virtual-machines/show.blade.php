@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')
@section('title', 'نمایش ماشین مجازی')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10" x-data="{ confirmAction: null }" @keydown.escape.window="confirmAction = null">

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('provisioning_password'))
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
            رمز عبور اولیه فقط همین حالا نمایش داده می‌شود: <span dir="ltr">{{ session('provisioning_password') }}</span>
        </div>
    @endif

    @php
        $walletBlocked = ($effectiveWalletBalance ?? 0) < \App\Models\AppSetting::customerWalletNegativeThreshold();
    @endphp

    {{-- Hero header --}}
    <div class="relative overflow-hidden rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <p class="text-sm font-bold text-white/60">VM #{{ $vm->id }}</p>
                    @php
                        $statusColors = match($vm->provisioning_status) {
                            \App\Models\VirtualMachine::PROVISION_READY => 'bg-emerald-400/20 text-emerald-300',
                            \App\Models\VirtualMachine::PROVISION_PENDING => 'bg-amber-400/20 text-amber-300',
                            \App\Models\VirtualMachine::PROVISION_FAILED => 'bg-red-400/20 text-red-300',
                            default => 'bg-white/10 text-white/50',
                        };
                        $statusLabels = match($vm->provisioning_status) {
                            \App\Models\VirtualMachine::PROVISION_READY => 'آماده',
                            \App\Models\VirtualMachine::PROVISION_PENDING => 'در حال راه‌اندازی',
                            \App\Models\VirtualMachine::PROVISION_FAILED => 'ناموفق',
                            default => $vm->provisioning_status,
                        };
                    @endphp
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusColors }}">{{ $statusLabels }}</span>
                </div>
                <h1 class="mt-1 text-2xl font-black md:text-4xl" dir="ltr">{{ $vm->display_name }}</h1>
                <p class="mt-1 text-sm text-white/50" dir="ltr">{{ $vm->name }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/70">
                    <span dir="ltr">{{ $vm->ip_address ?: 'بدون IP' }}</span>
                    <span>·</span>
                    <span>{{ $vm->proxmoxServer?->name ?: 'فقط لوکال' }}</span>
                    <span>·</span>
                    <span>{{ $vm->node ? 'Node ' . $vm->node : '' }}{{ $vm->vmid ? ' · VMID ' . $vm->vmid : '' }}</span>
                    <span>·</span>
                    <span>{{ $vm->provider === \App\Models\VirtualMachine::PROVIDER_HETZNER ? 'Hetzner' : 'Proxmox' }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/60">
                    <span>پروژه: <span class="font-bold text-white/80">{{ $vm->project?->name ?: '—' }}</span></span>
                    <span>·</span>
                    <span>مالک: <span class="font-bold text-white/80">{{ $vm->project?->owner?->name ?: '—' }}</span></span>
                    <span>·</span>
                    <span>ایجاد کننده: <span class="font-bold text-white/80">{{ $vm->creator?->name ?: '—' }}</span></span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.virtual-machines.edit', $vm) }}" class="rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#031B4E] transition hover:bg-slate-100">ویرایش</a>
                <a href="{{ route('admin.virtual-machines.transfer.show', $vm) }}" class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">انتقال مالکیت</a>
                @if($vm->proxmoxServer && $vm->node && $vm->vmid && $vm->provisioning_status === \App\Models\VirtualMachine::PROVISION_READY && ! $vm->isActionLocked())
                    <a href="{{ route('admin.virtual-machines.console.show', $vm) }}" class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">کنسول</a>
                @endif

                {{-- Action buttons with confirmations --}}
                @if($vm->provisioning_status === 'failed' && $vm->cloud_image_id)
                    <button
                        type="button"
                        @click="confirmAction = 'retry-provisioning'"
                        class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20"
                    >تلاش مجدد راه‌اندازی</button>
                @elseif($vm->isRunning())
                    <button
                        type="button"
                        @click="confirmAction = 'stop'"
                        class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20"
                    >خاموش کردن</button>
                @else
                    <button
                        type="button"
                        @click="{{ $walletBlocked ? 'null' : "confirmAction = 'start'" }}"
                        @disabled($walletBlocked)
                        class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                    >روشن کردن</button>
                @endif

                @if(! $vm->isDeleted() && (! $vm->isDeleting() || $vm->delete_failed_at || $vm->deleteAttemptIsStale()))
                    <button
                        type="button"
                        @click="confirmAction = 'delete'"
                        class="rounded-lg bg-red-500/20 px-4 py-2.5 text-sm font-bold text-red-300 transition hover:bg-red-500/30"
                    >حذف سرور</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @php
            $statusLabel = match($vm->status) {
                \App\Models\VirtualMachine::STATUS_RUNNING => 'روشن',
                \App\Models\VirtualMachine::STATUS_STOPPED => 'خاموش',
                \App\Models\VirtualMachine::STATUS_SUSPENDED => 'تعلیق',
                \App\Models\VirtualMachine::STATUS_DELETING => 'در حال حذف',
                \App\Models\VirtualMachine::STATUS_DELETED => 'حذف شده',
                default => $vm->status ?: '—',
            };
            $statusTone = match($vm->status) {
                \App\Models\VirtualMachine::STATUS_RUNNING => 'text-emerald-600',
                \App\Models\VirtualMachine::STATUS_SUSPENDED => 'text-red-600',
                \App\Models\VirtualMachine::STATUS_DELETING => 'text-amber-700',
                \App\Models\VirtualMachine::STATUS_DELETED => 'text-slate-500',
                default => 'text-slate-700',
            };
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">وضعیت فعلی</p>
            <p class="mt-3 text-xl font-black {{ $statusTone }}">{{ $statusLabel }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">هزینه تخمینی ماهانه</p>
            <p class="mt-3 text-xl font-black text-[#0069FF]">{{ $money->format($vm->isRunning() ? $billing->estimateMonthly($vm) : $billing->estimateStoppedMonthly($vm)) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">مصرف ماه جاری</p>
            <p class="mt-3 text-xl font-black {{ $currentMonthUsage > 0 ? 'text-slate-950' : 'text-slate-400' }}">{{ $money->format($currentMonthUsage) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">مبلغ صدور نشده</p>
            <p class="mt-3 text-xl font-black {{ $currentAccrued > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money->format($currentAccrued) }}</p>
        </div>
    </section>

    {{-- Wallet warning --}}
    @if ($walletBlocked)
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
            کیف پول فضای کاری این VM منفی است. روشن کردن آن تا شارژ شدن کیف پول ممکن نیست.
        </div>
    @endif

    {{-- Billing & Usage section --}}
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-black text-slate-950">اطلاعات مالی</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">موجودی کیف پول مالک</p>
                <p class="mt-2 text-lg font-black {{ ($wallet?->balance ?? 0) < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallet ? $money->format($wallet->balance) : '—' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">هزینه ساعتی (روشن)</p>
                <p class="mt-2 text-lg font-black text-slate-950">{{ $money->format((int) round($billing->hourlyWhenRunning($vm))) }}/ساعت</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">هزینه ساعتی (خاموش)</p>
                <p class="mt-2 text-lg font-black text-slate-950">{{ $money->format((int) round($billing->persistentHourly($vm))) }}/ساعت</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">کل مصرف تاریخی</p>
                <p class="mt-2 text-lg font-black {{ $totalUsage > 0 ? 'text-slate-950' : 'text-slate-400' }}">{{ $money->format($totalUsage) }}</p>
            </div>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">مالک صورتحساب</p>
                <p class="mt-2 font-black text-slate-950">{{ $billingCustomer?->name ?: '—' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">معاف از مالیات</p>
                <p class="mt-2 font-black text-slate-950">{{ $vm->tax_exempt ? 'بله' : 'خیر' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-bold text-slate-500">آخرین صدور صورتحساب</p>
                <p class="mt-2 font-black text-slate-950">{{ $vm->last_billed_at ? $vm->last_billed_at->format('Y/m/d H:i') : '—' }}</p>
            </div>
        </div>
    </section>

    {{-- Hardware & Bundle --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">مشخصات سخت‌افزاری</h2>
            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-xl bg-slate-50 p-4 text-center">
                    <p class="text-2xl font-black text-slate-950">{{ $vm->cpu_cores }}</p>
                    <p class="text-xs text-slate-500">هسته CPU</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 text-center">
                    <p class="text-2xl font-black text-slate-950">{{ $vm->ram_gb }}GB</p>
                    <p class="text-xs text-slate-500">رم</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 text-center">
                    <p class="text-2xl font-black text-slate-950">{{ $vm->disk_gb }}GB</p>
                    <p class="text-xs text-slate-500">دیسک</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 text-center">
                    <p class="text-2xl font-black text-slate-950">{{ $vm->ip_count }}</p>
                    <p class="text-xs text-slate-500">آدرس IP</p>
                </div>
            </div>
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 p-4">
                <p class="font-black text-slate-950">باندل قیمت‌گذاری</p>
                @if($vm->bundle)
                    <p class="mt-2 text-sm text-slate-600">{{ $vm->bundle->name }} — {{ $money->format($vm->bundle->monthly_price) }} / ماه</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">قیمت‌گذاری اختصاصی بر اساس نرخ منابع</p>
                @endif
            </div>
            @if($vm->last_started_at || $vm->last_stopped_at)
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @if($vm->last_started_at)
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-bold text-slate-500">آخرین روشن شدن</p>
                            <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $vm->last_started_at->format('Y/m/d H:i') }}</p>
                        </div>
                    @endif
                    @if($vm->last_stopped_at)
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-bold text-slate-500">آخرین خاموش شدن</p>
                            <p class="mt-2 font-black text-slate-950" dir="ltr">{{ $vm->last_stopped_at->format('Y/m/d H:i') }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">اطلاعات زیرساخت</h2>
            <div class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">سرور</span>
                    <span class="font-bold text-slate-950">{{ $vm->proxmoxServer?->name ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">Node</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->node ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">VMID</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->vmid ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">قالب</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->template_vmid ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">تصویر</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->cloudImage?->name ?: $vm->iso_volume ?: $vm->os_template ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">ذخیره‌سازی</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->storage ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">شبکه</span>
                    <span class="font-bold text-slate-950" dir="ltr">{{ $vm->network_bridge ?: '—' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                    <span class="font-bold text-slate-500">MAC</span>
                    <span class="font-bold text-slate-950 text-xs" dir="ltr">{{ $vm->mac_address ?: '—' }}</span>
                </div>
                @if($vm->ssh_public_key)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="font-bold text-slate-500">کلید SSH</p>
                        <p class="mt-1 break-all text-xs text-slate-700" dir="ltr">{{ substr($vm->ssh_public_key, 0, 60) }}...</p>
                    </div>
                @endif
                @if($vm->login_username)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                        <span class="font-bold text-slate-500">نام کاربری</span>
                        <span class="font-bold text-slate-950" dir="ltr">{{ $vm->login_username }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Disks & Upgrade Orders --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">دیسک‌های اضافه</h2>
            <div class="mt-5 space-y-3">
                @forelse($vm->disks as $disk)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                        <div>
                            <p class="font-black text-slate-950" dir="ltr">{{ $disk->disk_device }} · {{ $disk->size_gb }}GB</p>
                            <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $disk->storage ?: 'پیش‌فرض' }}</p>
                        </div>
                        @php
                            $diskStatus = match($disk->status) {
                                'ready' => 'bg-emerald-50 text-emerald-700',
                                'failed' => 'bg-red-50 text-red-700',
                                default => 'bg-amber-50 text-amber-700',
                            };
                            $diskStatusLabel = match($disk->status) {
                                'ready' => 'آماده',
                                'failed' => 'ناموفق',
                                default => $disk->status,
                            };
                        @endphp
                        <span class="rounded-lg px-3 py-1 text-xs font-black {{ $diskStatus }}">{{ $diskStatusLabel }}</span>
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-500">دیسک اضافه‌ای ثبت نشده است.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">تاریخچه ارتقاء</h2>
            <div class="mt-5 space-y-3">
                @forelse($vm->upgradeOrders as $order)
                    <div class="rounded-xl border border-slate-100 p-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-black text-slate-950">{{ $order->type }} #{{ $order->id }}</p>
                            @php
                                $orderStatus = match($order->status) {
                                    'succeeded' => 'bg-emerald-50 text-emerald-700',
                                    'failed' => 'bg-red-50 text-red-700',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                                $orderStatusLabel = match($order->status) {
                                    'succeeded' => 'انجام شده',
                                    'failed' => 'ناموفق',
                                    'pending' => 'در انتظار',
                                    default => $order->status,
                                };
                            @endphp
                            <span class="rounded-lg px-3 py-1 text-xs font-black {{ $orderStatus }}">{{ $orderStatusLabel }}</span>
                        </div>
                        <p class="mt-2 text-xs font-bold text-slate-500">
                            تغییر ماهانه: {{ $money->format($order->estimated_monthly_delta) }}
                            @if($order->applied_at)
                                · اعمال شده: {{ $order->applied_at->format('Y/m/d H:i') }}
                            @endif
                        </p>
                        @if($order->failure_reason)
                            <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700">{{ $order->failure_reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-500">تاریخچه ارتقاء وجود ندارد.</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Hidden forms for actions --}}
    @if($vm->provisioning_status === 'failed' && $vm->cloud_image_id)
        <form id="form-retry-provisioning" method="POST" action="{{ route('admin.virtual-machines.retry-provisioning', $vm) }}" class="hidden">@csrf</form>
    @elseif($vm->isRunning())
        <form id="form-stop" method="POST" action="{{ route('admin.virtual-machines.stop', $vm) }}" class="hidden">
            @csrf
            <input type="hidden" name="power_generation" value="{{ (int) data_get($vm->desired_state, 'power_generation', 0) }}">
        </form>
    @else
        <form id="form-start" method="POST" action="{{ route('admin.virtual-machines.start', $vm) }}" class="hidden">@csrf</form>
    @endif
    @if(! $vm->isDeleted() && (! $vm->isDeleting() || $vm->delete_failed_at || $vm->deleteAttemptIsStale()))
        <form id="form-delete" method="POST" action="{{ route('admin.virtual-machines.destroy', $vm) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif

    {{-- Confirmation modal --}}
    <div
        x-show="confirmAction"
        x-transition.opacity
        @click.self="confirmAction = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" @click.stop>
            <template x-if="confirmAction === 'retry-provisioning'">
                <div>
                    <h3 class="text-lg font-black text-slate-950">تلاش مجدد راه‌اندازی</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید راه‌اندازی این VM را مجدداً تلاش کنید؟</p>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" form="form-retry-provisioning" class="flex-1 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#0050D0]">بله، تلاش مجدد</button>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
            <template x-if="confirmAction === 'stop'">
                <div>
                    <h3 class="text-lg font-black text-slate-950">خاموش کردن سرور</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید این سرور را خاموش کنید؟ از این لحظه CPU و RAM هزینه‌ای نخواهند داشت.</p>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" form="form-stop" class="flex-1 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#0050D0]">بله، خاموش کن</button>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
            <template x-if="confirmAction === 'start'">
                <div>
                    <h3 class="text-lg font-black text-slate-950">روشن کردن سرور</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید این سرور را روشن کنید؟ از این لحظه CPU و RAM نیز هزینه خواهند داشت.</p>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" form="form-start" class="flex-1 rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#0050D0]">بله، روشن کن</button>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
            <template x-if="confirmAction === 'delete'">
                <div>
                    <h3 class="text-lg font-black text-red-700">حذف سرور</h3>
                    <p class="mt-2 text-sm text-slate-600">آیا مطمئن هستید که می‌خواهید این VM را از Proxmox و پنل حذف کنید؟ اگر قبلاً از Proxmox حذف شده باشد، فقط رکورد پنل حذف خواهد شد. <strong class="text-red-700">این عمل غیرقابل بازگشت است.</strong></p>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" form="form-delete" class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700">بله، حذف کن</button>
                        <button type="button" @click="confirmAction = null" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">انصراف</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>
@endsection
