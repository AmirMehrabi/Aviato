@extends('layouts.admin')
@section('title', 'ویرایش IP Pool')
@section('content')
    @php
        $usedNow = $inventory['reserved'] + $inventory['assigned'];
        $reserveableIds = $pool->addresses
            ->whereIn('status', ['available', 'released'])
            ->pluck('id')
            ->values()
            ->all();
    @endphp

    <div class="px-4 py-6 md:px-8 lg:px-10">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
        @endif

        @if ($errors->has('reservation'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first('reservation') }}</div>
        @endif

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-500">
                            <a href="{{ route('admin.ip-pools.index') }}" class="transition hover:text-[#0069FF]">IP Pools</a>
                            <span class="text-slate-300">/</span>
                            <span class="text-slate-900">{{ $pool->name }}</span>
                        </div>
                        <h1 class="mt-3 text-2xl font-black">ویرایش {{ $pool->name }}</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500">
                            مشخصات Pool را تغییر دهید و همزمان inventory واقعی IPها را ببینید. IPهای released هنوز قابل رزرو دوباره هستند.
                        </p>
                    </div>
                    <span class="rounded-md px-2.5 py-1 text-xs font-black {{ $pool->is_active ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-500' }}">
                        {{ $pool->is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.ip-pools.update', $pool) }}" class="mt-6">
                    @method('PUT')
                    @include('admin.ip-pools._form')
                </form>
            </section>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-[#B8D6FF] bg-[#031B4E] p-5 text-white shadow-sm">
                    <p class="text-xs font-black text-white/60">بازه</p>
                    <p class="mt-2 text-xl font-black">{{ $pool->start_ip }}{{ $pool->end_ip ? ' - '.$pool->end_ip : '' }}</p>
                    <p class="mt-2 font-mono text-sm text-white/75" dir="ltr">gw {{ $pool->gateway }} /{{ $pool->prefix_length }}</p>
                    <p class="mt-3 text-xs leading-6 text-white/65">
                        The pool keeps generating and reserving addresses from this range. Use the list below to reserve free rows or reclaim released ones.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-black text-slate-500">راهنمای رزرو</p>
                    <p class="mt-2 text-sm leading-7 text-slate-600">
                        Only <span class="font-black text-slate-900">available</span> and <span class="font-black text-slate-900">released</span> rows can be reserved. Assigned and already reserved rows stay locked.
                    </p>
                </div>
            </aside>
        </div>

        <section class="mt-6 grid gap-3 lg:grid-cols-5">
            <div class="rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] p-4">
                <p class="text-xs font-black text-[#0069FF]">در حال استفاده</p>
                <p class="mt-2 text-2xl font-black text-[#031B4E]">{{ $usedNow }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-black text-slate-500">آزاد</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $inventory['available'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                <p class="text-xs font-black text-amber-700">آزادشده</p>
                <p class="mt-2 text-2xl font-black text-amber-900">{{ $inventory['released'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                <p class="text-xs font-black text-emerald-700">رزروشده</p>
                <p class="mt-2 text-2xl font-black text-emerald-900">{{ $inventory['reserved'] }}</p>
            </div>
            <div class="rounded-lg bg-slate-950 p-4 text-white">
                <p class="text-xs font-black text-white/60">تخصیص‌یافته</p>
                <p class="mt-2 text-2xl font-black">{{ $inventory['assigned'] }}</p>
            </div>
        </section>

        <section
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            x-data="{
                selectedAddresses: [],
                reserveableIds: @js($reserveableIds),
                toggle(id, checked) {
                    const normalized = Number(id);
                    if (checked) {
                        if (!this.selectedAddresses.includes(normalized)) {
                            this.selectedAddresses.push(normalized);
                        }
                        return;
                    }

                    this.selectedAddresses = this.selectedAddresses.filter((item) => item !== normalized);
                },
                toggleAll(checked) {
                    this.selectedAddresses = checked ? [...this.reserveableIds] : [];
                },
                isSelected(id) {
                    return this.selectedAddresses.includes(Number(id));
                },
            }"
        >
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">آدرس‌های IP</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $pool->proxmoxServer?->name ?: 'بدون Proxmox' }} · {{ $pool->node ?: 'all nodes' }}</p>
                </div>
                <form method="POST" action="{{ route('admin.ip-pools.addresses.reserve', $pool) }}" id="bulk-reserve-form" class="flex items-center gap-3">
                    @csrf
                    <template x-for="id in selectedAddresses" :key="`bulk-address-${id}`">
                        <input type="hidden" name="address_ids[]" :value="id">
                    </template>
                    <button
                        type="submit"
                        class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="selectedAddresses.length === 0"
                    >
                        رزرو موارد انتخاب‌شده
                    </button>
                </form>
            </div>

            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold text-slate-500">
                <span x-text="selectedAddresses.length ? `${selectedAddresses.length} selected` : 'Select free rows to reserve them in bulk'"></span>
                <span class="mx-2 text-slate-300">•</span>
                <span>Released IPs are eligible again.</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="w-12 px-5 py-4">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-[#0069FF]"
                                    @change="toggleAll($event.target.checked)"
                                    :checked="reserveableIds.length > 0 && selectedAddresses.length === reserveableIds.length"
                                    :disabled="reserveableIds.length === 0"
                                >
                            </th>
                            <th class="px-5 py-4">IP Address</th>
                            <th class="px-5 py-4">وضعیت</th>
                            <th class="px-5 py-4">ماشین</th>
                            <th class="px-5 py-4">مشتری</th>
                            <th class="px-5 py-4">زمان رزرو</th>
                            <th class="px-5 py-4">زمان تخصیص</th>
                            <th class="px-5 py-4">زمان آزادسازی</th>
                            <th class="px-5 py-4">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pool->addresses as $address)
                            @php
                                $vm = $address->virtualMachine;
                                $selectable = in_array($address->status, ['available', 'released'], true);
                                $statusClass = match ($address->status) {
                                    'assigned' => 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]',
                                    'reserved' => 'bg-amber-50 text-amber-700 ring-amber-100',
                                    'released' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                    default => 'bg-white text-slate-600 ring-slate-200',
                                };
                            @endphp
                            <tr class="transition hover:bg-[#F8FBFF]">
                                <td class="px-5 py-4">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-slate-300 text-[#0069FF] disabled:cursor-not-allowed disabled:opacity-40"
                                        @change="toggle({{ $address->id }}, $event.target.checked)"
                                        :checked="isSelected({{ $address->id }})"
                                        @disabled(! $selectable)
                                    >
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-mono font-black text-slate-950" dir="ltr">{{ $address->address }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="rounded-md px-2.5 py-1 text-xs font-black ring-1 {{ $statusClass }}">{{ \App\Support\AdminUi::status($address->status) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($vm)
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="block font-black text-slate-950 transition hover:text-[#0069FF]" dir="ltr">{{ $vm->display_name }}</a>
                                        <span class="mt-1 block text-xs text-slate-500">{{ \App\Support\AdminUi::status($vm->status) }} / {{ \App\Support\AdminUi::status($vm->provisioning_status) }}</span>
                                    @else
                                        <span class="text-slate-400">بدون ماشین</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($vm?->customer)
                                        <a href="{{ route('admin.customers.show', $vm->customer) }}" class="font-bold text-[#0069FF] transition hover:text-[#0050D0]">{{ $vm->customer->name }}</a>
                                    @else
                                        <span class="text-slate-400">بدون مشتری</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">{{ $address->reserved_at?->format('Y/m/d H:i') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">{{ $address->assigned_at?->format('Y/m/d H:i') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">{{ $address->released_at?->format('Y/m/d H:i') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($selectable)
                                        <form method="POST" action="{{ route('admin.ip-pools.addresses.reserve-one', [$pool, $address]) }}">
                                            @csrf
                                            <button class="rounded-lg bg-[#0069FF] px-4 py-2 text-xs font-black text-white transition hover:bg-[#0050D0]">رزرو</button>
                                        </form>
                                    @elseif($address->status === 'assigned' && $vm)
                                        <div
                                            x-data="{
                                                syncProxmox: true,
                                                ipChange: '',
                                                submit() {
                                                    if (!this.ipChange) return;
                                                    if (!this.syncProxmox && !confirm('فقط رکوردهای دیتابیس به‌روزرسانی می‌شود. اعمال در Proxmox انجام نخواهد شد. ادامه می‌دهید?')) return;
                                                    this.$refs.form.submit();
                                                }
                                            }"
                                            class="flex min-w-72 flex-col gap-2"
                                        >
                                            <form x-ref="form" method="POST" action="{{ route('admin.virtual-machines.ip-address.update', $vm) }}" class="flex items-center gap-2" onsubmit="return false;">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="sync_to_proxmox" :value="syncProxmox ? '1' : '0'">
                                                <select name="ip_address_id" required x-model="ipChange" class="min-w-48 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                                                    <option value="">IP جدید</option>
                                                    @foreach($replacementAddresses->filter(fn ($candidate) => (int) $candidate->pool?->proxmox_server_id === (int) $vm->proxmox_server_id && (blank($candidate->pool?->node) || $candidate->pool?->node === $vm->node)) as $candidate)
                                                        <option value="{{ $candidate->id }}">{{ $candidate->address }} · {{ $candidate->pool?->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" @click="submit()" class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-black text-amber-800 transition hover:bg-amber-100">تغییر IP</button>
                                            </form>
                                            <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                                                <input type="checkbox" x-model="syncProxmox" class="size-3 rounded border-slate-300 text-[#0069FF]">
                                                اعمال در Proxmox
                                            </label>
                                        </div>
                                    @elseif($address->status === 'reserved' || ($address->status === 'assigned' && !$vm))
                                        <form method="POST" action="{{ route('admin.ip-pools.addresses.release', [$pool, $address]) }}" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این IP را آزاد کنید?');">
                                            @csrf
                                            <button class="rounded-lg bg-red-50 px-4 py-2 text-xs font-black text-red-700 transition hover:bg-red-100">
                                                {{ $address->status === 'assigned' && !$vm ? 'آزادسازی (بدون ماشین)' : 'آزادسازی' }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs font-black text-slate-400">قفل‌شده</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-8 text-center text-sm font-bold text-slate-500">برای این Pool هنوز IP ساخته نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
