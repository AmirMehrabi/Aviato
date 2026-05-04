@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="name" label="نام VM" :value="$vm->name" dir-ltr />
    <x-form.input name="hostname" label="Hostname" :value="$vm->hostname" dir-ltr />
    <x-form.select name="customer_id" label="مشتری" :selected="$vm->customer_id" :options="$customers->prepend('انتخاب مشتری', '')" />
    <x-form.select name="proxmox_server_id" label="Proxmox Server" :selected="$vm->proxmox_server_id" :options="$servers->prepend('بدون اتصال فعلا', '')" />
    <label class="block md:col-span-2"><span class="text-sm font-black text-slate-700">باندل سخت‌افزاری</span><select name="vm_bundle_id" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none"><option value="">Custom منابع دستی</option>@foreach($bundles as $bundle)<option value="{{ $bundle->id }}" @selected((string) old('vm_bundle_id', $vm->vm_bundle_id) === (string) $bundle->id)>{{ $bundle->name }} - {{ $bundle->cpu_cores }} CPU / {{ $bundle->ram_gb }}GB RAM / {{ $bundle->disk_gb }}GB - {{ number_format($bundle->monthly_price) }} تومان</option>@endforeach</select>@error('vm_bundle_id') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror<p class="mt-1 text-xs text-slate-500">اگر باندل انتخاب شود CPU/RAM/Disk/IP از باندل برداشته می‌شود.</p></label>
    <x-form.input name="cpu_cores" type="number" label="CPU Core" :value="$vm->cpu_cores" />
    <x-form.input name="ram_gb" type="number" label="RAM (GB)" :value="$vm->ram_gb" />
    <x-form.input name="disk_gb" type="number" label="Disk (GB)" :value="$vm->disk_gb" />
    <x-form.input name="ip_count" type="number" label="تعداد IP" :value="$vm->ip_count ?? 1" />
    <x-form.input name="ip_address" label="IP Address" :value="$vm->ip_address" dir-ltr />
    <x-form.input name="vmid" type="number" label="Proxmox VMID" :value="$vm->vmid" />
    <x-form.input name="node" label="Node" :value="$vm->node" dir-ltr />
    <x-form.input name="storage" label="Storage" :value="$vm->storage" dir-ltr />
    <x-form.input name="os_template" label="OS Template" :value="$vm->os_template" dir-ltr />
    <x-form.select name="status" label="وضعیت" :selected="$vm->status ?? 'stopped'" :options="['stopped' => 'خاموش', 'running' => 'روشن', 'suspended' => 'تعلیق شده']" />
</div>
<div class="mt-6 flex gap-3"><button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره VM</button><a href="{{ route('admin.virtual-machines.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a></div>
