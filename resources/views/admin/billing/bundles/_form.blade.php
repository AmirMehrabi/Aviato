@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="name" label="نام باندل" :value="$bundle->name" />
    <x-form.input name="slug" label="اسلاگ" :value="$bundle->slug" dir-ltr help="اختیاری؛ اگر خالی باشد خودکار ساخته می‌شود." />
    <x-form.input name="cpu_cores" type="number" label="CPU Core" :value="$bundle->cpu_cores" />
    <x-form.input name="ram_gb" type="number" label="RAM (GB)" :value="$bundle->ram_gb" />
    <x-form.input name="disk_gb" type="number" label="Disk (GB)" :value="$bundle->disk_gb" />
    <x-form.input name="ip_count" type="number" label="تعداد IP" :value="$bundle->ip_count ?? 1" />
    <x-form.input name="monthly_price" type="number" label="قیمت ماهانه باندل وقتی روشن است" :value="$bundle->monthly_price" />
    <x-form.input name="sort_order" type="number" label="ترتیب نمایش" :value="$bundle->sort_order ?? 0" />
    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <h2 class="font-black text-slate-900">حسابداری شبکه</h2>
        <p class="mt-1 text-xs text-slate-500">مبالغ به IRR و حجم‌ها به byte ذخیره می‌شوند. 1 TiB = 1099511627776 و 1 GiB = 1073741824 byte.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-4"><input type="checkbox" name="network_accounting_enabled" value="1" @checked(old('network_accounting_enabled', $bundle->network_accounting_enabled)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">فعال‌سازی هزینه شبکه</span></label>
            <x-form.input name="network_included_bytes_monthly" type="number" label="حجم رایگان ماهانه (byte)" :value="$bundle->network_included_bytes_monthly ?? 1099511627776" dir-ltr />
            <x-form.input name="network_overage_price" type="number" label="هزینه هر واحد (IRR)" :value="$bundle->network_overage_price ?? 9000" dir-ltr />
            <x-form.input name="network_overage_price_unit_bytes" type="number" label="اندازه واحد قیمت (byte)" :value="$bundle->network_overage_price_unit_bytes ?? 1073741824" dir-ltr />
            <label><span class="text-sm font-black text-slate-700">جهت ترافیک قابل پرداخت</span><select name="network_usage_direction" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3"><option value="both" @selected(old('network_usage_direction', $bundle->network_usage_direction) === 'both')>ورودی + خروجی</option><option value="egress" @selected(old('network_usage_direction', $bundle->network_usage_direction) === 'egress')>فقط خروجی</option><option value="ingress" @selected(old('network_usage_direction', $bundle->network_usage_direction) === 'ingress')>فقط ورودی</option></select></label>
            <x-form.input name="network_billing_timezone" label="منطقه زمانی دوره" :value="$bundle->network_billing_timezone ?? 'Asia/Tehran'" dir-ltr />
        </div>
    </div>
    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">توضیحات</span><textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">{{ old('description', $bundle->description) }}</textarea>@error('description') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror</label>
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bundle->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">فعال</span></label>
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="hidden" name="show_on_marketing" value="0"><input type="checkbox" name="show_on_marketing" value="1" @checked(old('show_on_marketing', $bundle->show_on_marketing ?? true)) class="size-4 rounded border-slate-300 text-[#0069FF]"><span class="text-sm font-black text-slate-700">نمایش در home / pricing / solutions</span></label>
</div>
<div class="mt-6 flex gap-3"><button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره</button><a href="{{ route('admin.billing.bundles.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a></div>
