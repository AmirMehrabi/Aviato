@extends('customer.layout')

@section('title', 'فضاهای کاری')
@section('header_title', 'فضاهای کاری')
@section('header_subtitle', 'فضای کاری برای گروه‌بندی ماشین‌ها و اعضاست. هزینه همه ماشین‌های داخل هر فضای کاری با مالک همان فضاست.')
@section('breadcrumbs')
    <span class="truncate text-slate-700">فضاهای کاری</span>
@endsection

@php
    $activeNav = 'projects';
    $roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
@endphp

@section('content')
    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($projects as $project)
                @php
                    $membership = $project->members->firstWhere('customer_id', $customer->id);
                    $isActive = (int) $activeProject->id === (int) $project->id;
                    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
                @endphp
                <article class="rounded-lg border {{ $isActive ? 'border-[#0069FF] bg-[#F8FBFF]' : 'border-slate-200 bg-white' }} p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-black tracking-wide {{ $isActive ? 'text-[#0069FF]' : 'text-slate-500' }}">{{ $project->is_default ? 'فضای کاری پیش‌فرض' : 'فضای کاری' }}</p>
                            <h2 class="mt-1 truncate text-xl font-black text-slate-950">{{ $project->name }}</h2>
                            <p class="mt-2 truncate text-sm font-bold text-slate-500">مالک: {{ $project->owner?->name }}</p>
                        </div>
                        <span class="shrink-0 rounded-lg bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $roleLabels[$membership?->role ?? 'member'] ?? 'عضو' }}</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="text-lg font-black text-slate-950">{{ number_format($project->visible_virtual_machines_count ?? $project->virtual_machines_count ?? 0) }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">ماشین‌ها</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="text-lg font-black text-slate-950">{{ number_format($project->members_count ?? $project->members->count()) }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">اعضا</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-3">
                            <p class="truncate text-sm font-black {{ $ownerPays ? 'text-[#0069FF]' : 'text-slate-950' }}">{{ $ownerPays ? 'شما' : $project->owner?->name }}</p>
                            <p class="mt-1 text-[11px] font-bold text-slate-500">پرداخت</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @unless($isActive)
                            <form method="POST" action="{{ route('customer.projects.switch', [], false) }}">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <button class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-black text-white transition hover:bg-[#0A2A66]">فعال کردن</button>
                            </form>
                        @endunless
                        <a href="{{ route('customer.projects.show', $project, false) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">باز کردن</a>
                        @if($isActive)
                            <span class="rounded-lg bg-[#EBF3FF] px-4 py-2 text-sm font-black text-[#0069FF]">فعال</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">ساخت فضای کاری</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">برای هر محصول، تیم یا محیط کاری یک فضای جدا بسازید. هزینه ماشین‌های داخل آن با مالک فضای کاری است.</p>
            <form method="POST" action="{{ route('customer.projects.store', [], false) }}" class="mt-5 space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm font-black text-slate-700">نام فضای کاری</span>
                    <input name="name" value="{{ old('name') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" placeholder="سرورهای عملیاتی">
                </label>
                <button class="w-full rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ساخت فضای کاری</button>
            </form>

            <div class="mt-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] p-4">
                <p class="text-sm font-black text-[#031B4E]">قانون پرداخت</p>
                <p class="mt-2 text-sm leading-7 text-[#031B4E]/80">مالک فضای کاری هزینه همه ماشین‌ها و منابع داخل آن را پرداخت می‌کند؛ حتی اگر یک عضو دیگر ماشین را ساخته باشد.</p>
            </div>
        </aside>
    </section>
@endsection
