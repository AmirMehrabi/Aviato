@csrf
@if(isset($managedUser)) @method('PUT') @endif
<div class="grid gap-5 md:grid-cols-2">
    <label class="block md:col-span-2"><span class="text-sm font-black text-slate-700">نام و نام خانوادگی</span><input name="name" required value="{{ old('name', ($managedUser ?? $user)->name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">ایمیل</span><input name="email" type="email" value="{{ old('email', ($managedUser ?? $user)->email) }}" dir="ltr" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">موبایل</span><input name="phone" value="{{ old('phone', ($managedUser ?? $user)->phone) }}" dir="ltr" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
    <label class="block"><span class="text-sm font-black text-slate-700">نقش ثابت</span><select name="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', ($managedUser ?? $user)->role?->value ?? ($managedUser ?? $user)->role) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', ($managedUser ?? $user)->is_active)) class="size-5 rounded border-slate-300 text-[#0069FF]"><span><b class="block text-sm">حساب فعال</b><small class="text-slate-500">حساب غیرفعال امکان ورود ندارد.</small></span></label>
    @unless(isset($managedUser))
        <label class="block"><span class="text-sm font-black text-slate-700">رمز عبور موقت</span><input name="password" required type="password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3" dir="ltr"></label>
        <label class="block"><span class="text-sm font-black text-slate-700">تکرار رمز عبور</span><input name="password_confirmation" required type="password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3" dir="ltr"></label>
    @endunless
</div>
<div class="mt-6 flex gap-3"><button class="rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white">ذخیره</button><a href="{{ isset($managedUser) ? route('admin.users.show', $managedUser) : route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-black text-slate-700">انصراف</a></div>
