@props([
    'value',
    'label' => null,
    'tone' => null,
    'description' => null,
])

@php
    $meta = \App\Support\AdminUi::statusMeta($value);
    $resolvedTone = $tone ?? $meta['tone'];
    $resolvedLabel = $label ?? $meta['label'];
    $resolvedDescription = $description ?? $meta['description'];
    $toneClasses = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'primary' => 'bg-blue-50 text-[#0069FF] ring-blue-200',
        'neutral' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $dotClasses = [
        'success' => 'bg-emerald-600',
        'danger' => 'bg-red-600',
        'warning' => 'bg-amber-500',
        'info' => 'bg-sky-600',
        'primary' => 'bg-[#0069FF]',
        'neutral' => 'bg-slate-500',
    ];
@endphp

<span
    @if($resolvedDescription) title="{{ $resolvedDescription }}" @endif
    {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-black ring-1 ring-inset '.($toneClasses[$resolvedTone] ?? $toneClasses['neutral'])) }}
>
    <span class="size-1.5 rounded-full {{ $dotClasses[$resolvedTone] ?? $dotClasses['neutral'] }}" aria-hidden="true"></span>
    {{ $resolvedLabel }}
</span>
