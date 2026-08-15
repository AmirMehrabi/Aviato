@csrf
@php
    $selectedIpPoolId = old('ip_pool_id', $vm->reservedIpAddress?->ip_pool_id);
    $selectedIpAddressId = old('ip_address_id', $vm->ip_address_id);
@endphp
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="name" label="نام VM (Internal)" :value="$vm->name" dir-ltr />
    <x-form.input name="display_name" label="نام نمایشی (Display Name)" :value="$vm->display_name" dir-ltr />
    <x-form.input name="hostname" label="Hostname" :value="$vm->hostname" dir-ltr />
    <x-form.select name="customer_id" label="مشتری" :selected="$vm->customer_id" :options="$customers->prepend('انتخاب مشتری', '')" />
    <label>
        <span class="text-sm font-black text-slate-700">Project</span>
        <select name="project_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">Default Project مشتری</option>
            @foreach($projects as $project)
                <option value="{{ $project->id }}" @selected((string) old('project_id', $vm->project_id) === (string) $project->id)>
                    {{ $project->name }} - {{ $project->owner?->name }}
                </option>
            @endforeach
        </select>
        @error('project_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="text-sm font-black text-slate-700">Proxmox Server</span>
        <select name="proxmox_server_id" x-model="form.proxmox_server_id" @change="resetForServer()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">بدون اتصال فعلا</option>
            @foreach($servers as $server)
                <option value="{{ $server->id }}">{{ $server->name }}{{ $server->datacenter ? ' / '.$server->datacenter : '' }}</option>
            @endforeach
        </select>
        @error('proxmox_server_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="block md:col-span-2"><span class="text-sm font-black text-slate-700">باندل سخت‌افزاری</span><select name="vm_bundle_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none"><option value="">Custom منابع دستی</option>@foreach($bundles as $bundle)<option value="{{ $bundle->id }}" @selected((string) old('vm_bundle_id', $vm->vm_bundle_id) === (string) $bundle->id)>{{ $bundle->name }} - {{ $bundle->cpu_cores }} CPU / {{ $bundle->ram_gb }}GB RAM / {{ $bundle->disk_gb }}GB - {{ app(App\Services\WalletService::class)->format($bundle->monthly_price) }}</option>@endforeach</select>@error('vm_bundle_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror<p class="mt-1 text-xs text-slate-500">اگر باندل انتخاب شود CPU/RAM/Disk/IP از باندل برداشته می‌شود.</p></label>
    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 md:col-span-2">
        <p class="text-sm font-black text-slate-700">منابع فعلی از باندل خوانده می‌شود</p>
        <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
            <div class="rounded-lg bg-white p-3"><span class="block text-xs font-bold text-slate-500">CPU</span><span class="font-black" dir="ltr">{{ $vm->cpu_cores }} Core</span></div>
            <div class="rounded-lg bg-white p-3"><span class="block text-xs font-bold text-slate-500">RAM</span><span class="font-black" dir="ltr">{{ $vm->ram_gb }}GB</span></div>
            <div class="rounded-lg bg-white p-3"><span class="block text-xs font-bold text-slate-500">Disk</span><span class="font-black" dir="ltr">{{ $vm->disk_gb }}GB</span></div>
            <div class="rounded-lg bg-white p-3"><span class="block text-xs font-bold text-slate-500">IP Count</span><span class="font-black" dir="ltr">{{ $vm->ip_count ?? 1 }}</span></div>
        </div>
    </div>
    <x-form.checkbox name="tax_exempt" label="معاف از مالیات (Tax Exempt)" :checked="old('tax_exempt', $vm->tax_exempt)" help="اگر غیرفعال باشد، مشتری صورتحساب رسمی دریافت می‌کند و شامل مالیات می‌شود." wrapper-class="md:col-span-2" />
    <label>
        <span class="text-sm font-black text-slate-700">IP Pool</span>
        <select name="ip_pool_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">بدون تغییر IP Pool</option>
            @foreach($ipPools as $pool)
                <option value="{{ $pool->id }}" @selected((string) $selectedIpPoolId === (string) $pool->id)>
                    {{ $pool->name }} - {{ $pool->proxmoxServer?->name ?: 'Proxmox' }} - {{ $pool->node ?: 'all nodes' }} - {{ $pool->network_bridge }}
                </option>
            @endforeach
        </select>
        @error('ip_pool_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="text-sm font-black text-slate-700">IP Address</span>
        <select name="ip_address_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">بدون تغییر IP Address</option>
            @foreach($ipAddresses as $address)
                <option value="{{ $address->id }}" @selected((string) $selectedIpAddressId === (string) $address->id)>
                    {{ $address->address }} - {{ $address->pool?->name ?: 'مخزن' }} - {{ \App\Support\AdminUi::status($address->status) }}
                </option>
            @endforeach
        </select>
        @error('ip_address_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
        <p class="mt-1 text-xs text-slate-500">بعد از تغییر IP، ipconfig0 و nameserver در Cloud-init روی Proxmox بازسازی می‌شود.</p>
    </label>
    <x-form.checkbox name="sync_to_proxmox" label="اعمال تغییرات IP در Proxmox (Cloud-init)" :checked="old('sync_to_proxmox', true)" help="اگر غیرفعال باشد فقط رکوردهای دیتابیس به‌روزرسانی می‌شود و تغییرات به Proxmox ارسال نمی‌شود." wrapper-class="md:col-span-2" />
    <x-form.input name="vmid" type="number" label="Proxmox VMID" :value="$vm->vmid" />
    <label>
        <span class="text-sm font-black text-slate-700">Node</span>
        <select
            name="node"
            x-model="form.node"
            @change="syncStorageForNode()"
            @disabled($vm->exists)
            class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none disabled:bg-slate-100 disabled:text-slate-500"
        >
            <option value="">انتخاب Node</option>
            @if($vm->node)
                <option value="{{ $vm->node }}">{{ $vm->node }} (current)</option>
            @endif
            <template x-for="node in options.nodes" :key="node.name">
                <option :value="node.name" x-text="node.display || node.name"></option>
            </template>
        </select>
        @error('node') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
        @if($vm->exists)
            <p class="mt-1 text-xs text-slate-500">برای تغییر Node از پنل «انتقال Node» بالای همین صفحه استفاده کنید.</p>
        @endif
    </label>
    <label>
        <span class="text-sm font-black text-slate-700">Storage</span>
        <select name="storage" x-model="form.storage" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">انتخاب Storage</option>
            @if($vm->storage)
                <option value="{{ $vm->storage }}">{{ $vm->storage }} (current)</option>
            @endif
            <template x-for="storage in visibleStorages" :key="`${storage.node}-${storage.storage}`">
                <option :value="storage.storage" x-text="storage.display || storage.storage"></option>
            </template>
        </select>
        @error('storage') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="text-sm font-black text-slate-700">OS Template</span>
        <select name="os_template" x-model="form.os_template" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">انتخاب OS Template</option>
            @if($vm->os_template)
                <option value="{{ $vm->os_template }}">{{ $vm->os_template }} (current)</option>
            @endif
            <template x-for="template in visibleTemplates" :key="`${template.node}-${template.vmid}`">
                <option :value="template.name || `VMID ${template.vmid}`" x-text="template.display"></option>
            </template>
        </select>
        @error('os_template') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
</div>
@if($vm->exists)
<div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <h2 class="font-black text-slate-900">بازنویسی حسابداری شبکه</h2>
    <p class="mt-1 text-xs text-slate-500">مقدار خالی یعنی ارث‌بری از باندل. تغییرات روی دوره‌ای که قبلاً snapshot شده اثر بازگشتی ندارد.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label><span class="text-sm font-black text-slate-700">وضعیت</span><select name="network_accounting_enabled_override" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3"><option value="">ارث‌بری از باندل</option><option value="1" @selected(old('network_accounting_enabled_override', $vm->network_accounting_enabled_override) === true)>فعال</option><option value="0" @selected(old('network_accounting_enabled_override', $vm->network_accounting_enabled_override) === false)>غیرفعال</option></select></label>
        <x-form.input name="network_included_bytes_monthly_override" type="number" label="حجم رایگان (byte)" :value="$vm->network_included_bytes_monthly_override" dir-ltr />
        <x-form.input name="network_overage_price_override" type="number" label="هزینه واحد (IRR)" :value="$vm->network_overage_price_override" dir-ltr />
        <x-form.input name="network_overage_price_unit_bytes_override" type="number" label="اندازه واحد (byte)" :value="$vm->network_overage_price_unit_bytes_override" dir-ltr />
        <label><span class="text-sm font-black text-slate-700">جهت ترافیک</span><select name="network_usage_direction_override" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3"><option value="">ارث‌بری</option><option value="both" @selected(old('network_usage_direction_override', $vm->network_usage_direction_override) === 'both')>ورودی + خروجی</option><option value="egress" @selected(old('network_usage_direction_override', $vm->network_usage_direction_override) === 'egress')>خروجی</option><option value="ingress" @selected(old('network_usage_direction_override', $vm->network_usage_direction_override) === 'ingress')>ورودی</option></select></label>
        <x-form.input name="network_billing_timezone_override" label="منطقه زمانی" :value="$vm->network_billing_timezone_override" dir-ltr />
    </div>
</div>
@endif
<div class="mt-6 flex gap-3"><button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره VM</button><a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a></div>
