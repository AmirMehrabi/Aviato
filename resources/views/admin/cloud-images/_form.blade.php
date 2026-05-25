@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.select name="proxmox_server_id" label="Proxmox Server" :selected="$image->proxmox_server_id" :options="$servers->prepend('انتخاب سرور', '')" />
    <x-form.input name="name" label="نام Image" :value="$image->name" dir-ltr />
    <x-form.input name="slug" label="Slug" :value="$image->slug" dir-ltr help="اختیاری؛ اگر خالی باشد خودکار ساخته می‌شود." />
    <x-form.select name="os_family" label="OS Family" :selected="$image->os_family ?: 'ubuntu'" :options="$osFamilies" />
    <x-form.input name="os_version" label="OS Version" :value="$image->os_version" dir-ltr placeholder="24.04 LTS" />
    <x-form.select name="logo_key" label="Logo" :selected="$image->logo_key ?: $image->os_family ?: 'ubuntu'" :options="$logoKeys" />
    <x-form.input name="node" label="Node" :value="$image->node" dir-ltr />
    <x-form.input name="template_vmid" type="number" label="Template VMID" :value="$image->template_vmid" />
    <x-form.input name="default_username" label="Default Username" :value="$image->default_username ?: 'ubuntu'" dir-ltr />
    <x-form.input name="storage" label="Clone Storage" :value="$image->storage" dir-ltr help="اختیاری؛ اگر خالی باشد Proxmox پیش‌فرض template را استفاده می‌کند." />
    <x-form.input name="disk_device" label="Disk Device" :value="$image->disk_device ?: 'scsi0'" dir-ltr />
    <x-form.input name="network_bridge" label="Network Bridge" :value="$image->network_bridge ?: 'vmbr0'" dir-ltr />
    <x-form.input name="ostype" label="OS Type" :value="$image->ostype ?: 'l26'" dir-ltr />
    <x-form.input name="min_cpu_cores" type="number" label="Minimum CPU" :value="$image->min_cpu_cores ?: 1" />
    <x-form.input name="min_ram_gb" type="number" label="Minimum RAM (GB)" :value="$image->min_ram_gb ?: 1" />
    <x-form.input name="min_disk_gb" type="number" label="Minimum Disk (GB)" :value="$image->min_disk_gb ?: 10" />
    <x-form.input name="sort_order" type="number" label="ترتیب نمایش" :value="$image->sort_order ?? 0" />
    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">توضیحات</span><textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none">{{ old('description', $image->description) }}</textarea>@error('description') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror</label>
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $image->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#105D52]"><span class="text-sm font-black text-slate-700">فعال</span></label>
</div>
<div class="mt-6 flex gap-3">
    <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره</button>
    <a href="{{ route('admin.cloud-images.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a>
</div>
