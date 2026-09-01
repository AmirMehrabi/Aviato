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
    $canManageMembers = $projectMembership?->canManageMembers() ?? false;
    $ownerPays = (int) $project->owner_customer_id === (int) $customer->id;
    $isOwner = $ownerPays;
    $isActive = (int) $activeProject->id === (int) $project->id;
    $roleLabels = ['owner' => 'مالک', 'admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'];
@endphp

@section('content')
    @unless($isActive)
        <section class="mb-5 flex flex-col gap-4 rounded-lg border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="inactive-workspace-title">
            <div>
                <h2 id="inactive-workspace-title" class="font-black text-amber-950">در حال مدیریت یک فضای کاری غیرفعال هستید</h2>
                <p class="mt-1 text-sm font-bold leading-7 text-amber-800">تغییرات این صفحه برای «{{ $project->name }}» ذخیره می‌شود، اما منابع منو هنوز مربوط به «{{ $activeProject->name }}» است.</p>
            </div>
            <form method="POST" action="{{ route('customer.projects.switch', [], false) }}" class="shrink-0">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <button class="w-full rounded-lg bg-[#031B4E] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0A2A66] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] sm:w-auto">ورود به این فضای کاری</button>
            </form>
        </section>
    @endunless

    <section class="grid gap-5 lg:grid-cols-4">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">مالک</p>
            <p class="mt-2 truncate text-xl font-black text-slate-950">{{ $project->owner?->name }}</p>
            <p class="mt-1 truncate text-sm font-bold text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <p class="text-xs font-black tracking-wide text-slate-500">نقش شما</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $roleLabels[$projectMembership?->role ?? 'member'] ?? 'عضو' }}</p>
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
            @if($isActive)
                <a href="{{ route('customer.servers.index', [], false) }}" class="mt-1 inline-flex text-sm font-black text-[#0069FF]">مشاهده ماشین‌ها</a>
            @else
                <p class="mt-1 text-sm font-bold text-slate-500">برای مشاهده، ابتدا وارد این فضا شوید</p>
            @endif
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

                <div class="mt-5 border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-black text-slate-950">فضای کاری پیش‌فرض</h3>
                            <p class="mt-1 text-sm font-bold leading-7 text-slate-500">پس از ورود، این فضا به‌صورت پیش‌فرض انتخاب می‌شود. تغییر آن، فضای فعال فعلی را عوض نمی‌کند.</p>
                        </div>
                        @if($project->is_default)
                            <span class="w-fit shrink-0 rounded-lg bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-700">پیش‌فرض</span>
                        @elseif($isOwner)
                            <form method="POST" action="{{ route('customer.projects.default', $project, false) }}">
                                @csrf
                                @method('PATCH')
                                <button class="rounded-lg border border-[#0069FF] px-4 py-2 text-sm font-black text-[#0069FF] transition hover:bg-[#EBF3FF]">انتخاب به‌عنوان پیش‌فرض</button>
                            </form>
                        @else
                            <span class="text-sm font-bold text-slate-500">فقط مالک می‌تواند این گزینه را تغییر دهد.</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($isOwner)
                <div class="rounded-lg border border-red-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    <h2 class="text-lg font-black text-red-700">حذف فضای کاری</h2>
                    <p class="mt-2 text-sm font-bold leading-7 text-slate-600">حذف دائمی است. فقط فضای کاری غیرپیش‌فرض و خالی را می‌توانید حذف کنید؛ سوابق مالی و انتقال‌های تکمیل‌شده حفظ می‌شوند.</p>

                    @if($deletionBlockers !== [])
                        <div class="mt-4 rounded-lg bg-red-50 p-4" role="status">
                            <p class="text-sm font-black text-red-800">این فضای کاری اکنون قابل حذف نیست:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1 text-sm font-bold leading-7 text-red-700">
                                @foreach($deletionBlockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <form method="POST" action="{{ route('customer.projects.destroy', $project, false) }}" class="mt-4" onsubmit="return confirm('فضای کاری «{{ addslashes($project->name) }}» برای همیشه حذف شود؟')">
                            @csrf
                            @method('DELETE')
                            <label class="block">
                                <span class="text-sm font-black text-slate-700">برای تأیید، نام «{{ $project->name }}» را وارد کنید</span>
                                <input name="confirmation" autocomplete="off" class="mt-2 w-full rounded-xl border border-red-200 px-4 py-3 focus:border-red-500 focus:outline-none" aria-describedby="delete-workspace-help">
                            </label>
                            <p id="delete-workspace-help" class="mt-2 text-xs font-bold text-slate-500">نام باید دقیقاً یکسان باشد.</p>
                            @error('delete')
                                <p class="mt-2 text-sm font-bold text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                            @error('confirmation')
                                <p class="mt-2 text-sm font-bold text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                            <button class="mt-4 rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">حذف دائمی فضای کاری</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="text-lg font-black text-slate-950">افزودن عضو</h2>
            @if($canManageMembers)
                <p class="mt-2 text-sm font-bold leading-7 text-slate-500">مشتری فوراً عضو می‌شود و اعلانی با لینک ورود به این فضای کاری دریافت می‌کند.</p>
                <form method="POST" action="{{ route('customer.projects.members.store', $project, false) }}" class="mt-4 space-y-4" x-data="{ role: '{{ old('role', 'member') }}' }">
                    @csrf
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">ایمیل یا موبایل مشتری</span>
                        <input name="identifier" value="{{ old('identifier') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-slate-700">نقش</span>
                        <select name="role" x-model="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                            <option value="admin">مدیر</option>
                            <option value="member" selected>عضو</option>
                            <option value="viewer">فقط مشاهده</option>
                            <option value="billing">مالی</option>
                        </select>
                        <span class="mt-2 block text-xs font-bold leading-6 text-slate-500">
                            <span x-show="role === 'admin'">مدیریت اعضا، تنظیمات و همه ماشین‌های فضای کاری</span>
                            <span x-show="role === 'member'">ساخت و مدیریت ماشین‌های خودش، بدون دسترسی به اعضا و تنظیمات</span>
                            <span x-show="role === 'viewer'">فقط مشاهده منابعی که برای او تعیین شده است</span>
                            <span x-show="role === 'billing'">مشاهده صورتحساب و پرداخت‌ها، بدون دسترسی به ماشین‌ها</span>
                        </span>
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
