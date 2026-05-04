@csrf

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="text-sm font-black text-slate-700">نام مشتری</span>
                <input name="name" value="{{ old('name', $customer->name) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none" required>
                @error('name') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">ایمیل</span>
                <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none">
                @error('email') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">موبایل</span>
                <input name="phone" value="{{ old('phone', $customer->phone) }}" placeholder="+98912..." class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none">
                @error('phone') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">رمز عبور</span>
                <input name="password" type="password" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" @if(! $customer->exists) required @endif>
                <span class="mt-1 block text-xs text-slate-500">در ویرایش، خالی بگذارید تا تغییر نکند.</span>
                @error('password') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-black text-slate-700">تکرار رمز عبور</span>
                <input name="password_confirmation" type="password" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-left dir-ltr focus:border-[#105D52] focus:outline-none" @if(! $customer->exists) required @endif>
            </label>

            <label class="block md:col-span-2">
                <span class="text-sm font-black text-slate-700">دلیل تعلیق</span>
                <input name="suspension_reason" value="{{ old('suspension_reason', $customer->suspension_reason) }}" placeholder="اختیاری؛ فقط برای مشتری تعلیق شده نمایش داده می‌شود" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none">
                @error('suspension_reason') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">وضعیت حساب</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">مشتری فعال می‌تواند وارد پنل شود؛ مشتری تعلیق شده برای عملیات مدیریتی نگه داشته می‌شود.</p>
            <div class="mt-5 space-y-3">
                <label class="block rounded-lg bg-slate-50 p-3 text-sm font-bold">
                    <span>وضعیت</span>
                    <select name="status" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 focus:border-[#105D52] focus:outline-none">
                        <option value="active" @selected(old('status', $customer->status ?: 'active') === 'active')>فعال</option>
                        <option value="suspended" @selected(old('status', $customer->status) === 'suspended')>تعلیق شده</option>
                    </select>
                </label>
                <label class="flex items-center justify-between gap-3 rounded-lg bg-[#F1F7F5] p-3 text-sm font-bold text-[#105D52]">
                    <span>ایمیل تایید شده</span>
                    <input type="hidden" name="email_verified" value="0">
                    <input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', (bool) $customer->email_verified_at))>
                </label>
            </div>
        </div>

        <button class="w-full rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0D4C44]">ذخیره مشتری</button>
        <a href="{{ route('admin.customers.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت</a>
    </aside>
</div>
