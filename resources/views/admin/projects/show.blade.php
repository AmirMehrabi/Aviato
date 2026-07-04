@extends('layouts.admin')

@section('title', $project->name)

@php
    $roleLabels = [
        'owner' => 'مالک',
        'admin' => 'مدیر',
        'member' => 'عضو',
        'viewer' => 'فقط مشاهده',
        'billing' => 'مالی',
    ];

    $scopeLabels = [
        'all' => 'همه VMها',
        'own' => 'VMهای خود عضو',
        'specific' => 'VMهای مشخص',
    ];

    $scopeDescriptions = [
        'all' => 'دسترسی به همه VMهای این workspace',
        'own' => 'فقط VMهایی که خود عضو ساخته یا مالکیت قدیمی دارند',
        'specific' => 'فقط VMهای انتخاب‌شده؛ VM جدید خودکار اضافه نمی‌شود',
    ];

    $defaultMemberRole = old('role', \App\Models\ProjectMember::ROLE_MEMBER);
    $defaultMemberScope = old('vm_access_scope', \App\Models\ProjectMember::defaultVmAccessScopeForRole($defaultMemberRole));
    $selectedCustomerIds = collect(old('customer_ids', []))->map(fn ($id): int => (int) $id)->all();
    $oldMemberVmIds = collect(old('vm_ids', []))->map(fn ($id): int => (int) $id)->all();
@endphp

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
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.projects.proforma', $project) }}" class="w-fit rounded-lg bg-white/20 px-4 py-2 text-sm font-black text-white transition hover:bg-white/30">پیش فاکتور</a>
                <span class="w-fit rounded-lg bg-white/10 px-4 py-2 text-sm font-black text-white">{{ $project->is_default ? 'فضای کاری پیش‌فرض' : 'فضای کاری' }}</span>
            </div>
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
            <p class="text-xs font-black tracking-wide text-slate-500">هزینه ماهانه</p>
            <p class="mt-2 text-xl font-black text-[#0069FF]">{{ number_format($totalMonthlyCost / 10) }} تومان</p>
            <p class="mt-1 text-sm font-bold text-slate-500">جمع تقریبی ماشین‌ها</p>
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
                                <th class="px-4 py-3 text-left">هزینه ماهانه</th>
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
                                    <td class="px-4 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-black text-slate-600">{{ \App\Support\AdminUi::status($vm->status) }}</span></td>
                                    <td class="px-4 py-3 text-left font-black text-slate-950">{{ number_format($vmPrices->get($vm->uuid, 0) / 10) }} <span class="text-xs font-bold text-slate-500">تومان</span></td>
                                    <td class="px-4 py-3 text-left">
                                        <a href="{{ route('admin.virtual-machines.show', $vm) }}" class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white">مشاهده</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">در این فضای کاری هنوز ماشینی وجود ندارد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">اعضا و دسترسی‌ها</h2>
                        <p class="mt-1 text-sm leading-7 text-slate-500">نقش، محدوده VM و فهرست VMهای مشخص برای هر عضو اینجا مدیریت می‌شود.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ number_format($project->members_count) }} عضو</span>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4" x-data="{ role: @js($defaultMemberRole), scope: @js($defaultMemberScope), scopeTouched: @js(old('vm_access_scope') !== null), syncScope() { if (! this.scopeTouched) { this.scope = this.role === 'member' ? 'own' : 'all'; } } }">
                    <h3 class="text-sm font-black text-slate-900">افزودن عضو جدید</h3>
                    <p class="mt-1 text-xs leading-6 text-slate-500">یک یا چند مشتری خارج از این workspace را انتخاب کنید.</p>
                    <form method="POST" action="{{ route('admin.projects.members.store', $project) }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block md:col-span-2">
                                <span class="text-sm font-black text-slate-700">مشتریان خارج از workspace</span>
                                <select name="customer_ids[]" multiple size="8" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#0069FF] focus:outline-none">
                                    @forelse($availableCustomers as $customer)
                                        <option value="{{ $customer->id }}" @selected(in_array($customer->id, $selectedCustomerIds, true))>
                                            {{ $customer->name }}{{ $customer->email ? ' - '.$customer->email : '' }}{{ $customer->phone ? ' - '.$customer->phone : '' }}
                                        </option>
                                    @empty
                                        <option value="" disabled>همه مشتری‌ها قبلا در این workspace عضو هستند.</option>
                                    @endforelse
                                </select>
                                @error('customer_ids') <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <x-form.select name="role" label="نقش" :selected="$defaultMemberRole" :options="$roleLabels" x-model="role" @change="syncScope()" />
                            <x-form.select name="vm_access_scope" label="دسترسی VM" :selected="$defaultMemberScope" :options="$scopeLabels" x-model="scope" @change="scopeTouched = true" />
                        </div>

                        <div x-cloak x-show="scope === 'specific'" class="rounded-xl border border-dashed border-[#B8D6FF] bg-white p-4">
                            <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                <p class="text-sm font-black text-slate-900">VMهای مشخص</p>
                                <p class="text-xs text-slate-500">فقط VMهای انتخاب‌شده در دسترس این عضو خواهند بود.</p>
                            </div>
                            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                @forelse($workspaceVirtualMachines as $vm)
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                        <input type="checkbox" name="vm_ids[]" value="{{ $vm->id }}" @checked(in_array($vm->id, $oldMemberVmIds, true)) class="mt-1 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]">
                                        <span class="min-w-0">
                                            <span class="block truncate font-black text-slate-900">{{ $vm->display_name }}</span>
                                            <span class="block truncate text-xs text-slate-500" dir="ltr">{{ $vm->name }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-500">هنوز هیچ VMای برای انتخاب وجود ندارد.</p>
                                @endforelse
                            </div>
                        </div>

                        <button class="rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">افزودن عضو</button>
                    </form>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-right">عضو</th>
                                <th class="px-4 py-3 text-right">نقش</th>
                                <th class="px-4 py-3 text-right">دسترسی VM</th>
                                <th class="px-4 py-3 text-right">VMهای مشخص</th>
                                <th class="px-4 py-3 text-left">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr class="bg-slate-50/60">
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-950">{{ $project->owner?->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $project->owner?->email ?: $project->owner?->phone }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full bg-[#EBF3FF] px-3 py-1 text-xs font-black text-[#031B4E]">{{ $roleLabels['owner'] }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">{{ $scopeLabels['all'] }}</span>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-500">مالک همیشه دسترسی کامل دارد و قابل محدودسازی نیست.</td>
                                <td class="px-4 py-4 text-left">
                                    <span class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-500">غیرقابل تغییر</span>
                                </td>
                            </tr>

                            @foreach($project->members->where('customer_id', '!=', $project->owner_customer_id) as $member)
                                @php
                                    $memberVmIds = $member->specificVirtualMachines->pluck('id')->map(fn ($id): int => (int) $id)->all();
                                    $updateFormId = 'member-update-'.$member->id;
                                    $deleteFormId = 'member-delete-'.$member->id;
                                @endphp
                                <tr x-data="{ scope: @js($member->vm_access_scope) }">
                                    <td class="px-4 py-4">
                                        <form id="{{ $updateFormId }}" method="POST" action="{{ route('admin.projects.members.update', [$project, $member]) }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <form id="{{ $deleteFormId }}" method="POST" action="{{ route('admin.projects.members.destroy', [$project, $member]) }}" onsubmit="return confirm('این عضو از workspace حذف شود؟')">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <p class="font-black text-slate-950">{{ $member->customer?->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $member->customer?->email ?: $member->customer?->phone }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <select form="{{ $updateFormId }}" name="role" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-[#0069FF] focus:outline-none" aria-label="نقش عضو">
                                            @foreach($roleLabels as $value => $label)
                                                <option value="{{ $value }}" @selected($member->role === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <select form="{{ $updateFormId }}" name="vm_access_scope" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-[#0069FF] focus:outline-none" aria-label="دسترسی VM" x-model="scope">
                                            @foreach($scopeLabels as $value => $label)
                                                <option value="{{ $value }}" @selected($member->vm_access_scope === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-2 text-xs leading-6 text-slate-500">{{ $scopeDescriptions[$member->vm_access_scope] ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div x-cloak x-show="scope === 'specific'" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-3">
                                            <div class="grid gap-2 xl:grid-cols-2">
                                                @forelse($workspaceVirtualMachines as $vm)
                                                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                                        <input form="{{ $updateFormId }}" type="checkbox" name="vm_ids[]" value="{{ $vm->id }}" @checked(in_array($vm->id, $memberVmIds, true)) class="mt-1 rounded border-slate-300 text-[#0069FF] focus:ring-[#0069FF]" :disabled="scope !== 'specific'">
                                                        <span class="min-w-0">
                                                            <span class="block truncate font-black text-slate-900">{{ $vm->display_name }}</span>
                                                            <span class="block truncate text-[11px] text-slate-500" dir="ltr">{{ $vm->name }}</span>
                                                        </span>
                                                    </label>
                                                @empty
                                                    <p class="text-xs text-slate-500">هیچ VMای برای انتخاب وجود ندارد.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">{{ $member->usesSpecificVmScope() ? 'فقط VMهای داخل این فهرست در دسترس هستند.' : 'این عضو با انتخاب فعلی به VMهای مشخص محدود نشده است.' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-left">
                                        <div class="flex flex-col gap-2">
                                            <button form="{{ $updateFormId }}" class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white transition hover:bg-[#0050D0]">ذخیره</button>
                                            <button form="{{ $deleteFormId }}" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-black text-red-700 transition hover:bg-red-100">حذف</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
                <p class="mt-2 text-sm leading-7 text-[#031B4E]/80">حالت «VMهای مشخص» فقط همان VMهای انتخاب‌شده را باز می‌کند و به VMهای جدید دسترسی خودکار نمی‌دهد.</p>
            </div>
        </aside>
    </section>
</div>
@endsection
