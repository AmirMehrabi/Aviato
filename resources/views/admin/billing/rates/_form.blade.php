@csrf
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="resource" label="کد منبع" :value="$rate->resource" dir-ltr help="مثلا cpu_core یا disk_gb" />
    <x-form.input name="label" label="نام نمایشی" :value="$rate->label" />
    <x-form.input name="unit" label="واحد" :value="$rate->unit" />
    <x-form.input name="monthly_price" type="number" label="قیمت ماهانه واحد (تومان)" :value="$rate->monthly_price" />
    <x-form.select name="billing_policy" label="قانون محاسبه" :selected="$rate->billing_policy" :options="['running' => 'فقط وقتی VM روشن است', 'always' => 'همیشه حتی وقتی VM خاموش است']" />
    <label class="mt-8 flex items-center gap-3 rounded-lg border border-slate-200 p-4">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate->is_active ?? true)) class="size-4 rounded border-slate-300 text-[#105D52]">
        <span class="text-sm font-black text-slate-700">فعال</span>
    </label>
</div>
<div class="mt-6 flex gap-3">
    <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره</button>
    <a href="{{ route('admin.billing.rates.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بازگشت</a>
</div>
