@php
    $isActive = $activeNav === $item['key'];
@endphp
<a
    href="{{ $item['route'] ?: '#' }}"
    @if (! $item['route']) aria-disabled="true" @endif
    @if ($isActive) aria-current="page" @endif
    @click="if (window.innerWidth < 1024) sidebarOpen = false"
    class="relative flex min-h-[42px] items-center gap-3 overflow-hidden rounded-lg px-3 py-2.5 leading-5 transition focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#69A9FF] {{ $isActive ? 'bg-[#234995] font-semibold text-white' : ($item['route'] ? 'text-[#C7D4EA] hover:bg-[#0A2A66] hover:text-white' : 'cursor-default text-[#9DB4DC] opacity-70') }}"
>
    @if ($isActive)
        <span class="absolute inset-y-2 right-0 w-[3px] rounded-l-full bg-[#168BFF]" aria-hidden="true"></span>
    @endif
    <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path d="{{ $item['icon'] }}" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="min-w-0">{{ $item['label'] }}</span>
    @if ($item['key'] === 'invoices' && ($invoiceCount ?? null))
        <span class="mr-auto shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-[#C7D4EA]">{{ $invoiceCount }}</span>
    @endif
    @if ($item['key'] === 'tickets')
        <span x-cloak x-show="ticketUnreadCount > 0" x-text="ticketUnreadCount.toLocaleString('fa-IR')" class="mr-auto shrink-0 rounded-full bg-[#0069FF] px-2 py-0.5 text-[10px] font-semibold text-white"></span>
    @endif
</a>
