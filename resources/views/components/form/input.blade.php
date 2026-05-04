@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
    'wrapperClass' => 'block',
    'inputClass' => '',
    'dirLtr' => false,
])

<label class="{{ $wrapperClass }}">
    <span class="text-sm font-black text-slate-700">{{ $label }}</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->merge(['class' => 'mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none '.($dirLtr ? 'text-left dir-ltr ' : '').$inputClass]) }}
    >
    @if($help)
        <span class="mt-1 block text-xs text-slate-500">{{ $help }}</span>
    @endif
    @error($name) <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
</label>
