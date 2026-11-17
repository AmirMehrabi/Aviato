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

@adminRoute('admin.tickets.index')
<a href="{{ route('admin.tickets.index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-black text-slate-600 transition-colors duration-150 hover:bg-white hover:text-[#0069FF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
            <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            بازگشت به تیکت‌ها
        </a>
@endadminRoute

        <span class="rounded-lg bg-slate-950 px-3 py-1.5 text-xs font-black text-white" dir="ltr">{{ $ticket->number }}</span>
    </div>

    <section class="mb-5 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-600">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</span>
                <span class="font-bold text-slate-600">{{ $ticket->assignee?->name ?? 'بدون مسئول' }}</span>
                <span class="rounded-full px-2.5 py-1 font-bold ring-1 {{ $statusClasses[$ticket->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
            </div>
            <h1 class="mt-3 break-words text-xl font-black leading-8 text-slate-950">{{ $ticket->subject }}</h1>
            <p class="mt-2 text-xs text-slate-500">{{ $ticket->customer->name }} · {{ $ticket->category?->name ?? 'پشتیبانی' }} · آخرین فعالیت <span dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span></p>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <main class="min-w-0 space-y-4">
            @component('tickets.partials.conversation', [
                'ticket' => $ticket,
                'messages' => $ticket->messages->values(),
                'portal' => 'admin',
                'canReply' => \App\Support\AdminAccess::canVisit(auth('admin')->user(), 'admin.tickets.reply'),
            ])
                @adminRoute('admin.tickets.reply')
                    @php($draftMode = old('mode') === 'internal' ? 'internal' : 'public')
                    <div id="ticket-reply" class="border-t border-slate-200" x-data="{ mode: @js($draftMode), submitting: false }">
                        <fieldset class="px-4 pt-4 sm:px-5" :disabled="submitting">
                            <legend class="sr-only">نوع پیام</legend>
                            <div class="flex w-fit max-w-full flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                                @foreach(['public' => 'پاسخ به مشتری', 'internal' => 'یادداشت داخلی'] as $replyMode => $label)
                                    <label class="cursor-pointer rounded-lg px-3 py-2 text-sm font-bold text-slate-600 focus-within:ring-2 focus-within:ring-blue-500 has-checked:bg-white has-checked:text-blue-700 has-checked:shadow-sm">
                                        <input type="radio" value="{{ $replyMode }}" name="ticket-reply-mode" x-model="mode" @checked($draftMode === $replyMode) class="sr-only">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        @foreach(['public', 'internal'] as $replyMode)
                            <div x-show="mode === '{{ $replyMode }}'" @style(['display: none' => $draftMode !== $replyMode])>
                                @include('tickets.partials.reply-form', ['portal' => 'admin', 'mode' => $replyMode, 'restoreDraft' => $draftMode === $replyMode])
                            </div>
                        @endforeach
                    </div>
                @endadminRoute
            @endcomponent

            @include('admin.tickets._activity')
        </main>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مسیر رسیدگی</h2>
                <p class="mt-1 text-xs font-bold text-slate-500">دسته‌بندی، تیم و مسئول پاسخ‌گو</p>

@adminRoute('admin.tickets.assignment')
<form method="POST" action="{{ route('admin.tickets.assignment', $ticket) }}" class="mt-4 space-y-3">
                    @csrf @method('PATCH')
                    <label class="block text-xs font-black text-slate-500">دسته‌بندی<select name="ticket_category_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">بدون دسته‌بندی</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($ticket->ticket_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-black text-slate-500">تیم<select name="support_team_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">انتخاب خودکار تیم</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected($ticket->support_team_id === $team->id)>{{ $team->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-black text-slate-500">مسئول<select name="assigned_user_id" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-900"><option value="">بدون مسئول / تخصیص خودکار</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($ticket->assigned_user_id === $agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
                    <button class="min-h-11 w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white">ذخیره مسیر رسیدگی</button>
                </form>
@endadminRoute

            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">وضعیت</h2>

@adminRoute('admin.tickets.status')
<form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="mt-4 flex gap-2">
                    @csrf @method('PATCH')
                    <label for="ticket-status" class="sr-only">وضعیت تیکت</label>
                    <select id="ticket-status" name="status" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select>
                    <button class="rounded-xl bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ذخیره وضعیت</button>
                </form>
@endadminRoute

            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مشتری و سرویس</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold text-slate-500">مشتری</dt><dd class="mt-1 font-black">
@adminRoute('admin.customers.show')
<a href="{{ route('admin.customers.show', $ticket->customer) }}" class="text-[#0069FF] hover:underline">{{ $ticket->customer->name }}</a>
@endadminRoute
</dd></div>
                    <div><dt class="font-bold text-slate-500">راه ارتباط</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->customer->email ?: $ticket->customer->phone }}</dd></div>
                    <div><dt class="font-bold text-slate-500">ماشین مرتبط</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->virtualMachine?->name ?? '—' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
@endsection
