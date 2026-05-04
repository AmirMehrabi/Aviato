@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="name" label="نام باندل" :value="$bundle->name" />
    <x-form.input name="slug" label="اسلاگ" :value="$bundle->slug" dir-ltr help="اختیاری؛ اگر خالی باشد خودکار ساخته می‌شود." />
    <x-form.input name="cpu_cores" type="number" label="CPU Core" :value="$bundle->cpu_cores" />
    <x-form.input name="ram_gb" type="number" label="RAM (GB)" :value="$bundle->ram_gb" />
    <x-form.input name="disk_gb" type="number" label="Disk (GB)" :value="$bundle->disk_gb" />
    <x-form.input name="ip_count" type="number" label="تعداد IP" :value="$bundle->ip_count ?? 1" />
    <x-form.input name="monthly_price" type="number" label="قیمت ماهانه باندل وقتی روشن است (تومان)" :value="$bundle->monthly_price" />
    <x-form.input name="sort_order" type="number" label="ترتیب نمایش" :value="$bundle->sort_order ?? 0" />
    <label class="md:col-span-2"><span class="text-sm font-black text-slate-700">توضیحات</span><textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none">{{ old('description', $bundle->description) }}</textarea>@error('description') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror</label>
    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bundle->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#105D52]"><span class="text-sm font-black text-slate-700">فعال</span></label>
</div>
<div class="mt-6 flex gap-3"><button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره</button><a href="{{ route('admin.billing.bundles.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a></div>
