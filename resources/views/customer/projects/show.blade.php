@extends('customer.layout')

@section('title', $project->name)
@section('header_title', $project->name)
@section('header_subtitle', 'نمای کلی فضای کاری، اعضا، دسترسی ماشین‌ها و تنظیمات')
@section('breadcrumbs')
    <a href="{{ route('customer.projects.index', [], false) }}" class="transition hover:text-[#0069FF]">فضاهای کاری</a>
    <span>/</span>
    <span class="truncate text-slate-700">{{ $project->name }}</span>
@endsection

@php
    $activeNav = 'projects';
    $canManageMembers = $activeMembership?->canManageMembers() ?? false;
    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
    $roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
@endphp

@section('content')
    <section class="grid gap-5 lg:grid-cols-4">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">مالک</p>
            <p class="mt-2 truncate text-xl font-black text-slate-950">{{ $project->owner?->name }}</p>
            <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">نقش شما</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $roleLabels[$activeMembership?->role ?? 'member'] ?? 'عضو' }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">{{ $canManageMembers ? 'امکان مدیریت اعضا دارید' : 'دسترسی شما محدود است' }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">اعضا</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->members->count()) }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">افراد دارای دسترسی</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">ماشین‌ها</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($visibleVirtualMachineCount ?? $project->virtualMachines->count()) }}</p>
            <a href="{{ route('customer.servers.index', [], false) }}" class="mt-1 inline-flex text-sm font-black text-[#0069FF]">مشاهده ماشین‌ها</a>
        </article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">دسترسی فضای کاری</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">مالک و مدیر همه ماشین‌های فضا را می‌بینند. نقش عضو فقط ماشین‌هایی را می‌بیند که خودش ساخته است.</p>
                    </div>
                    <span class="w-fit rounded-lg {{ $ownerPays ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-slate-100 text-slate-700' }} px-3 py-2 text-xs font-black">
                        مسئول پرداخت: {{ $project->owner?->name }}
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($project->members as $member)
                        <div class="flex flex-col gap-3 rounded-lg border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-black text-slate-950">{{ $member->customer?->name }}</p>
                                <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if($canManageMembers && $member->customer_id !== $project->owner_customer_id)
                                    <form method="POST" action="{{ route('customer.projects.members.update', [$project, $member], false) }}" class="flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black">
                                            @foreach(['admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'] as $role => $label)
                                                <option value="{{ $role }}" @selected($member->role === $role)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-black text-white">ذخیره</button>
                                    </form>
                                    <form method="POST" action="{{ route('customer.projects.members.destroy', [$project, $member], false) }}" onsubmit="return confirm('این عضو حذف شود؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-600">حذف</button>
                                    </form>
                                @else
                                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-600">{{ $roleLabels[$member->role] ?? $member->role }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">تنظیمات فضای کاری</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">مالک و مدیر می‌توانند نام فضای کاری را تغییر دهند. مسئول پرداخت تغییر نمی‌کند.</p>
                @if($canManageMembers)
                    <form method="POST" action="{{ route('customer.projects.update', $project, false) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        <input name="name" value="{{ old('name', $project->name) }}" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" aria-label="نام فضای کاری">
                        <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">تغییر نام</button>
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">فقط مالک یا مدیر این فضای کاری می‌تواند نام آن را تغییر دهد.</p>
                @endif
            </div>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
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
                            <option value="admin">مدیر</option>
                            <option value="member" selected>عضو</option>
                            <option value="viewer">فقط مشاهده</option>
                            <option value="billing">مالی</option>
                        </select>
                    </label>
                    <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">افزودن عضو</button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">برای دعوت عضو باید مالک یا مدیر این فضای کاری باشید.</p>
            @endif

            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-black text-slate-950">راهنمای نقش‌ها</p>
                <div class="mt-3 space-y-2 text-xs font-bold leading-6 text-slate-600">
                    <p><span class="font-black text-slate-900">مالک / مدیر:</span> مدیریت اعضا و ماشین‌ها</p>
                    <p><span class="font-black text-slate-900">عضو:</span> مدیریت ماشین‌ها</p>
                    <p><span class="font-black text-slate-900">فقط مشاهده:</span> فقط دیدن منابع</p>
                    <p><span class="font-black text-slate-900">مالی:</span> دیدن صورتحساب و پرداخت‌ها</p>
                </div>
            </div>
        </aside>
    </section>
@endsection
