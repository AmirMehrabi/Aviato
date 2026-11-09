@php
    $editing = isset($editingMember);
    $formKey = $editing ? 'member-'.$editingMember->id : 'add';
    $hasOldInput = old('_member_form') === $formKey;
    $selectedRole = $hasOldInput ? old('role', 'member') : ($editingMember->role ?? 'member');
    $selectedScope = $hasOldInput ? old('vm_access_scope', 'own') : ($editingMember->vm_access_scope ?? 'own');
    $vmSearchTerms = $assignableVms->map(fn ($vm) => mb_strtolower(($vm->display_name ?: $vm->name).' '.$vm->name.' '.$vm->ip_address))->values()->all();
    $selectedVmIds = array_map('strval', (array) ($hasOldInput ? old('vm_ids', []) : ($memberVmIds[$editingMember->id ?? 0] ?? [])));
@endphp
<div class="space-y-5" x-data="{ role: @js($selectedRole), scope: @js($selectedScope), selected: @js($selectedVmIds), vmSearchTerms: @js($vmSearchTerms), search: '', scopeTouched: @js($editing || $hasOldInput), syncScope() { if (!this.scopeTouched) this.scope = this.role === 'member' ? 'own' : 'all'; } }">
    <input type="hidden" name="_member_form" value="{{ $formKey }}">
    @if($hasOldInput && $errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert" tabindex="-1" x-init="$el.focus()">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    @unless($editing)
        <label class="block" for="{{ $formKey }}-identifier">
            <span class="text-sm font-black text-slate-700">ایمیل یا موبایل مشتری</span>
            <input id="{{ $formKey }}-identifier" required name="identifier" maxlength="255" value="{{ $hasOldInput ? old('identifier') : '' }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-[#0069FF] focus:outline-none">
        </label>
    @endunless
    <label class="block" for="{{ $formKey }}-role">
        <span class="text-sm font-black text-slate-700">نقش</span>
        <select id="{{ $formKey }}-role" name="role" x-model="role" @change="syncScope()" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-[#0069FF] focus:outline-none">
            @foreach(['admin' => 'مدیر', 'member' => 'عضو', 'viewer' => 'فقط مشاهده', 'billing' => 'مالی'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="mt-2 block text-xs leading-6 text-slate-500">
            <span x-show="role === 'admin'">مدیریت اعضا، تنظیمات و امور مالی؛ مدیریت ماشین‌ها در محدوده انتخاب‌شده.</span>
            <span x-show="role === 'member'">ساخت ماشین جدید و مدیریت ماشین‌های مجاز؛ بدون مدیریت اعضا یا امور مالی.</span>
            <span x-show="role === 'viewer'">فقط مشاهده ماشین‌های مجاز، بدون امکان تغییر آن‌ها.</span>
            <span x-show="role === 'billing'">فقط امور مالی؛ این نقش به ماشین‌ها دسترسی ندارد.</span>
        </span>
    </label>

    <fieldset class="space-y-3" x-show="role !== 'billing'">
        <legend class="mb-2 text-sm font-black text-slate-700">به کدام ماشین‌ها دسترسی داشته باشد؟</legend>
        @foreach(['all' => ['همه ماشین‌ها', 'تمام ماشین‌های فعلی و ماشین‌هایی که بعداً ساخته می‌شوند.'], 'own' => ['ماشین‌های خود عضو', 'فقط ماشین‌هایی که این عضو ساخته است.'], 'specific' => ['ماشین‌های مشخص', 'فقط ماشین‌هایی که در فهرست زیر انتخاب می‌کنید.']] as $value => [$label, $description])
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-[#0069FF] has-[:checked]:bg-[#EBF3FF]">
                <input type="radio" name="vm_access_scope" value="{{ $value }}" x-model="scope" @change="scopeTouched = true" @checked($selectedScope === $value) class="mt-1 accent-[#0069FF]">
                <span><span class="block text-sm font-bold text-slate-900">{{ $label }}</span><span class="mt-1 block text-xs leading-6 text-slate-500">{{ $description }}</span></span>
            </label>
        @endforeach
        <div x-show="scope === 'specific'" @if($selectedScope !== 'specific') x-cloak @endif class="space-y-3 rounded-lg border border-slate-200 p-3">
            @if($assignableVms->isNotEmpty())
                <label class="block" for="{{ $formKey }}-search">
                    <span class="sr-only">جستجوی ماشین</span>
                    <input id="{{ $formKey }}-search" type="search" x-model="search" placeholder="جستجوی نام یا IP ماشین…" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#0069FF] focus:outline-none">
                </label>
                <p class="text-xs font-bold text-[#0069FF]" aria-live="polite"><span x-text="selected.length">{{ count($selectedVmIds) }}</span> ماشین انتخاب شده</p>
                <div class="max-h-60 space-y-1 overflow-y-auto" role="group" aria-label="انتخاب ماشین‌های مجاز">
                    @foreach($assignableVms as $vm)
                        <label x-show="@js(mb_strtolower(($vm->display_name ?: $vm->name).' '.$vm->name.' '.$vm->ip_address)).includes(search.toLowerCase().trim())" class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                            <input type="checkbox" name="vm_ids[]" value="{{ $vm->id }}" x-model="selected" :disabled="scope !== 'specific'" @checked(in_array((string) $vm->id, $selectedVmIds, true)) class="accent-[#0069FF]">
                            <span class="min-w-0"><span class="block break-all text-sm font-bold text-slate-800" dir="auto">{{ $vm->display_name ?: $vm->name }}</span><span class="block text-xs text-slate-500" dir="ltr">{{ $vm->ip_address }}</span></span>
                        </label>
                    @endforeach
                </div>
                <p x-cloak x-show="search.trim() && !vmSearchTerms.some(term => term.includes(search.toLowerCase().trim()))" class="text-xs text-slate-500">ماشینی با این نام یا IP پیدا نشد.</p>
                <p class="text-xs leading-6 text-slate-500">ماشین‌های جدید خودکار به این فهرست اضافه نمی‌شوند.</p>
            @else
                <p class="text-sm leading-7 text-slate-500">هنوز ماشینی در این فضای کاری وجود ندارد. «همه ماشین‌ها» یا «ماشین‌های خود عضو» را انتخاب کنید.</p>
            @endif
        </div>
    </fieldset>
    <fieldset class="border-t border-slate-100 pt-4">
        <legend class="text-sm font-bold text-slate-700">دسترسی DNS</legend>
        <div class="mt-2 flex flex-wrap gap-4 text-xs">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="dns_read" value="1" @checked($hasOldInput ? old('dns_read') : ($editingMember->dns_read ?? false)) class="accent-[#0069FF]"> مشاهده DNS</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="dns_manage" value="1" @checked($hasOldInput ? old('dns_manage') : ($editingMember->dns_manage ?? false)) class="accent-[#0069FF]"> مدیریت DNS</label>
        </div>
        <p class="mt-2 text-xs leading-6 text-slate-500">مالک و مدیر فضای کاری همیشه دسترسی DNS دارند.</p>
    </fieldset>
    <button class="w-full rounded-lg bg-[#0069FF] px-4 py-3 text-sm font-black text-white transition hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $editing ? 'ذخیره دسترسی‌ها' : 'افزودن عضو' }}</button>
</div>
