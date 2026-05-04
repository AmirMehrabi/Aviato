@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'help' => null,
    'wrapperClass' => 'block',
    'selectClass' => '',
])

<label class="{{ $wrapperClass }}">
    <span class="text-sm font-black text-slate-700">{{ $label }}</span>
    <select
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 focus:border-[#105D52] focus:outline-none '.$selectClass]) }}
    >
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if($help)
        <span class="mt-1 block text-xs text-slate-500">{{ $help }}</span>
    @endif
    @error($name) <span class="mt-1 block text-xs font-bold text-red-600">{{ $message }}</span> @enderror
</label>
