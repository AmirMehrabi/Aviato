@props([
    'label',
    'icon' => 'view',
    'href' => null,
    'tone' => 'neutral',
    'type' => 'button',
])

@php
    $toneClasses = [
        'primary' => 'border-blue-200 bg-blue-50 text-[#0069FF] hover:border-[#0069FF] hover:bg-[#0069FF] hover:text-white',
        'neutral' => 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-950',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700 hover:border-sky-600 hover:bg-sky-600 hover:text-white',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-600 hover:bg-emerald-600 hover:text-white',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-500 hover:bg-amber-500 hover:text-white',
        'danger' => 'border-red-200 bg-red-50 text-red-700 hover:border-red-600 hover:bg-red-600 hover:text-white',
        'purple' => 'border-purple-200 bg-purple-50 text-purple-700 hover:border-purple-600 hover:bg-purple-600 hover:text-white',
    ];
    $classes = 'group relative inline-flex size-10 shrink-0 items-center justify-center rounded-lg border transition-[color,background-color,border-color,transform] duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0069FF] focus-visible:ring-offset-2 active:scale-[.96] motion-reduce:transition-none motion-reduce:active:scale-100 '.($toneClasses[$tone] ?? $toneClasses['neutral']);
@endphp

@if($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-admin.icon :name="$icon" />
        <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-950 px-2.5 py-1.5 text-xs font-bold text-white shadow-lg group-hover:block group-focus-visible:block">{{ $label }}</span>
    </a>
@else
    <button type="{{ $type }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-admin.icon :name="$icon" />
        <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-950 px-2.5 py-1.5 text-xs font-bold text-white shadow-lg group-hover:block group-focus-visible:block">{{ $label }}</span>
    </button>
@endif
