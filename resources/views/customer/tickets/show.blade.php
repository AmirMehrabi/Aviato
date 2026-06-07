@extends('customer.layout')

@section('title', $ticket->number)
@section('header_title', $ticket->subject)
@section('header_subtitle', 'شماره '.$ticket->number.' · '.($statuses[$ticket->status] ?? $ticket->status))
@section('breadcrumbs')
    <a href="{{ route('customer.tickets.index', [], false) }}" class="transition hover:text-[#0069FF]">تیکت‌ها</a>
    <svg class="size-3.5 rotate-180 text-slate-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
    <span class="truncate text-slate-700" dir="ltr">{{ $ticket->number }}</span>
@endsection
@php($activeNav = 'tickets')

@section('content')
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
    <section class="space-y-4">
        @foreach($ticket->messages->where('type', \App\Models\TicketMessage::TYPE_REPLY) as $message)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <p class="font-black text-slate-950">{{ $message->author?->name ?? 'سیستم' }}</p>
                        <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</p>
                    </div>
                    <span class="rounded-lg px-2.5 py-1 text-xs font-black {{ $message->author_type === \App\Models\Customer::class ? 'bg-[#EBF3FF] text-[#0069FF]' : 'bg-emerald-50 text-emerald-700' }}">{{ $message->author_type === \App\Models\Customer::class ? 'مشتری' : 'پشتیبانی' }}</span>
                </div>
                <div class="prose prose-slate mt-4 max-w-none text-sm leading-8">{!! $message->renderedBody() !!}</div>
                @if($message->attachments->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($message->attachments as $attachment)
                            <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-600">{{ $attachment->original_name }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach

        @if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
            <form method="POST" action="{{ route('customer.tickets.reply', $ticket, false) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                @csrf
                <h2 class="font-black text-slate-950">ارسال پاسخ</h2>
                <textarea name="body" rows="8" data-ticket-editor class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold"></textarea>
                <input type="file" name="attachments[]" multiple class="mt-4 w-full rounded-xl border border-dashed border-slate-300 px-4 py-4 text-sm font-bold">
                <button class="mt-4 rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ارسال پاسخ</button>
            </form>
        @endif
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <h2 class="font-black text-slate-950">جزئیات</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="font-bold text-slate-500">وضعیت</dt><dd class="font-black">{{ $statuses[$ticket->status] ?? $ticket->status }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="font-bold text-slate-500">دسته‌بندی</dt><dd class="font-black">{{ $ticket->category?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="font-bold text-slate-500">اولویت</dt><dd class="font-black">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="font-bold text-slate-500">سرویس</dt><dd class="font-black" dir="ltr">{{ $ticket->virtualMachine?->name ?? '—' }}</dd></div>
            </dl>
        </section>
        @if($ticket->status === \App\Models\Ticket::STATUS_CLOSED)
            <form method="POST" action="{{ route('customer.tickets.reopen', $ticket, false) }}">@csrf @method('PATCH')<button class="w-full rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">باز کردن دوباره</button></form>
        @else
            <form method="POST" action="{{ route('customer.tickets.close', $ticket, false) }}">@csrf @method('PATCH')<button class="w-full rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">بستن تیکت</button></form>
        @endif
    </aside>
</div>
@endsection
