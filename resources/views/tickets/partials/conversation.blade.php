<section class="ticket-conversation overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="conversation-heading">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-6">
        <div>
            <h2 id="conversation-heading" class="font-black text-slate-950">{{ $portal === 'admin' ? 'گفتگوی تیکت' : 'گفتگوی پشتیبانی' }}</h2>

        </div>
        <div class="flex flex-wrap items-center gap-1 text-xs font-bold">
            @if($messages->count() > 1)
                <a href="#latest-message" class="inline-flex min-h-10 items-center rounded-lg px-2 text-slate-600 hover:bg-slate-100">آخرین پیام ↓</a>
            @endif
            @if($canReply)
                <a href="#ticket-reply" class="inline-flex min-h-10 items-center rounded-lg px-2 text-[#0050D0] hover:bg-blue-50">نوشتن پاسخ</a>
            @endif
        </div>
    </header>
    <div class="ticket-thread" role="list" aria-label="پیام‌های گفتگو">
        @forelse($messages as $message)
            @php
                $isInternal = $message->type === \App\Models\TicketMessage::TYPE_INTERNAL;
                $isCustomer = $message->author_type === \App\Models\Customer::class;
                $isOwn = $portal === 'customer' ? $isCustomer : (!$isCustomer && $message->author_id === auth('admin')->id());
                $previous = $loop->first ? null : $messages[$loop->index - 1];
                $day = \App\Support\Jalali::format($message->created_at, 'Y/m/d');
                $startsDay = !$previous || $day !== \App\Support\Jalali::format($previous->created_at, 'Y/m/d');
                $continuesGroup = !$startsDay && !$isInternal
                    && $previous->type === $message->type
                    && $previous->author_type === $message->author_type
                    && $previous->author_id === $message->author_id;
                $side = $isInternal ? 'internal' : ($isOwn ? 'own' : 'other');
            @endphp
            @if($startsDay)
                <div class="ticket-day" role="presentation"><span dir="ltr">{{ $day }}</span></div>
            @endif
            <article @if($loop->last) id="latest-message" tabindex="-1" @endif role="listitem"
                @class(['ticket-message', "ticket-message-{$side}", 'ticket-message-continuation' => $continuesGroup])
                data-message-side="{{ $side }}">
                @unless($continuesGroup)
                    <header class="ticket-message-author">
                        @if($isInternal)
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
                        @endif
                        <span>{{ $isOwn ? 'شما' : ($message->author?->name ?? 'پشتیبانی') }}</span>
                        <span class="font-medium text-slate-600">{{ $isInternal ? 'یادداشت داخلی · فقط تیم پشتیبانی' : ($loop->first && $isCustomer ? 'درخواست اولیه مشتری' : ($isCustomer ? 'مشتری' : 'پشتیبانی')) }}</span>
                    </header>
                @endunless
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
                <footer class="mt-1 text-end text-[11px] tabular-nums text-slate-600">
                    <time datetime="{{ $message->created_at->toIso8601String() }}" title="{{ \App\Support\Jalali::format($message->created_at) }}" dir="ltr">{{ \App\Support\Jalali::format($message->created_at, 'H:i') }}</time>
                </footer>
            </article>
        @empty
            <p class="py-8 text-center text-sm text-slate-500">هنوز پیامی ثبت نشده است.</p>
        @endforelse
    </div>
    {{ $slot }}
</section>
