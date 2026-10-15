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
                <span class="rounded-full px-2.5 py-1 font-bold ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
            </div>
            <h1 class="mt-3 break-words text-xl font-black leading-8 text-slate-950">{{ $ticket->subject }}</h1>
            <p class="mt-2 text-xs text-slate-500">{{ $ticket->category?->name ?? 'پشتیبانی' }} · آخرین فعالیت <span dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span></p>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="min-w-0 space-y-5">
            @include('tickets.partials.conversation', ['messages' => $publicMessages, 'portal' => 'customer'])

            @if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
                <form id="ticket-reply" method="POST" action="{{ route('customer.tickets.reply', $ticket, false) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="font-black text-slate-950">ارسال پاسخ</h2>
                            <p class="mt-1 text-xs font-bold text-slate-500">می‌توانید تصویر، PDF، فایل لاگ، آرشیو یا سندهای مرتبط را پیوست کنید.</p>
                        </div>
                        <span class="w-fit rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500">حداکثر ۵ فایل، هر فایل ۲۰MB</span>
                    </div>
                    @if($errors->any())
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold leading-7 text-red-700" role="alert">پاسخ ارسال نشد. متن پاسخ و فایل‌های پیوست را بررسی کنید.</div>
                    @endif
                    @if($errors->any())<ul class="mt-2 list-inside list-disc text-sm leading-7 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                <div class="mt-4">
                        <label for="customer-ticket-reply" class="mb-2 block text-sm font-black text-slate-800">متن پاسخ</label>
                        <textarea id="customer-ticket-reply" name="body" rows="8" required data-ticket-editor class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold" aria-describedby="customer-ticket-reply-help">{{ old('body') }}</textarea>
                        <p id="customer-ticket-reply-help" class="mt-2 text-xs font-bold text-slate-500">شرح دقیق و اطلاعات لازم برای بررسی سریع‌تر را بنویسید.</p>
                    </div>
                    <label class="mt-4 block text-xs font-bold text-slate-500">پیوست (اختیاری) · حداکثر ۵ فایل، هر فایل ۲۰ مگابایت
                    <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="mt-2 block w-full text-xs text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                </label>
                    <button :disabled="submitting" class="mt-4 min-h-11 rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition-colors duration-150 hover:bg-[#0050D0] disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] active:scale-[0.96]" x-text="submitting ? 'در حال ارسال…' : 'ارسال پاسخ'">ارسال پاسخ</button>
                </form>
            @else
                <section id="ticket-reply" class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
                    <p class="font-black text-slate-950">این تیکت بسته شده است.</p>
                    <p class="mt-2 text-sm font-bold text-slate-500">برای درخواست جدید، یک تیکت جدید ثبت کنید.</p>
                    <a href="{{ route('customer.tickets.create', [], false) }}" class="mt-4 inline-flex rounded-xl bg-[#0069FF] px-4 py-3 text-sm font-bold text-white">ثبت تیکت جدید</a>
                </section>
            @endif
        </main>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="font-black text-slate-950">وضعیت تیکت</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                    <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $priorityClasses[$ticket->priority] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</span>
                </div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="font-bold text-slate-500">دسته‌بندی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->category?->name ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-500">تیم پشتیبانی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->supportTeam?->name ?? '—' }}</dd></div>
                    <div><dt class="font-bold text-slate-500">مسئول رسیدگی</dt><dd class="mt-1 font-black text-slate-950">{{ $ticket->assignee?->name ?? 'در صف تخصیص' }}</dd></div>
                </dl>
            </section>

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
