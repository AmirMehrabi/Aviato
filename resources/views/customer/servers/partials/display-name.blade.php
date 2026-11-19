@if ($canManageServer)
    <h1 class="{{ $headingClass }}" :class="{ 'hidden!': editing }"><bdi>{{ $server->display_name }}</bdi></h1>
    <form method="POST" action="{{ route('customer.servers.display-name.update', $server, false) }}" x-cloak :class="{ 'hidden!': !editing }" @submit="if (saving) { $event.preventDefault(); } else { saving = true; }" @keydown.escape.prevent.stop="cancel()" class="w-full max-w-xl space-y-2">
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
@else
    <h1 class="{{ $headingClass }}"><bdi>{{ $server->display_name }}</bdi></h1>
@endif
