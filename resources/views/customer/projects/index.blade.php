@extends('customer.layout')

@section('title', 'پروژه ها')
@section('header_title', 'پروژه ها')
@section('header_subtitle', 'انتخاب پروژه فعال، ساخت پروژه جدید و مدیریت دسترسی ها')
@section('breadcrumbs')
    <span class="truncate text-slate-700">پروژه ها</span>
@endsection

@php($activeNav = 'projects')

@section('content')
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $errors->first() }}</div>@endif

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($projects as $project)
                @php($membership = $project->members->firstWhere('customer_id', $customer->id))
                <article class="rounded-2xl border {{ $activeProject->id === $project->id ? 'border-[#0069FF] bg-[#F8FBFF]' : 'border-slate-200 bg-white' }} p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black text-slate-500">{{ $project->is_default ? 'Default Project' : 'Project' }}</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $project->name }}</h2>
                            <p class="mt-2 text-sm font-bold text-slate-500">Owner: {{ $project->owner?->name }}</p>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $membership?->role }}</span>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('customer.projects.switch', [], false) }}">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $project->id }}">
                            <button class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-black text-white">فعال کردن</button>
                        </form>
                        <a href="{{ route('customer.projects.show', $project, false) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700">مدیریت</a>
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">پروژه جدید</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">ماشین های مجازی که داخل پروژه ساخته می‌شوند از کیف پول مالک پروژه محاسبه می‌شوند.</p>
            <form method="POST" action="{{ route('customer.projects.store', [], false) }}" class="mt-5 space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm font-black text-slate-700">نام پروژه</span>
                    <input name="name" value="{{ old('name') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" placeholder="Production Servers">
                </label>
                <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white">ساخت پروژه</button>
            </form>
        </aside>
    </section>
@endsection
