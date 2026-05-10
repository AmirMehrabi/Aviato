@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'Create Proxmox VM')

@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="vmWizard({
        optionsUrlTemplate: @js(route('admin.virtual-machines.options', ['proxmoxServer' => '__SERVER__'])),
        bundles: @js($bundles->map(fn ($bundle) => [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'cpu_cores' => $bundle->cpu_cores,
            'ram_gb' => $bundle->ram_gb,
            'disk_gb' => $bundle->disk_gb,
            'ip_count' => $bundle->ip_count,
            'price' => $money->format($bundle->monthly_price),
        ])->values()),
    })"
>
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#0A3D37] p-6 text-white shadow-xl shadow-[#0A3D37]/15">
        <div class="absolute -left-20 -top-20 size-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-bold text-emerald-50/60">Proxmox Provisioning Wizard</p>
                <h1 class="mt-2 text-3xl font-black">ساخت VM واقعی روی Proxmox</h1>
                <p class="mt-3 max-w-3xl leading-8 text-emerald-50/75">ابتدا Region/Server را انتخاب کنید؛ ISO، Storage، Node و Network از API همان Proxmox خوانده می‌شود.</p>
            </div>
            <a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg bg-white px-5 py-3 text-sm font-black text-[#0A3D37]">بازگشت</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <template x-for="item in steps" :key="item.id">
                <button type="button" class="mb-2 flex w-full items-center gap-3 rounded-xl px-4 py-3 text-right text-sm font-black transition" :class="step === item.id ? 'bg-[#F1F7F5] text-[#105D52]' : 'text-slate-500 hover:bg-slate-50'" @click="go(item.id)">
                    <span class="grid size-8 place-items-center rounded-lg" :class="step === item.id ? 'bg-[#105D52] text-white' : 'bg-slate-100 text-slate-500'" x-text="item.id"></span>
                    <span x-text="item.label"></span>
                </button>
            </template>

            <div class="mt-5 rounded-xl border border-dashed border-slate-300 p-4 text-xs leading-6 text-slate-500">
                <p class="font-black text-slate-700">نیازمندی API Token</p>
                <p class="mt-1">برای ساخت VM، توکن باید دسترسی VM.Allocate، VM.Config.*, Datastore.AllocateSpace و Datastore.Audit روی Node/Storage داشته باشد.</p>
            </div>
        </aside>

        <form method="POST" action="{{ route('admin.virtual-machines.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            <section x-show="step === 1" x-cloak>
                <h2 class="text-2xl font-black">۱. انتخاب مشتری و Region</h2>
                <p class="mt-2 text-sm text-slate-500">Region همان Proxmox server / datacenter شماست.</p>
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <x-form.select name="customer_id" label="مشتری" :selected="$selectedCustomerId" :options="$customers->prepend('انتخاب مشتری', '')" x-model="form.customer_id" />
                    <label>
                        <span class="text-sm font-black text-slate-700">Region / Proxmox Server</span>
                        <select name="proxmox_server_id" x-model="form.proxmox_server_id" @change="loadOptions()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none">
                            <option value="">انتخاب Region</option>
                            @foreach($servers as $server)
                                <option value="{{ $server->id }}">{{ $server->datacenter ?: 'بدون دیتاسنتر' }} / {{ $server->name }} / {{ $server->host }}</option>
                            @endforeach
                        </select>
                        @error('proxmox_server_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600" x-show="loading">در حال دریافت گزینه‌ها از Proxmox API...</div>
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" x-show="error" x-text="error"></div>
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-6 text-amber-800" x-show="Object.keys(apiErrors).length">
                    <p class="font-black">برخی endpointها خطا دادند:</p>
                    <template x-for="(message, endpoint) in apiErrors" :key="endpoint"><p dir="ltr"><span x-text="endpoint"></span>: <span x-text="message"></span></p></template>
                </div>
            </section>

            <section x-show="step === 2" x-cloak>
                <h2 class="text-2xl font-black">۲. انتخاب Node، ISO و Storage</h2>
                <p class="mt-2 text-sm text-slate-500">این لیست‌ها مستقیما از Proxmox خوانده شده‌اند.</p>
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <label><span class="text-sm font-black text-slate-700">Node</span><select name="node" x-model="form.node" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">انتخاب Node</option><template x-for="node in filteredNodes" :key="node.name"><option :value="node.name" x-text="node.display"></option></template></select></label>
                    <label><span class="text-sm font-black text-slate-700">VMID</span><input name="vmid" type="number" x-model="form.vmid" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"></label>
                    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">ISO File</span><select name="iso_volume" x-model="form.iso_volume" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">انتخاب ISO</option><template x-for="iso in filteredIsos" :key="iso.node + iso.volume"><option :value="iso.volume" x-text="iso.display"></option></template></select><p class="mt-1 text-xs text-slate-500" x-show="!filteredIsos.length && form.node">برای این Node هیچ ISO قابل استفاده‌ای پیدا نشد.</p></label>
                    <label><span class="text-sm font-black text-slate-700">Disk Storage</span><select name="storage" x-model="form.storage" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">انتخاب Storage</option><template x-for="storage in filteredStorages" :key="storage.node + storage.storage"><option :value="storage.storage" x-text="storage.display"></option></template></select></label>
                    <label><span class="text-sm font-black text-slate-700">Network Bridge</span><select name="network_bridge" x-model="form.network_bridge" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">انتخاب Bridge</option><template x-for="bridge in filteredBridges" :key="bridge.node + bridge.iface"><option :value="bridge.iface" x-text="bridge.display"></option></template></select></label>
                </div>
            </section>

            <section x-show="step === 3" x-cloak>
                <h2 class="text-2xl font-black">۳. منابع و مشخصات VM</h2>
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <x-form.input name="name" label="نام VM در Proxmox" value="" dir-ltr x-model="form.name" />
                    <x-form.input name="hostname" label="Hostname" value="" dir-ltr x-model="form.hostname" />
                    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">باندل سخت‌افزاری</span><select name="vm_bundle_id" x-model="form.vm_bundle_id" @change="applyBundle()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">Custom منابع دستی</option><template x-for="bundle in bundles" :key="bundle.id"><option :value="bundle.id" x-text="`${bundle.name} - ${bundle.cpu_cores} CPU / ${bundle.ram_gb}GB RAM / ${bundle.disk_gb}GB - ${bundle.price}`"></option></template></select></label>
                    <x-form.input name="cpu_cores" type="number" label="CPU Core" value="2" x-model="form.cpu_cores" />
                    <x-form.input name="ram_gb" type="number" label="RAM (GB)" value="4" x-model="form.ram_gb" />
                    <x-form.input name="disk_gb" type="number" label="Disk (GB)" value="50" x-model="form.disk_gb" />
                    <x-form.input name="ip_count" type="number" label="تعداد IP برای Billing" value="1" x-model="form.ip_count" />
                    <x-form.input name="ip_address" label="IP Address اختیاری" value="" dir-ltr x-model="form.ip_address" />
                    <x-form.select name="ostype" label="OS Type" selected="l26" :options="['l26' => 'Linux 2.6/3.x/4.x/5.x/6.x', 'win11' => 'Windows 11', 'win10' => 'Windows 10', 'w2k22' => 'Windows Server 2022', 'w2k19' => 'Windows Server 2019', 'other' => 'Other']" x-model="form.ostype" />
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="start_after_create" value="1" x-model="form.start_after_create" class="size-4 rounded border-slate-300 text-[#105D52]"><span class="text-sm font-black text-slate-700">بعد از ساخت روشن شود</span></label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="onboot" value="1" x-model="form.onboot" class="size-4 rounded border-slate-300 text-[#105D52]"><span class="text-sm font-black text-slate-700">Start on boot</span></label>
                </div>
                <input type="hidden" name="os_template" :value="form.iso_volume">
            </section>

            <section x-show="step === 4" x-cloak>
                <h2 class="text-2xl font-black">۴. بازبینی و ساخت</h2>
                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    <template x-for="row in reviewRows" :key="row.label"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500" x-text="row.label"></p><p class="mt-2 font-black text-slate-950" dir="ltr" x-text="row.value || '—'"></p></div></template>
                </div>
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-900">با زدن دکمه ساخت، VM از طریق Proxmox API ساخته می‌شود. اگر ساخت remote شکست بخورد، رکورد local با وضعیت failed ذخیره می‌شود.</div>
            </section>

            <div class="mt-8 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
                <button type="button" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700" @click="back()" x-show="step > 1">مرحله قبل</button>
                <span x-show="step === 1"></span>
                <button type="button" class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white" @click="next()" x-show="step < 4">مرحله بعد</button>
                <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white" x-show="step === 4">ساخت VM روی Proxmox</button>
            </div>
        </form>
    </div>
</div>

<script>
function vmWizard(config) {
    return {
        step: 1,
        steps: [
            { id: 1, label: 'Region' },
            { id: 2, label: 'ISO و زیرساخت' },
            { id: 3, label: 'منابع' },
            { id: 4, label: 'بازبینی' },
        ],
        bundles: config.bundles,
        loading: false,
        error: '',
        apiErrors: {},
        inventory: { nodes: [], iso_files: [], disk_storages: [], bridges: [] },
        form: {
            customer_id: @js((string) ($selectedCustomerId ?? '')),
            proxmox_server_id: '',
            node: '',
            vmid: '',
            iso_volume: '',
            storage: '',
            network_bridge: '',
            name: '',
            hostname: '',
            vm_bundle_id: '',
            cpu_cores: 2,
            ram_gb: 4,
            disk_gb: 50,
            ip_count: 1,
            ip_address: '',
            ostype: 'l26',
            start_after_create: false,
            onboot: false,
        },
        get filteredNodes() { return this.inventory.nodes || []; },
        get filteredIsos() { return (this.inventory.iso_files || []).filter((item) => !this.form.node || item.node === this.form.node); },
        get filteredStorages() { return (this.inventory.disk_storages || []).filter((item) => !this.form.node || item.node === this.form.node); },
        get filteredBridges() { return (this.inventory.bridges || []).filter((item) => !this.form.node || item.node === this.form.node); },
        get reviewRows() {
            return [
                { label: 'Customer ID', value: this.form.customer_id },
                { label: 'Proxmox Server', value: this.form.proxmox_server_id },
                { label: 'Node', value: this.form.node },
                { label: 'VMID', value: this.form.vmid },
                { label: 'Name', value: this.form.name },
                { label: 'ISO', value: this.form.iso_volume },
                { label: 'Storage', value: this.form.storage },
                { label: 'Bridge', value: this.form.network_bridge },
                { label: 'Resources', value: `${this.form.cpu_cores} CPU / ${this.form.ram_gb}GB RAM / ${this.form.disk_gb}GB Disk` },
                { label: 'Start after create', value: this.form.start_after_create ? 'yes' : 'no' },
            ];
        },
        go(target) { if (target <= this.step) this.step = target; },
        next() { if (this.step < 4) this.step++; },
        back() { if (this.step > 1) this.step--; },
        applyBundle() {
            const bundle = this.bundles.find((item) => String(item.id) === String(this.form.vm_bundle_id));
            if (!bundle) return;
            this.form.cpu_cores = bundle.cpu_cores;
            this.form.ram_gb = bundle.ram_gb;
            this.form.disk_gb = bundle.disk_gb;
            this.form.ip_count = bundle.ip_count;
        },
        async loadOptions() {
            this.error = '';
            this.apiErrors = {};
            this.inventory = { nodes: [], iso_files: [], disk_storages: [], bridges: [] };
            if (!this.form.proxmox_server_id) return;
            this.loading = true;
            try {
                const response = await fetch(config.optionsUrlTemplate.replace('__SERVER__', this.form.proxmox_server_id), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || data.message || 'Unable to fetch Proxmox options.');
                this.inventory = data;
                this.apiErrors = data.errors || {};
                this.form.vmid = data.next_vmid || '';
                this.form.node = data.nodes?.[0]?.name || '';
                this.form.iso_volume = this.filteredIsos?.[0]?.volume || '';
                this.form.storage = this.filteredStorages?.[0]?.storage || '';
                this.form.network_bridge = this.filteredBridges?.[0]?.iface || 'vmbr0';
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endsection
