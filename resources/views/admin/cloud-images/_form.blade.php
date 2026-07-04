@csrf
@inject('wallets', 'App\Services\WalletService')
@php
    $selectedBundleIds = collect(old('bundle_ids', $selectedBundleIds ?? []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $initialMappings = collect(old('node_mappings', $existingNodeMappings ?? []))->values();
@endphp
<div
    x-data="{
        serverId: @js((string) old('proxmox_server_id', $image->proxmox_server_id)),
        urls: @js($serverOptionUrls ?? []),
        loading: false,
        error: '',
        inventory: { nodes: [], os_templates: [], disk_storages: [], bridges: [] },
        mappings: @js($initialMappings),
        async loadInventory() {
            this.error = '';
            if (!this.serverId || !this.urls[this.serverId]) {
                this.inventory = { nodes: [], os_templates: [], disk_storages: [], bridges: [] };
                return;
            }
            this.loading = true;
            try {
                const response = await fetch(this.urls[this.serverId], { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.error || payload.message || 'Unable to fetch Proxmox inventory.');
                this.inventory = payload;
                this.mappings = (payload.nodes || []).map((node) => {
                    const existing = this.mappings.find((mapping) => mapping.node === node.name) || {};
                    const templates = (payload.os_templates || []).filter((item) => item.node === node.name);
                    const storages = (payload.disk_storages || []).filter((item) => item.node === node.name);
                    const bridges = (payload.bridges || []).filter((item) => item.node === node.name);
                    return {
                        node: node.name,
                        template_vmid: String(existing.template_vmid || templates[0]?.vmid || ''),
                        storage: existing.storage || storages[0]?.storage || '',
                        network_bridge: existing.network_bridge || bridges.find((item) => item.active)?.iface || bridges[0]?.iface || 'vmbr1',
                        template_version: existing.template_version || '',
                        is_enabled: existing.is_enabled === true || existing.is_enabled === 1 || existing.is_enabled === '1',
                    };
                });
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        templates(node) { return (this.inventory.os_templates || []).filter((item) => item.node === node); },
        storages(node) { return (this.inventory.disk_storages || []).filter((item) => item.node === node); },
        bridges(node) { return (this.inventory.bridges || []).filter((item) => item.node === node); },
    }"
    x-init="if (serverId) loadInventory()"
>
<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm font-black text-slate-700">Proxmox Cluster</span>
        <select name="proxmox_server_id" x-model="serverId" @change="loadInventory()" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
            <option value="">انتخاب کلاستر</option>
            @foreach($servers as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        @error('proxmox_server_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </label>
    <x-form.input name="name" label="نام Image" :value="$image->name" dir-ltr />
    <x-form.input name="slug" label="Slug" :value="$image->slug" dir-ltr help="اختیاری؛ اگر خالی باشد خودکار ساخته می‌شود." />
    <x-form.select name="os_family" label="OS Family" :selected="$image->os_family ?: 'ubuntu'" :options="$osFamilies" />
    <x-form.input name="os_version" label="OS Version" :value="$image->os_version" dir-ltr placeholder="24.04 LTS" />
    <x-form.select name="logo_key" label="Logo" :selected="$image->logo_key ?: $image->os_family ?: 'ubuntu'" :options="$logoKeys" />
    <x-form.input name="default_username" label="Default Username" :value="$image->default_username ?: 'ubuntu'" dir-ltr />
    <x-form.input name="disk_device" label="Disk Device" :value="$image->disk_device ?: 'scsi0'" dir-ltr />
    <x-form.input name="ostype" label="OS Type" :value="$image->ostype ?: 'l26'" dir-ltr />
    <x-form.input name="min_cpu_cores" type="number" label="Minimum CPU" :value="$image->min_cpu_cores ?: 1" />
    <x-form.input name="min_ram_gb" type="number" label="Minimum RAM (GB)" :value="$image->min_ram_gb ?: 1" />
    <x-form.input name="min_disk_gb" type="number" label="Minimum Disk (GB)" :value="$image->min_disk_gb ?: 10" />
    <x-form.input name="sort_order" type="number" label="ترتیب نمایش" :value="$image->sort_order ?? 0" />
    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">توضیحات</span><textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">{{ old('description', $image->description) }}</textarea>@error('description') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror</label>
    <input type="hidden" name="cloud_init_enabled" value="0">
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="cloud_init_enabled" value="1" @checked(old('cloud_init_enabled', $image->cloud_init_enabled ?? true)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">CloudInit فعال است</span></label>
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $image->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">فعال</span></label>
</div>

<section class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="font-black text-slate-950">Node template mappings</h2>
            <p class="mt-1 text-sm text-slate-500">برای هر node، template، storage و bridge همان node را انتخاب کنید.</p>
        </div>
        <button type="button" @click="loadInventory()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-black" :disabled="loading">
            <span x-text="loading ? 'در حال دریافت…' : 'بروزرسانی'"></span>
        </button>
    </div>
    <p x-show="error" x-text="error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700"></p>
    @error('node_mappings') <p class="mt-3 text-sm font-bold text-red-600">{{ $message }}</p> @enderror

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <template x-for="(mapping, index) in mappings" :key="mapping.node">
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-black" dir="ltr" x-text="mapping.node"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="inventory.nodes.find((item) => item.name === mapping.node)?.display || ''"></p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="hidden" :name="`node_mappings[${index}][is_enabled]`" value="0">
                        <input type="checkbox" :name="`node_mappings[${index}][is_enabled]`" value="1" x-model="mapping.is_enabled" class="size-4 rounded border-slate-300 text-[#0069FF]">
                        فعال
                    </label>
                </div>
                <input type="hidden" :name="`node_mappings[${index}][node]`" :value="mapping.node">
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-bold text-slate-700">Template
                        <select :name="`node_mappings[${index}][template_vmid]`" x-model="mapping.template_vmid" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2" :required="mapping.is_enabled">
                            <option value="">انتخاب template</option>
                            <template x-for="template in templates(mapping.node)" :key="template.vmid">
                                <option :value="String(template.vmid)" x-text="`${template.name || 'template'} / VMID ${template.vmid}`"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">Storage
                        <select :name="`node_mappings[${index}][storage]`" x-model="mapping.storage" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2" :required="mapping.is_enabled">
                            <option value="">انتخاب storage</option>
                            <template x-for="storage in storages(mapping.node)" :key="storage.storage">
                                <option :value="storage.storage" x-text="storage.display"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">Network bridge
                        <select :name="`node_mappings[${index}][network_bridge]`" x-model="mapping.network_bridge" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2" :required="mapping.is_enabled">
                            <option value="">انتخاب bridge</option>
                            <template x-for="bridge in bridges(mapping.node)" :key="bridge.iface">
                                <option :value="bridge.iface" x-text="bridge.display"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm font-bold text-slate-700">Template version
                        <input :name="`node_mappings[${index}][template_version]`" x-model="mapping.template_version" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-left" dir="ltr" placeholder="24.04-v1">
                    </label>
                </div>
            </article>
        </template>
    </div>
</section>

<div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h2 class="text-base font-black text-slate-950">Whitelist پلن‌ها</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">فقط پلن‌های انتخاب‌شده در صفحه ساخت ماشین مجازی نمایش داده می‌شوند. اگر این Cloud Image فعال باشد، باید حداقل یک پلن انتخاب شود.</p>
        </div>
        @error('bundle_ids') <span class="text-xs font-bold text-red-600">{{ $message }}</span> @enderror
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($bundles as $bundle)
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 transition hover:border-[#0069FF]/40 hover:bg-[#F8FBFF]">
                <input
                    type="checkbox"
                    name="bundle_ids[]"
                    value="{{ $bundle->id }}"
                    @checked(in_array((string) $bundle->id, $selectedBundleIds, true))
                    class="mt-1 size-4 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]"
                >
                <span class="min-w-0">
                    <span class="block font-black text-slate-950">{{ $bundle->name }}</span>
                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $bundle->cpu_cores }} CPU / {{ $bundle->ram_gb }}GB RAM / {{ $bundle->disk_gb }}GB Disk - {{ $wallets->format($bundle->monthly_price) }} / ماه</span>
                </span>
            </label>
        @endforeach
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره</button>
    <a href="{{ route('admin.cloud-images.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a>
</div>
</div>
