@extends('customer.layout')

@section('title', 'فضاهای کاری')
@section('header_title', 'فضاهای کاری')
@section('header_subtitle', 'فضای کاری یک محیط مشترک برای ماشین‌ها، اعضا و پرداخت‌هاست. با تغییر آن، منابع و صورتحسابی که می‌بینید تغییر می‌کند.')
@section('breadcrumbs')
    <span class="truncate text-slate-700">فضاهای کاری</span>
@endsection

@php
    $activeNav = 'projects';
    $roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
@endphp

@section('content')
    <section x-data="{ creating: false }">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-950">فضاهای کاری شما <span class="text-sm text-slate-400">({{ number_format($projects->count()) }})</span></h2>
                <p class="mt-1 text-sm text-slate-500">برای دیدن منابع وارد یک فضا شوید، یا اعضا و تنظیمات آن را مدیریت کنید.</p>
            </div>
            <button type="button" @click="$refs.createDialog.showModal(); $nextTick(() => $refs.workspaceName.focus())" class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">+ ساخت فضای کاری</button>
        </div>
        <div class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach($projects as $project)
                @php
                    $membership = $project->members->firstWhere('customer_id', $customer->id);
                    $isActive = (int) $activeProject->id === (int) $project->id;
                    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
                    $canViewVms = app(\App\Services\ProjectAccessService::class)->canViewVms($project, $customer);
                @endphp
                <article class="rounded-lg border {{ $isActive ? 'border-[#0069FF] bg-[#F8FBFF]' : 'border-slate-200 bg-white' }} p-5 shadow-sm shadow-slate-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap gap-2 text-xs font-black">
                                @if($isActive)<span class="rounded-md bg-[#EBF3FF] px-2 py-1 text-[#0069FF]">فضای فعال</span>@endif
                                @if($project->is_default)<span class="rounded-md bg-slate-100 px-2 py-1 text-slate-600">پیش‌فرض</span>@endif
                            </div>
                            <h3 class="mt-2 truncate text-xl font-black text-slate-950" title="{{ $project->name }}">{{ $project->name }}</h3>
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

                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                        @unless($isActive)
                            <form method="POST" action="{{ route('customer.projects.switch', [], false) }}">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <input type="hidden" name="destination" value="resources">
                                <button class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-black text-white transition hover:bg-[#0A2A66] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">ورود به فضای کاری</button>
                            </form>
                        @endunless
                        @if($isActive)
                            <a href="{{ route($canViewVms ? 'customer.servers.index' : 'dashboard', [], false) }}" class="rounded-lg bg-[#031B4E] px-4 py-2 text-sm font-black text-white transition hover:bg-[#0A2A66]">{{ $canViewVms ? 'مشاهده منابع' : 'نمای کلی فضای کاری' }}</a>
                        @endif
                        <a href="{{ route('customer.projects.show', $project, false) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">مدیریت</a>
                    </div>
                </article>
            @endforeach
        </div>

        <dialog data-workspace-dialog x-ref="createDialog" aria-labelledby="create-workspace-title" aria-describedby="create-workspace-help" @close="creating = false" @click="if ($event.target === $el && ($event.clientX < $el.getBoundingClientRect().left || $event.clientX > $el.getBoundingClientRect().right || $event.clientY < $el.getBoundingClientRect().top || $event.clientY > $el.getBoundingClientRect().bottom)) $el.close()" @if($errors->has('name')) x-init="$el.showModal()" @endif class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-right text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
            <div class="flex items-center justify-between gap-3">
                <h2 id="create-workspace-title" class="text-lg font-black">ساخت فضای کاری</h2>
                <button type="button" @click="$refs.createDialog.close()" aria-label="بستن" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button>
            </div>
            <p id="create-workspace-help" class="mt-2 text-sm leading-7 text-slate-500">برای تیم یا محصول خود یک فضای جدا بسازید. پس از ساخت، وارد فضای جدید می‌شوید.</p>
            <form method="POST" action="{{ route('customer.projects.store', [], false) }}" class="mt-5 space-y-4" @submit="creating = true">
                @csrf
                <label class="block" for="workspace-name">
                    <span class="text-sm font-black text-slate-700">نام فضای کاری</span>
                    <input id="workspace-name" x-ref="workspaceName" name="name" required maxlength="120" autofocus value="{{ old('name') }}" @error('name') aria-invalid="true" aria-describedby="workspace-name-error" @enderror class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none" placeholder="مثلاً سرورهای عملیاتی">
                </label>
                @error('name')<p id="workspace-name-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <p class="rounded-lg bg-[#EBF3FF] p-3 text-xs font-bold leading-6 text-[#031B4E]">شما مالک این فضا و مسئول پرداخت هزینه منابع آن خواهید بود؛ حتی منابعی که اعضای دیگر می‌سازند.</p>
                <div class="flex gap-3">
                    <button :disabled="creating" class="flex-1 rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] disabled:opacity-60" x-text="creating ? 'در حال ساخت…' : 'ساخت و ورود به فضای کاری'">ساخت و ورود به فضای کاری</button>
                    <button type="button" @click="$refs.createDialog.close()" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">انصراف</button>
                </div>
            </form>
        </dialog>
    </section>
@endsection
