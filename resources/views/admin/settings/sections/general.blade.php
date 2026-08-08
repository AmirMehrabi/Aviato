<div class="space-y-7">
    <section>
        <h2 class="text-base font-black text-slate-950">واحد پول</h2>
        <p class="mt-1 text-xs leading-6 text-slate-500">این واحد در قیمت‌ها، کیف پول و اسناد مالی استفاده می‌شود.</p>
        <div class="mt-4 max-w-xl">
            <x-form.select name="currency" label="واحد پولی" :selected="$currency" :options="$currencies" />
        </div>
    </section>

    <section class="border-t border-slate-200 pt-7">
        <h2 class="text-base font-black text-slate-950">مشخصات صادرکننده اسناد مالی</h2>
        <p class="mt-1 text-xs leading-6 text-slate-500">این اطلاعات در بالای رسیدهای پرداخت و اسناد مالی مشتری نمایش داده می‌شود.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <x-form.input name="company_name" label="نام شرکت یا مجموعه" :value="$companyProfile['name']" />
            <x-form.input name="company_phone" label="شماره تماس" :value="$companyProfile['phone']" dir-ltr />
            <x-form.input name="company_email" type="email" label="ایمیل" :value="$companyProfile['email']" dir-ltr />
            <x-form.input name="company_postal_code" label="کد پستی" :value="$companyProfile['postal_code']" dir-ltr />
            <x-form.input name="company_national_id" label="شناسه ملی" :value="$companyProfile['national_id']" dir-ltr />
            <x-form.input name="company_registration_number" label="شماره ثبت" :value="$companyProfile['registration_number']" dir-ltr />
            <x-form.input name="company_economic_code" label="کد اقتصادی" :value="$companyProfile['economic_code']" dir-ltr />

            <label class="block">
                <span class="text-sm font-black text-slate-700">نشانی</span>
                <textarea name="company_address" rows="3" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:ring-4 focus:ring-[#0069FF]/10">{{ old('company_address', $companyProfile['address']) }}</textarea>
                @error('company_address')<span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">لوگوی شرکت</span>
                <span class="mt-2 flex min-h-28 items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <img src="{{ $companyProfile['logo_url'] }}" alt="لوگوی فعلی شرکت" class="h-14 w-32 object-contain object-right">
                    <span class="min-w-0 flex-1">
                        <input type="file" name="company_logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="block w-full text-xs font-bold text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-[#EAF3FF] file:px-3 file:py-2 file:font-black file:text-[#0069FF]">
                        <span class="mt-2 block text-xs leading-6 text-slate-500">PNG، JPG، WebP یا SVG تا ۲ مگابایت. اگر فایلی انتخاب نشود، لوگوی فعلی حفظ می‌شود.</span>
                    </span>
                </span>
                @error('company_logo')<span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
    </section>
</div>
