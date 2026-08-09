@extends('customer.layout')

@section('title', 'تیکت‌ها')
@section('header_title', 'تیکت‌های پشتیبانی')
@section('header_subtitle', 'درخواست‌های پشتیبانی، پاسخ‌ها و وضعیت پیگیری')
@section('breadcrumbs')
    <span class="truncate text-slate-700">تیکت‌ها</span>
@endsection
@php
    $activeNav = 'tickets';
@endphp

@section('content')
@php
    $priorityLabels = \App\Models\Ticket::priorities();
    $statusClasses = [
        \App\Models\Ticket::STATUS_OPEN => 'bg-blue-50 text-blue-700 ring-blue-200',
        \App\Models\Ticket::STATUS_PENDING => 'bg-amber-50 text-amber-800 ring-amber-200',
        \App\Models\Ticket::STATUS_ANSWERED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        \App\Models\Ticket::STATUS_CLOSED => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp

<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl bg-[#031B4E] p-5 text-white shadow-xl shadow-[#031B4E]/10 sm:p-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-black text-[#8FB8FF]">مرکز پشتیبانی</p>
                <h1 class="mt-2 text-2xl font-black leading-9 sm:text-3xl">درخواست‌ها و پاسخ‌ها، یک‌جا و روشن</h1>
                <p class="mt-2 text-sm font-bold leading-7 text-[#C7D4EA]">پاسخ‌های جدید را سریع پیدا کنید، وضعیت پیگیری را ببینید و گفتگو را از همان تیکت ادامه دهید.</p>
            </div>
            <a href="{{ route('customer.tickets.create', [], false) }}" class="inline-flex min-h-11 w-fit items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#0069FF]/25 transition-colors duration-150 hover:bg-[#0050D0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white active:scale-[0.96]">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                ثبت تیکت جدید
            </a>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach([
                ['label' => 'همه تیکت‌ها', 'value' => $ticketCounts['all'], 'url' => route('customer.tickets.index', [], false)],
                ['label' => 'پاسخ جدید', 'value' => $ticketCounts['unread'], 'url' => route('customer.tickets.index', ['attention' => 'unread'], false)],
                ['label' => 'در حال پیگیری', 'value' => $ticketCounts['open'], 'url' => route('customer.tickets.index', ['status' => \App\Models\Ticket::STATUS_OPEN], false)],
                ['label' => 'بسته‌شده', 'value' => $ticketCounts['closed'], 'url' => route('customer.tickets.index', ['status' => \App\Models\Ticket::STATUS_CLOSED], false)],
            ] as $stat)
                <a href="{{ $stat['url'] }}" class="rounded-2xl bg-white/10 p-3.5 ring-1 ring-white/10 transition-colors duration-150 hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    <span class="block text-2xl font-black">{{ number_format($stat['value']) }}</span>
                    <span class="mt-1 block text-xs font-bold text-[#B8C9E7]">{{ $stat['label'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 sm:p-5">
        <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_auto]" role="search">
            @if(($filters['attention'] ?? null) === 'unread')
                <input type="hidden" name="attention" value="unread">
            @endif
            <div>
                <label for="ticket-search" class="sr-only">جستجوی تیکت‌ها</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
                    <input id="ticket-search" name="search" value="{{ $filters['search'] ?? '' }}" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pe-4 ps-12 text-sm font-bold outline-none transition-colors duration-150 placeholder:text-slate-400 focus:border-[#0069FF] focus:bg-white focus-visible:ring-2 focus-visible:ring-[#B8D6FF]" placeholder="جستجو با شماره یا موضوع">
                </div>
            </div>
            <div>
                <label for="ticket-status" class="sr-only">وضعیت تیکت</label>
                <select id="ticket-status" name="status" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold outline-none focus:border-[#0069FF] focus:bg-white focus-visible:ring-2 focus-visible:ring-[#B8D6FF]">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="min-h-12 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition-colors duration-150 hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] active:scale-[0.96]">اعمال فیلتر</button>
        </form>

        @if(array_filter($filters))
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <span class="text-xs font-black text-slate-500">فیلتر فعال:</span>
                @if(($filters['attention'] ?? null) === 'unread')<span class="rounded-lg bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">فقط پاسخ‌های جدید</span>@endif
                @if($filters['status'] ?? null)<span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">{{ $statuses[$filters['status']] }}</span>@endif
                @if($filters['search'] ?? null)<span class="max-w-56 truncate rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">«{{ $filters['search'] }}»</span>@endif
                <a href="{{ route('customer.tickets.index', [], false) }}" class="rounded-lg px-2.5 py-1 text-xs font-black text-red-600 hover:bg-red-50">پاک کردن فیلترها</a>
            </div>
        @endif
    </section>

    <section aria-labelledby="ticket-list-title">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 id="ticket-list-title" class="text-lg font-black text-slate-950">تیکت‌ها</h2>
            <p class="text-xs font-bold text-slate-500">{{ number_format($tickets->total()) }} نتیجه</p>
        </div>

        <div class="space-y-3">
            @forelse($tickets as $ticket)
                @php
                    $latest = $ticket->latestPublicMessage;
                    $hasUnread = $ticket->unread_replies_count > 0;
                    $nextAction = match ($ticket->status) {
                        \App\Models\Ticket::STATUS_ANSWERED, \App\Models\Ticket::STATUS_PENDING => 'منتظر پاسخ شما',
                        \App\Models\Ticket::STATUS_CLOSED => 'گفتگو پایان یافته',
                        default => 'در حال بررسی پشتیبانی',
                    };
                @endphp
                <article class="relative overflow-hidden rounded-2xl border bg-white shadow-sm transition-[border-color,box-shadow,transform] duration-150 hover:-translate-y-0.5 hover:shadow-md {{ $hasUnread ? 'border-[#80B6FF] shadow-[#0069FF]/10' : 'border-slate-200 shadow-slate-200/50' }}">
                    @if($hasUnread)<span class="absolute inset-y-0 start-0 w-1 bg-[#0069FF]" aria-hidden="true"></span>@endif
                    <a href="{{ route('customer.tickets.show', $ticket, false) }}" class="block p-4 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#0069FF] sm:p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-lg bg-slate-950 px-2.5 py-1 text-xs font-black text-white" dir="ltr">{{ $ticket->number }}</span>
                                    <span class="rounded-lg px-2.5 py-1 text-xs font-black ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                                    @if($hasUnread)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#0069FF] px-2.5 py-1 text-xs font-black text-white">
                                            <span class="size-1.5 rounded-full bg-white" aria-hidden="true"></span>
                                            {{ $ticket->unread_replies_count }} پاسخ جدید
                                        </span>
                                    @endif
                                </div>
                                <h3 class="mt-3 text-base font-black leading-7 text-slate-950 sm:text-lg">{{ $ticket->subject }}</h3>
                                @if($latest)
                                    <p class="mt-2 line-clamp-2 text-sm font-bold leading-7 text-slate-500">
                                        <span class="text-slate-700">{{ $latest->author_type === \App\Models\Customer::class ? 'شما' : 'پشتیبانی' }}:</span>
                                        {{ \Illuminate\Support\Str::limit($latest->body, 150) }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-row items-center justify-between gap-4 border-t border-slate-100 pt-3 lg:w-48 lg:flex-col lg:items-end lg:border-0 lg:pt-0">
                                <span class="text-xs font-bold text-slate-400" dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span>
                                <span class="text-xs font-black {{ $hasUnread ? 'text-[#0069FF]' : 'text-slate-600' }}">{{ $nextAction }}</span>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-xs font-bold text-slate-500">
                            <span>{{ $ticket->category?->name ?? 'بدون دسته‌بندی' }}</span>
                            <span>اولویت: {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                            @if($ticket->virtualMachine)<span dir="ltr">{{ $ticket->virtualMachine->name }}</span>@endif
                        </div>
                    </a>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-[#EBF3FF] text-[#0069FF]"><svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke-linejoin="round"/></svg></span>
                    <p class="mt-4 font-black text-slate-950">{{ array_filter($filters) ? 'تیکتی با این فیلترها پیدا نشد' : 'هنوز تیکتی ثبت نکرده‌اید' }}</p>
                    <p class="mt-2 text-sm font-bold text-slate-500">{{ array_filter($filters) ? 'فیلترها را پاک کنید یا عبارت دیگری جستجو کنید.' : 'برای دریافت کمک، اولین درخواست پشتیبانی را ثبت کنید.' }}</p>
                    <a href="{{ array_filter($filters) ? route('customer.tickets.index', [], false) : route('customer.tickets.create', [], false) }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">{{ array_filter($filters) ? 'پاک کردن فیلترها' : 'ثبت اولین تیکت' }}</a>
                </div>
            @endforelse
        </div>

        <div class="mt-5">{{ $tickets->links() }}</div>
    </section>
</div>
@endsection
