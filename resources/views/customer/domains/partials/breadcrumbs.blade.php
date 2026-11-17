@foreach($parents as [$label, $url])
    <a href="{{ $url }}" class="transition hover:text-[#0069FF]">{{ $label }}</a>
    <svg class="size-3.5 shrink-0 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/>
    </svg>
@endforeach
<span class="min-w-0 truncate text-slate-700" aria-current="page" @if($ltr ?? false) dir="ltr" @endif>{{ $current }}</span>
