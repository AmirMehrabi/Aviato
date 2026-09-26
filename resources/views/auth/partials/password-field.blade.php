<div data-auth-field>
    <label for="{{ $name }}" class="text-sm font-black text-slate-700">
        {{ $label }}
        @if ($requiredMarker ?? false)
            <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only"> (الزامی)</span>
        @endif
    </label>
    <div class="relative mt-2">
        <input id="{{ $name }}" type="password" name="{{ $name }}" required
            autocomplete="{{ $autocomplete }}"
            @if ($minlength ?? false) minlength="{{ $minlength }}" @endif
            @if ($hintId ?? false) aria-describedby="{{ $hintId }}{{ $errors->has($name) ? ' '.$name.'-server-error' : '' }}"
            @elseif ($errors->has($name)) aria-describedby="{{ $name }}-server-error" @endif
            @error($name) aria-invalid="true" @enderror
            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-3 pl-4 pr-20 text-left text-sm font-semibold outline-none transition focus:border-[#0069FF] focus:bg-white focus:ring-4 focus:ring-[#0069FF]/10 aria-invalid:border-rose-400 aria-invalid:bg-rose-50/40"
            dir="ltr">
        <button type="button" data-password-toggle aria-controls="{{ $name }}" aria-pressed="false"
            aria-label="نمایش {{ $label }}"
            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-xs font-bold text-[#0069FF] transition hover:bg-[#EBF3FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            نمایش
        </button>
    </div>
    @if ($hint ?? false)
        <span id="{{ $hintId }}" class="mt-1 block text-xs leading-5 text-slate-500">{{ $hint }}</span>
    @endif
    @error($name)
        <span id="{{ $name }}-server-error" class="mt-1 block text-xs font-semibold text-rose-600" role="alert">{{ $message }}</span>
    @enderror
</div>
