@extends('layouts.admin')

@section('title', $ticket->number)

@section('content')
@php
    $statusClasses = [
        \App\Models\Ticket::STATUS_OPEN => 'bg-blue-50 text-blue-700 ring-blue-200',
        \App\Models\Ticket::STATUS_PENDING => 'bg-amber-50 text-amber-800 ring-amber-200',
        \App\Models\Ticket::STATUS_ANSWERED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        \App\Models\Ticket::STATUS_CLOSED => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp
<div class="px-4 py-6 md:px-8 lg:px-10" data-ticket-seen-url="{{ route('admin.tickets.seen', $ticket) }}">
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.tickets.index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-black text-slate-600 transition-colors duration-150 hover:bg-white hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            بازگشت به تیکت‌ها
        </a>
        <span class="rounded-lg bg-slate-950 px-3 py-1.5 text-xs font-black text-white" dir="ltr">{{ $ticket->number }}</span>
    </div>

    <section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-[#031B4E] p-5 text-white sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg px-3 py-1.5 text-xs font-black ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                        <span class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-black text-white ring-1 ring-white/15">اولویت {{ $priorities[$ticket->priority] ?? $ticket->priority }}</span>
                    </div>
                    <h1 class="mt-4 text-2xl font-black leading-9 sm:text-3xl">{{ $ticket->subject }}</h1>
                    <p class="mt-2 text-sm font-bold leading-7 text-[#C7D4EA]">{{ $ticket->customer->name }} · {{ $ticket->category?->name ?? 'بدون دسته‌بندی' }}</p>
                </div>
                <div class="grid min-w-0 gap-2 sm:grid-cols-2 lg:w-80">
                    <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/10"><p class="text-[11px] font-black text-[#9DB4DC]">آخرین فعالیت</p><p class="mt-1 text-sm font-black" dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</p></div>
                    <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/10"><p class="text-[11px] font-black text-[#9DB4DC]">مسئول رسیدگی</p><p class="mt-1 truncate text-sm font-black">{{ $ticket->assignee?->name ?? 'بدون مسئول' }}</p></div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <main class="min-w-0 space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="conversation-heading">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="conversation-heading" class="font-black text-slate-950">گفتگوی تیکت</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">پاسخ‌های عمومی و یادداشت‌های داخلی به ترتیب زمان</p>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500">{{ $ticket->messages->count() }} پیام</span>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse($ticket->messages as $message)
                        @php
                            $isInternal = $message->type === \App\Models\TicketMessage::TYPE_INTERNAL;
                            $isCustomer = $message->author_type === \App\Models\Customer::class;
                            $surface = $isInternal ? 'border-amber-200 bg-amber-50' : ($isCustomer ? 'border-[#B8D6FF] bg-[#F5F9FF]' : 'border-emerald-200 bg-emerald-50');
                            $avatar = $isInternal ? 'bg-amber-600' : ($isCustomer ? 'bg-[#0069FF]' : 'bg-[#00A67E]');
                            $badge = $isInternal ? 'bg-amber-200 text-amber-900 ring-amber-300' : ($isCustomer ? 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]' : 'bg-emerald-100 text-emerald-700 ring-emerald-200');
                            $label = $isInternal ? 'فقط برای تیم پشتیبانی' : ($isCustomer ? ($loop->first ? 'درخواست اولیه مشتری' : 'پاسخ مشتری') : 'پاسخ پشتیبانی');
                        @endphp
                        <article class="rounded-2xl border p-4 {{ $surface }}">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-black text-white {{ $avatar }}">{{ mb_substr($message->author?->name ?? 'س', 0, 1) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div><p class="font-black text-slate-950">{{ $message->author?->name ?? 'سیستم' }}</p><p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ \App\Support\Jalali::format($message->created_at) }}</p></div>
                                        <span class="rounded-lg px-2.5 py-1 text-xs font-black ring-1 {{ $badge }}">{{ $label }}</span>
                                    </div>
                                    <div class="ticket-markdown mt-4 text-sm font-semibold leading-8 text-slate-700">{!! $message->renderedBody() !!}</div>
                                    @if($message->attachments->isNotEmpty())
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @foreach($message->attachments as $attachment)
                                                <a href="{{ route('admin.tickets.attachments.show', [$ticket, $attachment]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-black/10 bg-white px-3 py-2 text-xs font-black text-slate-700 transition-colors duration-150 hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19" stroke-linecap="round"/></svg>
                                                    {{ $attachment->original_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm font-bold text-slate-500">هنوز پیامی ثبت نشده است.</div>
                    @endforelse
                </div>
            </section>

            <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" x-data="{ mode: @js(old('mode', 'public')), submitting: false }" @submit="submitting = true">
                @csrf
                <div>
                    <h2 class="font-black text-slate-950">ادامه گفتگو</h2>
                    <p class="mt-1 text-xs font-bold text-slate-500">پیش از نوشتن، مشخص کنید پیام برای مشتری ارسال می‌شود یا فقط داخل تیم می‌ماند.</p>
                </div>

                <fieldset class="mt-4">
                    <legend class="sr-only">نوع پیام</legend>
                    <div class="grid gap-2 rounded-2xl bg-slate-100 p-1.5 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl px-4 py-3 transition-colors duration-150" :class="mode === 'public' ? 'bg-white text-[#0069FF] shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                            <input type="radio" name="mode" value="public" x-model="mode" class="sr-only">
                            <span class="block text-sm font-black">پاسخ به مشتری</span><span class="mt-1 block text-xs font-bold opacity-75">مشتری پیام و پیوست‌ها را می‌بیند.</span>
                        </label>
                        <label class="cursor-pointer rounded-xl px-4 py-3 transition-colors duration-150" :class="mode === 'internal' ? 'bg-amber-50 text-amber-800 shadow-sm ring-1 ring-amber-200' : 'text-slate-600 hover:text-slate-900'">
                            <input type="radio" name="mode" value="internal" x-model="mode" class="sr-only">
                            <span class="block text-sm font-black">یادداشت داخلی</span><span class="mt-1 block text-xs font-bold opacity-75">فقط مدیران پشتیبانی آن را می‌بینند.</span>
                        </label>
                    </div>
                </fieldset>

                @if($errors->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700" role="alert">پیام ثبت نشد. متن و فایل‌های پیوست را بررسی کنید.</div>@endif
                <label for="admin-ticket-reply" class="mt-4 block text-sm font-black text-slate-800">متن پیام</label>
                <textarea id="admin-ticket-reply" name="body" rows="9" required data-ticket-editor class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold">{{ old('body') }}</textarea>
                <label class="mt-4 flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition-colors duration-150 hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-[#0069FF] shadow-sm"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19" stroke-linecap="round"/></svg></span>
                    <span><span class="block text-sm font-black text-slate-800">افزودن فایل</span><span class="mt-1 block text-xs font-bold text-slate-500">حداکثر ۵ فایل، هر فایل ۲۰ مگابایت</span></span>
                    <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="sr-only">
                </label>
                <button :disabled="submitting" class="mt-4 min-h-11 rounded-xl px-6 py-3 text-sm font-black text-white transition-colors duration-150 disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF] active:scale-[0.96]" :class="mode === 'internal' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-[#0069FF] hover:bg-[#0050D0]'" x-text="submitting ? 'در حال ثبت…' : (mode === 'internal' ? 'ثبت یادداشت داخلی' : 'ارسال پاسخ به مشتری')">ارسال پاسخ به مشتری</button>
            </form>
        </main>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مسیر رسیدگی</h2>
                <p class="mt-1 text-xs font-bold text-slate-500">دسته‌بندی، تیم و مسئول پاسخ‌گو</p>
                <form method="POST" action="{{ route('admin.tickets.assignment', $ticket) }}" class="mt-4 space-y-3">
                    @csrf @method('PATCH')
                    <label class="block text-xs font-black text-slate-500">دسته‌بندی<select name="ticket_category_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">بدون دسته‌بندی</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($ticket->ticket_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-black text-slate-500">تیم<select name="support_team_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">انتخاب خودکار تیم</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected($ticket->support_team_id === $team->id)>{{ $team->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-black text-slate-500">مسئول<select name="assigned_user_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">بدون مسئول / تخصیص خودکار</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($ticket->assigned_user_id === $agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
                    <button class="min-h-11 w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white">ذخیره مسیر رسیدگی</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">وضعیت</h2>
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="mt-4 flex gap-2">
                    @csrf @method('PATCH')
                    <label for="ticket-status" class="sr-only">وضعیت تیکت</label>
                    <select id="ticket-status" name="status" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select>
                    <button class="rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ذخیره وضعیت</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مشتری و سرویس</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold text-slate-500">مشتری</dt><dd class="mt-1 font-black"><a href="{{ route('admin.customers.show', $ticket->customer) }}" class="text-[#0069FF] hover:underline">{{ $ticket->customer->name }}</a></dd></div>
                    <div><dt class="font-bold text-slate-500">راه ارتباط</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->customer->email ?: $ticket->customer->phone }}</dd></div>
                    <div><dt class="font-bold text-slate-500">ماشین مرتبط</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->virtualMachine?->name ?? '—' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
@endsection
