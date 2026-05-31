@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.select name="proxmox_server_id" label="Proxmox Server" :selected="$pool->proxmox_server_id" :options="$servers->prepend('انتخاب سرور', '')" />
    <x-form.input name="name" label="نام Pool" :value="$pool->name" />
    <x-form.input name="node" label="Node" :value="$pool->node" dir-ltr help="اختیاری؛ خالی یعنی برای همه nodeهای این Proxmox." />
    <x-form.input name="network_bridge" label="Bridge" :value="$pool->network_bridge ?: 'vmbr0'" dir-ltr />
    <x-form.input name="gateway" label="Gateway" :value="$pool->gateway" dir-ltr />
    <x-form.input name="prefix_length" type="number" label="Prefix Length" :value="$pool->prefix_length ?: 24" />
    <x-form.input name="nameservers" label="Nameservers" :value="$pool->nameservers ?: '1.1.1.1'" dir-ltr help="با فاصله یا کاما جدا کنید؛ همان مقدار به Proxmox ارسال می‌شود." />
    <x-form.input name="start_ip" label="Start IP" :value="$pool->start_ip" dir-ltr />
    <x-form.input name="end_ip" label="End IP" :value="$pool->end_ip" dir-ltr help="اختیاری؛ برای یک IP خالی بماند." />
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pool->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">فعال</span></label>
</div>
<div class="mt-6 flex gap-3">
    <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره</button>
    <a href="{{ route('admin.ip-pools.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a>
</div>
