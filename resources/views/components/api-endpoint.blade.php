@php
    $badge = match (strtoupper($method)) {
        'POST' => ['class' => 'bg-sky-100 text-sky-700 ring-sky-200', 'label' => 'ساخت', 'icon' => '↑'],
        'DELETE' => ['class' => 'bg-rose-100 text-rose-700 ring-rose-200', 'label' => 'حذف', 'icon' => '×'],
        default => ['class' => 'bg-emerald-100 text-emerald-700 ring-emerald-200', 'label' => 'خواندن', 'icon' => '↓'],
    };
@endphp

<div class="flex flex-wrap items-center gap-3">
    <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-mono text-xs font-black ring-1 {{ $badge['class'] }}" dir="ltr" title="{{ $badge['label'] }}">
        <span class="grid size-4 place-items-center rounded-full bg-white/70 text-sm leading-none" aria-hidden="true">{{ $badge['icon'] }}</span>
        {{ strtoupper($method) }}
    </span>
    <code class="break-all font-mono text-sm font-bold text-slate-800" dir="ltr">{{ $path }}</code>
    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500" dir="ltr">{{ $ability }}</span>
</div>
