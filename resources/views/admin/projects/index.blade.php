@extends('layouts.admin')

@section('title', 'فضاهای کاری')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl bg-[#031B4E] p-6 text-white shadow-xl shadow-[#031B4E]/15">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-bold text-white/60">مدیریت فضاهای کاری</p>
                <h1 class="mt-2 text-2xl font-black md:text-4xl">فضاهای کاری</h1>
                <p class="mt-2 max-w-3xl text-sm leading-7 text-white/70">هر فضای کاری یک مالک دارد. هزینه همه ماشین‌ها و منابع داخل آن با همان مالک است.</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.projects.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="جستجوی نام فضای کاری، مالک، ایمیل یا موبایل..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select name="owner" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه مالک‌ها</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $owner->id)>{{ $owner->name }} - {{ $owner->email ?: $owner->phone }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">اعمال فیلتر</button>
            <a href="{{ route('admin.projects.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</a>
        </div>
    </form>

    <section class="mt-6">
        <x-admin.index-table :columns="[
            ['label' => 'فضای کاری'],
            ['label' => 'مالک'],
            ['label' => 'منابع'],
            ['label' => 'مسئول پرداخت'],
            ['label' => 'تاریخ ایجاد'],
            ['label' => 'عملیات', 'class' => 'text-left'],
        ]">
            @forelse ($projects as $project)
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-5 py-4">
                        <div>
                            <a href="{{ route('admin.projects.show', $project) }}" class="font-black text-slate-950 hover:text-[#0069FF]">{{ $project->name }}</a>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                <span>#{{ $project->id }}</span>
                                @if($project->is_default)
                                    <span class="rounded bg-[#EBF3FF] px-2 py-0.5 font-black text-[#0069FF]">پیش‌فرض</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <a href="{{ route('admin.customers.show', $project->owner) }}" class="font-bold text-slate-900 hover:text-[#0069FF]">{{ $project->owner?->name }}</a>
                        <p class="mt-1 text-xs text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-black text-slate-950">{{ number_format($project->virtual_machines_count) }} ماشین</p>
                        <p class="mt-1 text-xs text-slate-500">{{ number_format($project->members_count) }} عضو</p>
                    </td>
                    <td class="px-5 py-4">
                        <span class="rounded-md bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">{{ $project->owner?->name }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $project->created_at?->format('Y/m/d') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.projects.show', $project) }}" class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white">مشاهده</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-14 text-center">
                        <h2 class="text-xl font-black text-slate-900">فضای کاری پیدا نشد</h2>
                        <p class="mt-2 text-slate-500">فیلترها را تغییر دهید و دوباره جستجو کنید.</p>
                    </td>
                </tr>
            @endforelse

            <x-slot:pagination>
                {{ $projects->links() }}
            </x-slot:pagination>
        </x-admin.index-table>
    </section>
</div>
@endsection
