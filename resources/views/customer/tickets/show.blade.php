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
    $firstMessage = $publicMessages->first();
    $responses = $publicMessages->slice(1);
@endphp

@section('content')
<div class="space-y-5">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
        <div class="border-b border-slate-200 bg-[#031B4E] p-5 text-white">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-black text-white ring-1 ring-white/15" dir="ltr">{{ $ticket->number }}</span>
                        <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                        <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $priorityClasses[$ticket->priority] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</span>
                    </div>
                    <h1 class="mt-4 text-2xl font-black leading-9">{{ $ticket->subject }}</h1>
                    <p class="mt-2 text-sm font-bold leading-7 text-[#C7D4EA]">
                        {{ $ticket->category?->name ?? 'بدون دسته‌بندی' }} · آخرین فعالیت
                        <span dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span>
                    </p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:w-80">
                    <div class="rounded-xl border border-white/10 bg-white/10 p-3">
                        <p class="text-[11px] font-black text-[#9DB4DC]">مسئول رسیدگی</p>
                        <p class="mt-1 truncate text-sm font-black">{{ $ticket->assignee?->name ?? 'در صف تخصیص' }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/10 p-3">
                        <p class="text-[11px] font-black text-[#9DB4DC]">تیم</p>
                        <p class="mt-1 truncate text-sm font-black">{{ $ticket->supportTeam?->name ?? 'پشتیبانی' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-black text-slate-500">ثبت شده</p>
                <p class="mt-2 text-sm font-black text-slate-950" dir="ltr">{{ \App\Support\Jalali::format($ticket->created_at) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-black text-slate-500">آخرین پاسخ مشتری</p>
                <p class="mt-2 text-sm font-black text-slate-950" dir="ltr">{{ $ticket->last_customer_reply_at ? \App\Support\Jalali::format($ticket->last_customer_reply_at) : '—' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-black text-slate-500">آخرین پاسخ پشتیبانی</p>
                <p class="mt-2 text-sm font-black text-slate-950" dir="ltr">{{ $ticket->last_admin_reply_at ? \App\Support\Jalali::format($ticket->last_admin_reply_at) : '—' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-black text-slate-500">سرویس مرتبط</p>
                <p class="mt-2 truncate text-sm font-black text-slate-950" dir="ltr">{{ $ticket->virtualMachine?->name ?? 'بدون سرویس' }}</p>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-5">
            @if($firstMessage)
                <section class="rounded-2xl border border-[#B8D6FF] bg-[#F8FBFF] p-5 shadow-sm shadow-[#0069FF]/10">
                    <div class="flex flex-col gap-3 border-b border-[#B8D6FF] pb-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#0069FF] text-sm font-black text-white">{{ mb_substr($firstMessage->author?->name ?? 'م', 0, 1) }}</span>
                            <div>
                                <p class="font-black text-slate-950">درخواست اولیه</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $firstMessage->author?->name ?? 'مشتری' }} · <span dir="ltr">{{ \App\Support\Jalali::format($firstMessage->created_at) }}</span></p>
                            </div>
                        </div>
                        <span class="w-fit rounded-lg bg-white px-3 py-1.5 text-xs font-black text-[#0069FF] ring-1 ring-[#B8D6FF]">Ticket body</span>
                    </div>
                    <div class="ticket-markdown mt-5 text-sm font-semibold leading-8 text-slate-700">{!! $firstMessage->renderedBody() !!}</div>
                    @include('customer.tickets._attachments', ['message' => $firstMessage, 'ticket' => $ticket])
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-black text-slate-950">گفتگو</h2>
                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500">{{ $responses->count() }} پاسخ</span>
                </div>

                <div class="mt-5 space-y-5">
                    @forelse($responses as $message)
                        @php
                            $isCustomer = $message->author_type === \App\Models\Customer::class;
                            $bubbleClass = $isCustomer ? 'border-[#B8D6FF] bg-[#F8FBFF]' : 'border-emerald-200 bg-emerald-50';
                            $avatarClass = $isCustomer ? 'bg-[#0069FF]' : 'bg-[#00A67E]';
                            $roleBadge = $isCustomer ? 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]' : 'bg-emerald-100 text-emerald-700 ring-emerald-200';
                        @endphp
                        <article class="rounded-2xl border p-4 {{ $bubbleClass }}">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-black text-white {{ $avatarClass }}">{{ mb_substr($message->author?->name ?? 'س', 0, 1) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="font-black text-slate-950">{{ $message->author?->name ?? 'سیستم' }}</p>
                                            <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ \App\Support\Jalali::format($message->created_at) }}</p>
                                        </div>
                                        <span class="rounded-lg px-2.5 py-1 text-xs font-black ring-1 {{ $roleBadge }}">{{ $isCustomer ? 'پاسخ مشتری' : 'پاسخ پشتیبانی' }}</span>
                                    </div>
                                    <div class="ticket-markdown mt-4 text-sm font-semibold leading-8 text-slate-700">{!! $message->renderedBody() !!}</div>
                                    @include('customer.tickets._attachments', ['message' => $message, 'ticket' => $ticket])
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm font-bold text-slate-500">هنوز پاسخی ثبت نشده است.</div>
                    @endforelse
                </div>
            </section>

            @if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
                <form method="POST" action="{{ route('customer.tickets.reply', $ticket, false) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="font-black text-slate-950">ارسال پاسخ</h2>
                            <p class="mt-1 text-xs font-bold text-slate-500">می‌توانید تصویر، PDF، فایل لاگ، آرشیو یا سندهای مرتبط را پیوست کنید.</p>
                        </div>
                        <span class="w-fit rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500">حداکثر ۵ فایل، هر فایل ۲۰MB</span>
                    </div>
                    <div class="mt-4">
                        <textarea name="body" rows="8" data-ticket-editor class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold"></textarea>
                    </div>
                    <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-7 text-center transition hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
                        <span class="grid size-12 place-items-center rounded-xl bg-white text-[#0069FF] shadow-sm">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.48-8.49" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span class="mt-3 text-sm font-black text-slate-800">افزودن پیوست</span>
                        <span class="mt-1 text-xs font-bold text-slate-500">PDF، تصویر، فایل متنی، ZIP و سایر فایل‌های لازم برای بررسی پشتیبانی</span>
                        <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="sr-only">
                    </label>
                    <button class="mt-4 rounded-xl bg-[#0069FF] px-6 py-3 text-sm font-black text-white shadow-sm shadow-[#0069FF]/20 transition hover:bg-[#0050D0]">ارسال پاسخ</button>
                </form>
            @else
                <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
                    <p class="font-black text-slate-950">این تیکت بسته شده است.</p>
                    <p class="mt-2 text-sm font-bold text-slate-500">برای ادامه گفتگو، تیکت را دوباره باز کنید.</p>
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

            @if($ticket->status === \App\Models\Ticket::STATUS_CLOSED)
                <form method="POST" action="{{ route('customer.tickets.reopen', $ticket, false) }}">@csrf @method('PATCH')<button class="w-full rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">باز کردن دوباره تیکت</button></form>
            @else
                <form method="POST" action="{{ route('customer.tickets.close', $ticket, false) }}">@csrf @method('PATCH')<button class="w-full rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">بستن تیکت</button></form>
            @endif
        </aside>
    </div>
</div>
@endsection
