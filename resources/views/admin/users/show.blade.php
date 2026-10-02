@extends('layouts.admin')
@section('title', $managedUser->name)
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if(session('status'))<div class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div>
@adminRoute('admin.users.index')
<a href="{{ route('admin.users.index') }}" class="text-sm font-black text-[#0069FF]">بازگشت</a>
@endadminRoute
<h1 class="mt-3 text-2xl font-black">{{ $managedUser->name }}</h1><p class="mt-1 text-sm text-slate-500">{{ $managedUser->role->label() }} · {{ $managedUser->is_active ? 'فعال':'غیرفعال' }}</p></div>
@adminRoute('admin.users.edit')
<a href="{{ route('admin.users.edit',$managedUser) }}" class="rounded-xl bg-[#0069FF] px-5 py-3 text-center text-sm font-black text-white">ویرایش حساب</a>
@endadminRoute
</div>
    <div class="mt-6 grid gap-5 lg:grid-cols-3"><section class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2"><h2 class="font-black">اطلاعات حساب</h2><dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">ایمیل</dt><dd class="mt-1 font-bold" dir="ltr">{{ $managedUser->email ?: '—' }}</dd></div><div><dt class="text-slate-500">موبایل</dt><dd class="mt-1 font-bold" dir="ltr">{{ $managedUser->phone ?: '—' }}</dd></div><div><dt class="text-slate-500">آخرین ورود</dt><dd class="mt-1 font-bold">{{ $managedUser->last_login_at?->diffForHumans() ?? 'هرگز' }}</dd></div><div><dt class="text-slate-500">نشست فعال</dt><dd class="mt-1 font-bold">{{ $activeSessions }}</dd></div><div><dt class="text-slate-500">تیکت اختصاص‌یافته</dt><dd class="mt-1 font-bold">{{ $managedUser->assigned_tickets_count }}</dd></div><div><dt class="text-slate-500">تیم‌های پشتیبانی</dt><dd class="mt-1 font-bold">{{ $managedUser->support_teams_count }}</dd></div></dl></section>
    <aside class="space-y-5"><section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">نشست‌ها</h2>
@adminRoute('admin.users.sessions.destroy')
<form method="POST" action="{{ route('admin.users.sessions.destroy',$managedUser) }}" class="mt-4">@csrf @method('DELETE')<button class="w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-black text-amber-800">بستن همه نشست‌ها</button></form>
@endadminRoute
</section><section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">حذف حساب</h2><p class="mt-2 text-xs leading-6 text-slate-500">حذف فقط در صورت نداشتن سوابق وابسته ممکن است.</p>
@adminRoute('admin.users.destroy')
<form method="POST" action="{{ route('admin.users.destroy',$managedUser) }}" class="mt-4" onsubmit="return confirm('حساب حذف شود؟')">@csrf @method('DELETE')<button class="w-full rounded-xl bg-red-50 px-4 py-3 text-sm font-black text-red-700">حذف دائمی</button></form>
@endadminRoute
</section></aside></div>
    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">ثبت رمز عبور موقت</h2>
@adminRoute('admin.users.reset-password')
<form method="POST" action="{{ route('admin.users.reset-password',$managedUser) }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf<input name="password" type="password" required placeholder="رمز جدید" class="rounded-xl border border-slate-200 px-4 py-3" dir="ltr"><input name="password_confirmation" type="password" required placeholder="تکرار رمز" class="rounded-xl border border-slate-200 px-4 py-3" dir="ltr"><button class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white">ثبت و بستن نشست‌ها</button></form>
@endadminRoute
</section>
    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="flex items-center justify-between border-b p-5"><h2 class="font-black">فعالیت اخیر</h2>
@adminRoute('admin.audit.index')
<a href="{{ route('admin.audit.index',['user_id'=>$managedUser->id]) }}" class="text-sm font-black text-[#0069FF]">همه موارد</a>
@endadminRoute
</div><div class="divide-y">@forelse($recentAuditLogs as $log)
@adminRoute('admin.audit.show')
<a href="{{ route('admin.audit.show',$log) }}" class="flex justify-between gap-4 px-5 py-4 text-sm hover:bg-slate-50"><span><b dir="ltr">{{ $log->event }}</b><small class="mr-3 text-slate-400">{{ $log->result }}</small></span><time>{{ $log->created_at->diffForHumans() }}</time></a>
@endadminRoute
@empty<p class="p-8 text-center text-sm text-slate-500">فعالیتی ثبت نشده است.</p>@endforelse</div></section>
</div>
@if($managedUser->role === \App\Enums\AdminRole::Custom)
    <section class="mx-4 mb-6 rounded-xl border border-slate-200 bg-white p-5 md:mx-8">
        <h2 class="text-lg font-black">دسترسی‌های سفارشی</h2>
        <div class="mt-3 flex flex-wrap gap-2">@forelse(\App\Support\AdminAccess::permissions($managedUser) as $permission)<span class="rounded-lg bg-slate-100 px-3 py-2 text-sm">{{ \App\Enums\AdminAbility::from($permission)->group() }}: {{ \App\Enums\AdminAbility::from($permission)->label() }}</span>@empty<p class="text-sm text-slate-500">فقط پروفایل شخصی</p>@endforelse</div>
    </section>
@endif
@endsection
