@extends('layouts.admin')

@section('title', 'تیم‌های پشتیبانی')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">تیم‌های پشتیبانی</h1>
            <p class="mt-2 text-sm text-slate-500">مدیریت تیم‌ها و اعضای رسیدگی به تیکت‌ها.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <form method="POST" action="{{ route('admin.support-teams.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <h1 class="text-xl font-black text-slate-950">تیم جدید</h1>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">نام</span><input name="name" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"></label>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">توضیح</span><textarea name="description" rows="3" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"></textarea></label>
            <label class="mt-4 flex items-center gap-2 text-sm font-black text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> فعال</label>
            <label class="mt-4 block"><span class="text-sm font-black text-slate-700">اعضا</span><select name="users[]" multiple class="mt-2 h-40 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>@endforeach</select></label>
            <button class="mt-4 w-full rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ساخت تیم</button>
        </form>

        <section class="space-y-4">
            @forelse($teams as $team)
                <form method="POST" action="{{ route('admin.support-teams.update', $team) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf @method('PUT')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="block"><span class="text-sm font-black text-slate-700">نام</span><input name="name" value="{{ $team->name }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"></label>
                        <label class="block"><span class="text-sm font-black text-slate-700">وضعیت</span><span class="mt-2 flex h-11 items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($team->is_active) class="rounded border-slate-300"> فعال</span></label>
                    </div>
                    <label class="mt-4 block"><span class="text-sm font-black text-slate-700">توضیح</span><textarea name="description" rows="2" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold">{{ $team->description }}</textarea></label>
                    <label class="mt-4 block"><span class="text-sm font-black text-slate-700">اعضا</span><select name="users[]" multiple class="mt-2 h-32 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold">@foreach($users as $user)<option value="{{ $user->id }}" @selected($team->users->contains($user))>{{ $user->name }} - {{ $user->email }}</option>@endforeach</select></label>
                    <button class="mt-4 rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-black text-white">ذخیره</button>
                </form>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm font-bold text-slate-500">هنوز تیمی ساخته نشده است.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
