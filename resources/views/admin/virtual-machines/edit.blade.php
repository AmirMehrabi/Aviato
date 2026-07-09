@extends('layouts.admin')
@section('title', 'ویرایش VM')
@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="vmEditOptions({
        optionsUrlTemplate: @js(route('admin.virtual-machines.options', ['proxmoxServer' => '__SERVER__'], false)),
        initialServerId: @js((string) old('proxmox_server_id', $vm->proxmox_server_id)),
        initialNode: @js(old('node', $vm->node)),
        initialStorage: @js(old('storage', $vm->storage)),
        initialOsTemplate: @js(old('os_template', $vm->os_template)),
    })"
    x-init="init()"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ session('error') }}</div>
    @endif

    @if($vm->isProxmox() && ! $vm->isActionLocked() && $vm->proxmoxServer && $vm->node && $vm->vmid)
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900">انتقال Node</h2>
                    <p class="mt-1 text-sm text-slate-500">این عملیات در صف اجرا می‌شود و پس از پایان، اعلان مدیریتی دریافت می‌کنید.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-500" dir="ltr">Current: {{ $vm->node }} / {{ $vm->vmid }}</span>
            </div>
            <form method="POST" action="{{ route('admin.virtual-machines.move-node') }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] lg:items-center">
                @csrf
                <input type="hidden" name="vm_ids[]" value="{{ $vm->id }}">
                <select name="target_node" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                    <option value="">انتخاب مقصد</option>
                    @foreach($nodeMoveOptions as $node => $label)
                        <option value="{{ $node }}" @disabled((string) $node === (string) $vm->node)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="mode" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                    <option value="reconcile_only">فقط تطبیق دیتابیس اگر VM روی مقصد وجود دارد</option>
                    <option value="migrate">Migration در Proxmox و سپس بروزرسانی دیتابیس</option>
                </select>
                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700">
                    <input type="checkbox" name="online" value="1" class="rounded border-slate-300 text-[#0069FF]">
                    Online
                </label>
                <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ثبت در صف</button>
            </form>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="mb-6 text-2xl font-black">ویرایش VM</h1>
        <form method="POST" action="{{ route('admin.virtual-machines.update', $vm) }}">
            @method('PUT')
            @include('admin.virtual-machines._form')
        </form>
    </div>
</div>

<script>
function vmEditOptions(config) {
    return {
        optionsUrlTemplate: config.optionsUrlTemplate,
        options: {
            nodes: [],
            disk_storages: [],
            os_templates: [],
        },
        form: {
            proxmox_server_id: config.initialServerId || '',
            node: config.initialNode || '',
            storage: config.initialStorage || '',
            os_template: config.initialOsTemplate || '',
        },
        init() {
            this.loadOptions();
        },
        get visibleStorages() {
            if (!this.form.node) return this.options.disk_storages;

            return this.options.disk_storages.filter((storage) => String(storage.node) === String(this.form.node));
        },
        get visibleTemplates() {
            if (!this.form.node) return this.options.os_templates;

            return this.options.os_templates.filter((template) => String(template.node) === String(this.form.node));
        },
        async loadOptions() {
            if (!this.form.proxmox_server_id) {
                this.options = { nodes: [], disk_storages: [], os_templates: [] };
                return;
            }

            try {
                const response = await fetch(this.optionsUrlTemplate.replace('__SERVER__', encodeURIComponent(this.form.proxmox_server_id)), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('options failed');
                const payload = await response.json();
                this.options = {
                    nodes: payload.nodes || [],
                    disk_storages: payload.disk_storages || [],
                    os_templates: payload.os_templates || [],
                };

                if (!this.form.node && this.options.nodes.length) {
                    this.form.node = this.options.nodes[0].name;
                }

                if (!this.form.storage && this.visibleStorages.length) {
                    this.form.storage = this.visibleStorages[0].storage;
                }

                if (!this.form.os_template && this.visibleTemplates.length) {
                    this.form.os_template = this.visibleTemplates[0].name || `VMID ${this.visibleTemplates[0].vmid}`;
                }
            } catch (error) {
                this.options = { nodes: [], disk_storages: [], os_templates: [] };
            }
        },
        resetForServer() {
            this.form.node = '';
            this.form.storage = '';
            this.form.os_template = '';
            this.loadOptions();
        },
        syncStorageForNode() {
            if (!this.visibleStorages.find((storage) => String(storage.storage) === String(this.form.storage))) {
                this.form.storage = this.visibleStorages[0]?.storage || '';
            }

            if (!this.visibleTemplates.find((template) => String(template.name || `VMID ${template.vmid}`) === String(this.form.os_template))) {
                const next = this.visibleTemplates[0];
                this.form.os_template = next ? (next.name || `VMID ${next.vmid}`) : this.form.os_template;
            }
        },
    };
}
</script>
@endsection
