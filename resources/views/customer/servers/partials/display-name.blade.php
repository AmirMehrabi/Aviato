@if ($canManageServer ?? false)
    @php
        $displayNameInput = old('display_name', $server->display_name);
        $displayNameInput = is_string($displayNameInput) ? $displayNameInput : ($displayNameInput === null ? '' : $server->display_name);
    @endphp
    <div class="min-w-0 flex-1" x-data="{
        editing: @js($errors->renameVm->has('display_name')),
        saving: false,
        name: @js($displayNameInput),
        start() { this.editing = true; this.$nextTick(() => { this.$refs.name.focus(); this.$refs.name.select(); }); },
        cancel() { if (this.saving) return; this.editing = false; this.name = @js($server->display_name); this.$nextTick(() => this.$refs.edit.focus()); }
    }" x-init="if (editing) $nextTick(() => $refs.name.focus())">
        <div class="flex min-w-0 items-center gap-2" x-show="!editing">
            <h1 class="min-w-0 break-all text-3xl font-black tracking-tight text-slate-950" dir="auto">{{ $server->display_name }}</h1>
            <button type="button" x-ref="edit" @click="start()" aria-label="ویرایش نام نمایشی" title="ویرایش نام نمایشی" class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-[#EBF3FF] hover:text-[#0069FF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 3 5 5M4 20l4-1L20 7a3.5 3.5 0 0 0-5-5L3 14l-1 8 2-2Z"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('customer.servers.display-name.update', $server, false) }}" x-cloak x-show="editing" @submit="if (saving) { $event.preventDefault(); } else { saving = true; }" @keydown.escape.prevent.stop="cancel()" class="w-full max-w-xl space-y-2">
            @csrf
            @method('PATCH')
            <label for="vm-display-name" class="block text-sm font-black text-slate-700">نام نمایشی</label>
            <input id="vm-display-name" x-ref="name" x-model="name" name="display_name" value="{{ $displayNameInput }}" type="text" dir="auto" required maxlength="128" :readonly="saving" aria-describedby="vm-display-name-help{{ $errors->renameVm->has('display_name') ? ' vm-display-name-error' : '' }}" @if ($errors->renameVm->has('display_name')) aria-invalid="true" @endif class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-base font-bold text-slate-950 focus:border-[#0069FF] focus:outline-2 focus:outline-[#0069FF]">
            <p id="vm-display-name-help" class="text-xs font-bold leading-6 text-slate-500">این نام فقط در پنل نمایش داده می‌شود و hostname ماشین را تغییر نمی‌دهد.</p>
            @error('display_name', 'renameVm')
                <p id="vm-display-name-error" role="alert" class="text-xs font-bold text-red-700">{{ $message }}</p>
            @enderror
            <div class="flex flex-wrap gap-2">
                <button type="submit" :disabled="saving" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#0069FF] px-4 text-sm font-black text-white hover:bg-[#0050D0] disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]" x-text="saving ? 'در حال ذخیره…' : 'ذخیره'">ذخیره</button>
                <button type="button" @click="cancel()" :disabled="saving" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-black text-slate-600 hover:bg-slate-50 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">انصراف</button>
            </div>
        </form>
    </div>
@else
    <h1 class="min-w-0 break-all text-3xl font-black tracking-tight text-slate-950" dir="auto">{{ $server->display_name }}</h1>
@endif
