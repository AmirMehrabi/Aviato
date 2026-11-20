@php
    $isActive = $activeNav === $item['key'];
@endphp
<a
    href="{{ route($item['route'], [], false) }}"
    @if ($isActive) aria-current="{{ $sidebarRoute === $item['route'] ? 'page' : 'location' }}" @endif
    @click="if (window.innerWidth < 1024) sidebarOpen = false"
    @class([
        'flex items-center gap-3 rounded-xl px-3 font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white h-11 text-[13px]',
        'bg-white/12 text-white' => $isActive,
        'text-[#C7D4EA] hover:bg-[#0A2A66] hover:text-white' => ! $isActive,
    ])
>
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
