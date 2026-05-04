@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'hiddenValue' => '0',
    'includeHidden' => true,
    'wrapperClass' => 'flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm font-bold',
])

@php
    $isChecked = filter_var(old($name, $checked), FILTER_VALIDATE_BOOL);
@endphp

<label class="{{ $wrapperClass }}">
    <span>{{ $label }}</span>
    <span>
        @if($includeHidden)
            <input type="hidden" name="{{ $name }}" value="{{ $hiddenValue }}">
        @endif
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) {{ $attributes }}>
    </span>
</label>
