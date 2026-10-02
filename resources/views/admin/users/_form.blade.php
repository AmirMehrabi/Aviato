@csrf
@if(isset($managedUser)) @method('PUT') @endif
@php
    $selectedRole = old('role', ($managedUser ?? $user)->role?->value);
    $selectedPermissions = session()->hasOldInput() ? old('permissions', []) : \App\Support\AdminAccess::permissions($managedUser ?? $user);
    $presets = collect(\App\Enums\AdminRole::cases())->mapWithKeys(fn ($role) => [$role->value => array_column(\App\Support\AdminAccess::abilities($role), 'value')]);
@endphp
<div x-data="{ role: @js($selectedRole), selected: @js($selectedPermissions), presets: @js($presets), preset: 'support' }">
<div class="grid gap-5 md:grid-cols-2">
    <label class="block md:col-span-2"><span class="text-sm font-black text-slate-700">نام و نام خانوادگی</span><input name="name" required value="{{ old('name', ($managedUser ?? $user)->name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">ایمیل</span><input name="email" type="email" value="{{ old('email', ($managedUser ?? $user)->email) }}" dir="ltr" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">موبایل</span><input name="phone" value="{{ old('phone', ($managedUser ?? $user)->phone) }}" dir="ltr" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">نوع دسترسی</span><select x-model="role" name="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', ($managedUser ?? $user)->role?->value ?? ($managedUser ?? $user)->role) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', ($managedUser ?? $user)->is_active)) class="size-5 rounded border-slate-300 text-[#0069FF]"><span><b class="block text-sm">حساب فعال</b><small class="text-slate-500">حساب غیرفعال امکان ورود ندارد.</small></span></label>
    @unless(isset($managedUser))
        <label class="block"><span class="text-sm font-black text-slate-700">رمز عبور موقت</span><input name="password" required type="password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3" dir="ltr"></label>
        <label class="block"><span class="text-sm font-black text-slate-700">تکرار رمز عبور</span><input name="password_confirmation" required type="password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3" dir="ltr"></label>
    @endunless
</div>
<section x-show="role === 'custom'" x-cloak class="mt-6 rounded-xl border border-slate-200 p-5">
    <h2 class="text-lg font-black">دسترسی ماژول‌ها</h2>
    <p class="mt-2 text-sm text-slate-500">دسترسی مشاهده لازم برای عملیات انتخاب‌شده به‌صورت خودکار اضافه می‌شود. مدیریت کاربران پنل فقط برای مدیر کامل است.</p>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <select x-model="preset" class="rounded-lg border border-slate-200 p-2">@foreach($roles as $value => $label)@if(! in_array($value, ['admin', 'custom']))<option value="{{ $value }}">{{ $label }}</option>@endif @endforeach</select>
        <button type="button" @click="selected = [...presets[preset]]" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">شروع از نقش آماده</button>
        <button type="button" @click="selected = []" class="text-sm text-slate-500">پاک کردن انتخاب‌ها</button>
    </div>
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach(collect(\App\Enums\AdminAbility::cases())->reject(fn ($ability) => $ability === \App\Enums\AdminAbility::UsersManage)->groupBy(fn ($ability) => $ability->group()) as $group => $abilities)
            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-2 text-sm font-black">{{ $group }}</legend>
                @foreach($abilities as $ability)
                    <label class="mb-3 flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $ability->value }}" x-model="selected" :disabled="role !== 'custom'" @checked(in_array($ability->value, $selectedPermissions, true)) class="rounded border-slate-300 text-[#0069FF]"><span>{{ $ability->label() }}</span></label>
                @endforeach
            </fieldset>
        @endforeach
    </div>
</section>
</div>
<div class="mt-6 flex gap-3"><button class="rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white">ذخیره</button>
@adminRoute('admin.users.show')
<a href="{{ isset($managedUser) ? route('admin.users.show', $managedUser) : route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-black text-slate-700">انصراف</a>
@endadminRoute
</div>
