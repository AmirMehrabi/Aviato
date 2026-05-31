@csrf

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <x-form.input name="name" label="نام مشتری" :value="$customer->name" wrapper-class="block md:col-span-2" required />

            <x-form.input name="email" label="ایمیل" type="email" :value="$customer->email" dir-ltr />

            <x-form.input name="phone" label="موبایل" :value="$customer->phone" placeholder="+98912..." dir-ltr />

            <x-form.input
                name="password"
                label="رمز عبور"
                type="password"
                help="در ویرایش، خالی بگذارید تا تغییر نکند."
                dir-ltr
                :required="! $customer->exists"
            />

            <x-form.input
                name="password_confirmation"
                label="تکرار رمز عبور"
                type="password"
                dir-ltr
                :required="! $customer->exists"
            />

            <x-form.input
                name="suspension_reason"
                label="دلیل تعلیق"
                :value="$customer->suspension_reason"
                placeholder="اختیاری؛ فقط برای مشتری تعلیق شده نمایش داده می‌شود"
                wrapper-class="block md:col-span-2"
            />
        </div>
    </div>

    <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">وضعیت حساب</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">مشتری فعال می‌تواند وارد پنل شود؛ مشتری تعلیق شده برای عملیات مدیریتی نگه داشته می‌شود.</p>
            <div class="mt-5 space-y-3">
                <x-form.select
                    name="status"
                    label="وضعیت"
                    :options="['active' => 'فعال', 'suspended' => 'تعلیق شده']"
                    :selected="$customer->status ?: 'active'"
                    wrapper-class="block rounded-lg bg-slate-50 p-3 text-sm font-bold"
                    select-class="bg-white px-3 py-2"
                />

                <x-form.checkbox
                    name="email_verified"
                    label="ایمیل تایید شده"
                    :checked="(bool) $customer->email_verified_at"
                    wrapper-class="flex items-center justify-between gap-3 rounded-lg bg-[#EBF3FF] p-3 text-sm font-bold text-[#0069FF]"
                />
            </div>
        </div>

        <button class="w-full rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ذخیره مشتری</button>
        <a href="{{ route('admin.customers.index') }}" class="block rounded-lg border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">بازگشت</a>
    </aside>
</div>
