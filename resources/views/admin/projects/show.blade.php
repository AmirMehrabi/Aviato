@extends('layouts.admin')

@section('title', $project->name)

@php($roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'])

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.projects.index') }}" class="text-sm font-black text-white/60 transition hover:text-white">فضاهای کاری</a>
                <h1 class="mt-2 truncate text-2xl font-black md:text-4xl">{{ $project->name }}</h1>
                <p class="mt-2 text-sm leading-7 text-white/70">مسئول پرداخت: {{ $project->owner?->name }}</p>
            </div>
            <span class="w-fit rounded-lg bg-white/10 px-4 py-2 text-sm font-black text-white">{{ $project->is_default ? 'فضای کاری پیش‌فرض' : 'فضای کاری' }}</span>
        </div>
    </div>

    <section class="mt-6 grid gap-5 lg:grid-cols-4">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">مالک</p>
            <a href="{{ route('admin.customers.show', $project->owner) }}" class="mt-2 block truncate text-xl font-black text-slate-950 hover:text-[#0069FF]">{{ $project->owner?->name }}</a>
            <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">اعضا</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->members_count) }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">افراد دارای دسترسی</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">ماشین‌ها</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($project->virtual_machines_count) }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">هزینه با مالک است</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">شناسه داخلی</p>
            <p class="mt-2 truncate text-xl font-black text-slate-950">{{ $project->slug }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">برای آدرس و پشتیبانی</p>
        </article>
    </section>

    <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">ماشین‌ها</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-right">ماشین</th>
                                <th class="px-4 py-3 text-right">ساخته‌شده توسط</th>
                                <th class="px-4 py-3 text-right">مسئول پرداخت</th>
                                <th class="px-4 py-3 text-right">وضعیت</th>
                                <th class="px-4 py-3 text-left">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($project->virtualMachines as $vm)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $vm->display_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $vm->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $vm->proxmoxServer?->name ?: 'بدون سرور' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $vm->creator?->name ?: $vm->customer?->name }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-900">{{ $project->owner?->name }}</td>
                                    <td class="px-4 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ $vm->status }}</span></td>
                                    <td class="px-4 py-3 text-left">
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white">مشاهده</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">در این فضای کاری هنوز ماشینی وجود ندارد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">اعضا</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($project->members as $member)
                        <div class="rounded-lg border border-slate-100 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-black text-slate-950">{{ $member->customer?->name }}</p>
                                    <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                                </div>
                                <span class="shrink-0 rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $roleLabels[$member->role] ?? $member->role }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">تنظیمات مدیریت</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">تغییر نام فضای کاری، مالک، اعضا، ماشین‌ها یا مسئول پرداخت را عوض نمی‌کند.</p>
            <form method="POST" action="{{ route('admin.projects.update', $project) }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <label class="block">
                    <span class="text-sm font-black text-slate-700">نام فضای کاری</span>
                    <input name="name" value="{{ old('name', $project->name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                </label>
                <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">تغییر نام فضای کاری</button>
            </form>

            <div class="mt-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] p-4">
                <p class="text-sm font-black text-[#031B4E]">راهنمای پشتیبانی</p>
                <p class="mt-2 text-sm leading-7 text-[#031B4E]/80">«ساخته‌شده توسط» نشان می‌دهد چه کسی ماشین را ایجاد کرده است. «مسئول پرداخت» نشان می‌دهد هزینه با چه کسی است.</p>
            </div>
        </aside>
    </section>
</div>
@endsection
