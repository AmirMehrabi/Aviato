@extends('customer.layout')

@section('title', $ticket->number)
@section('header_title', $ticket->number)
@section('header_subtitle', $ticket->subject)
@section('breadcrumbs')
    <a href="{{ route('customer.tickets.index', [], false) }}" class="transition hover:text-[#0069FF]">تیکت‌ها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
    <span class="truncate text-slate-700" dir="ltr">{{ $ticket->number }}</span>
@endsection
@php
    $activeNav = 'tickets';
    $statusClasses = [
        \App\Models\Ticket::STATUS_OPEN => 'bg-blue-50 text-blue-700 ring-blue-200',
        \App\Models\Ticket::STATUS_PENDING => 'bg-amber-50 text-amber-700 ring-amber-200',
        \App\Models\Ticket::STATUS_ANSWERED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        \App\Models\Ticket::STATUS_CLOSED => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $priorityClasses = [
        \App\Models\Ticket::PRIORITY_LOW => 'bg-slate-100 text-slate-600 ring-slate-200',
        \App\Models\Ticket::PRIORITY_NORMAL => 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]',
        \App\Models\Ticket::PRIORITY_HIGH => 'bg-orange-50 text-orange-700 ring-orange-200',
        \App\Models\Ticket::PRIORITY_URGENT => 'bg-red-50 text-red-700 ring-red-200',
    ];
    $statusLabel = $ticket->status === \App\Models\Ticket::STATUS_PENDING ? 'در انتظار پاسخ شما' : ($statuses[$ticket->status] ?? $ticket->status);
    $publicMessages = $ticket->messages->where('type', \App\Models\TicketMessage::TYPE_REPLY)->values();
@endphp

@section('content')
<div class="space-y-5" data-ticket-seen-url="{{ route('customer.tickets.seen', $ticket, false) }}">
    @if(session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    <a href="{{ route('customer.tickets.index', [], false) }}" class="inline-flex min-h-10 items-center text-sm font-bold text-slate-600 hover:text-[#0069FF]">→ بازگشت به تیکت‌ها</a>
    <section class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-bold text-slate-500" dir="ltr">{{ $ticket->number }}</span>
                <span class="rounded-full px-2.5 py-1 font-bold ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statusLabel }}</span>
            </div>
            <h1 class="mt-3 break-words text-xl font-black leading-8 text-slate-950">{{ $ticket->subject }}</h1>
            <p class="mt-2 text-xs text-slate-500">{{ $ticket->category?->name ?? 'پشتیبانی' }} · آخرین فعالیت <span dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span></p>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="min-w-0">
            @component('tickets.partials.conversation', [
                'ticket' => $ticket,
                'messages' => $publicMessages,
                'portal' => 'customer',
                'canReply' => $ticket->status !== \App\Models\Ticket::STATUS_CLOSED,
            ])
                @if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
                    @include('tickets.partials.reply-form', ['portal' => 'customer', 'mode' => 'public', 'restoreDraft' => true])
                @else
                    <div class="border-t border-slate-200 bg-slate-50 px-4 py-5 text-center">
                        <p class="text-sm font-bold text-slate-900">این تیکت بسته شده است.</p>
                        <a href="{{ route('customer.tickets.create', [], false) }}" class="mt-2 inline-flex min-h-10 items-center text-sm font-bold text-[#0050D0] hover:underline">ثبت تیکت جدید</a>
                    </div>
                @endif
            @endcomponent
        </main>

        <aside class="space-y-4">
            <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <summary class="cursor-pointer text-sm font-black text-slate-950">وضعیت تیکت</summary>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statusLabel }}</span>
                    <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $priorityClasses[$ticket->priority] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</span>
                </div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="font-bold text-slate-500">دسته‌بندی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->category?->name ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-500">تیم پشتیبانی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->supportTeam?->name ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-500">مسئول رسیدگی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->assignee?->name ?? 'در صف تخصیص' }}</dd></div>
                </dl>
            </details>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">سرویس مرتبط</h2>
                @if($ticket->virtualMachine)
                    <div class="mt-4 rounded-xl bg-slate-50 p-4">
                        <p class="truncate text-sm font-black text-slate-950" dir="ltr">{{ $ticket->virtualMachine->name }}</p>
                        <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $ticket->virtualMachine->ip_address ?: 'no-ip' }}</p>
                        <a href="{{ route('customer.servers.show', $ticket->virtualMachine, false) }}" class="mt-4 inline-flex w-full justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">مشاهده سرویس</a>
                    </div>
                @else
                    <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm font-bold leading-7 text-slate-500">برای این تیکت سرویس خاصی انتخاب نشده است.</p>
                @endif
            </section>

            @if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
                <form method="POST" action="{{ route('customer.tickets.close', $ticket, false) }}">@csrf @method('PATCH')<button class="w-full rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">بستن تیکت</button></form>
            @endif
        </aside>
    </div>
</div>
@endsection
