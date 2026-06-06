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
