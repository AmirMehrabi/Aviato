@extends('customer.layout')

@section('title', $project->name)
@section('header_title', $project->name)
@section('header_subtitle', 'اعضا، نقش ها و ماشین های این پروژه')
@section('breadcrumbs')
    <a href="{{ route('customer.projects.index', [], false) }}" class="transition hover:text-[#0069FF]">پروژه ها</a>
    <span>/</span>
    <span class="truncate text-slate-700">{{ $project->name }}</span>
@endsection

@php
    $activeNav = 'projects';
    $canManageMembers = $activeMembership?->canManageMembers() ?? false;
@endphp

@section('content')
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

    <section class="grid gap-5 lg:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">Owner</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $project->owner?->name }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">Members</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $project->members->count() }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">نقش شما: {{ $activeMembership?->role }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black text-slate-500">ماشین های مجازی</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $project->virtualMachines->count() }}</p>
            <a href="{{ route('customer.servers.index', [], false) }}" class="mt-2 inline-flex text-sm font-black text-[#0069FF]">مشاهده ماشین ها</a>
        </article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">اعضا</h2>
            <div class="mt-4 space-y-3">
                @foreach($project->members as $member)
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-black text-slate-950">{{ $member->customer?->name }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($canManageMembers && $member->customer_id !== $project->owner_customer_id)
                                <form method="POST" action="{{ route('customer.projects.members.update', [$project, $member], false) }}" class="flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black">
                                        @foreach(['admin' => 'Admin', 'member' => 'Member', 'viewer' => 'Viewer', 'billing' => 'Billing'] as $role => $label)
                                            <option value="{{ $role }}" @selected($member->role === $role)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-black text-white">ذخیره</button>
                                </form>
                                <form method="POST" action="{{ route('customer.projects.members.destroy', [$project, $member], false) }}" onsubmit="return confirm('Remove this member?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-600">حذف</button>
                                </form>
                            @else
                                <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-600">{{ $member->role }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">دعوت عضو</h2>
            @if($canManageMembers)
                <form method="POST" action="{{ route('customer.projects.members.store', $project, false) }}" class="mt-4 space-y-4">
                    @csrf
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">ایمیل یا موبایل مشتری</span>
                        <input name="identifier" value="{{ old('identifier') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">نقش</span>
                        <select name="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                            <option value="admin">Admin</option>
                            <option value="member" selected>Member</option>
                            <option value="viewer">Viewer</option>
                            <option value="billing">Billing</option>
                        </select>
                    </label>
                    <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white">افزودن عضو</button>
                </form>
            @else
                <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">برای مدیریت اعضا باید Owner یا Admin پروژه باشید.</p>
            @endif
        </aside>
    </section>
@endsection
