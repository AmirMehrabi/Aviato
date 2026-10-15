<section class="ticket-conversation overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="conversation-heading">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-6">
        <div>
            <h2 id="conversation-heading" class="font-black text-slate-950">{{ $portal === 'admin' ? 'گفتگوی تیکت' : 'گفتگوی پشتیبانی' }}</h2>
            <p class="mt-1 text-xs text-slate-500">پیام‌ها از قدیمی به جدید</p>
        </div>
        <a href="#ticket-reply" class="rounded-lg px-3 py-2 text-sm font-bold text-[#0069FF] hover:bg-blue-50">{{ $portal === 'customer' && $ticket->status === \App\Models\Ticket::STATUS_CLOSED ? 'تیکت بسته شده' : 'نوشتن پاسخ ↓' }}</a>
    </header>
    <div class="ticket-thread" role="list" aria-label="پیام‌های گفتگو">
        @forelse($messages as $message)
            @php
                $isInternal = $message->type === \App\Models\TicketMessage::TYPE_INTERNAL;
                $isCustomer = $message->author_type === \App\Models\Customer::class;
                $isOwn = $portal === 'customer' ? $isCustomer : (!$isCustomer && $message->author_id === auth('admin')->id());
                $previous = $loop->first ? null : $messages[$loop->index - 1];
            @endphp
            @if(!$previous || !$message->created_at->isSameDay($previous->created_at))
                <div class="ticket-day"><span>{{ \App\Support\Jalali::format($message->created_at, 'Y/m/d') }}</span></div>
            @endif
            <article @if($loop->last) id="latest-message" @endif role="listitem" class="ticket-message {{ $isInternal ? 'ticket-message-internal' : ($isOwn ? 'ticket-message-own' : 'ticket-message-other') }}" data-message-side="{{ $isInternal ? 'internal' : ($isOwn ? 'own' : 'other') }}">
                <div class="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <span class="font-black text-slate-900">{{ $isOwn ? 'شما' : ($message->author?->name ?? 'پشتیبانی') }}</span>
                    <span class="text-slate-500">{{ $isInternal ? 'یادداشت داخلی · فقط تیم پشتیبانی' : ($isCustomer ? ($loop->first ? 'درخواست اولیه مشتری' : 'مشتری') : 'پشتیبانی') }}</span>
                </div>
                <div class="ticket-markdown text-sm leading-8 text-slate-800" dir="auto">{!! $message->renderedBody() !!}</div>
                @if($portal === 'customer')
                    @include('customer.tickets._attachments', ['message' => $message, 'ticket' => $ticket])
                @elseif($message->attachments->isNotEmpty())
                    @adminRoute('admin.tickets.attachments.show')
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($message->attachments as $attachment)
                            <a href="{{ route('admin.tickets.attachments.show', [$ticket, $attachment]) }}" class="ticket-file">↓ {{ $attachment->original_name }} <span dir="ltr">{{ $attachment->readableSize() }}</span></a>
                        @endforeach
                    </div>
                    @endadminRoute
                @endif
                <footer class="mt-3 text-end text-[11px] text-slate-500"><time datetime="{{ $message->created_at->toIso8601String() }}" dir="ltr">{{ \App\Support\Jalali::format($message->created_at) }}</time></footer>
            </article>
        @empty
            <p class="py-8 text-center text-sm text-slate-500">هنوز پیامی ثبت نشده است.</p>
        @endforelse
    </div>
</section>
