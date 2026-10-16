@props(['item', 'awaitingTickets' => 0])

<a href="{{ route($item['route']) }}"
    @click="if (window.innerWidth < 1024) sidebarOpen = false"
    @if ($item['active']) aria-current="page" @endif
    class="group relative flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-[13px] transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-white {{ $item['active'] ? 'bg-white/15 font-bold text-white ring-1 ring-inset ring-white/15' : 'font-medium text-white/80 hover:bg-white/10 hover:text-white' }}">
    <x-admin.nav-icon :name="$item['icon']" class="text-white/85 group-hover:text-white" />
    <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
    @if ($item['active'])
        <span class="absolute right-0 h-4 w-0.5 rounded-full bg-white" aria-hidden="true"></span>
    @endif
    @if ($item['route'] === 'admin.tickets.index' && $awaitingTickets > 0)
        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-black text-[#004DB3]" aria-label="{{ $awaitingTickets }} تیکت در انتظار پاسخ مدیر" title="در انتظار پاسخ مدیر">{{ $awaitingTickets > 99 ? '99+' : $awaitingTickets }}</span>
    @endif
</a>
