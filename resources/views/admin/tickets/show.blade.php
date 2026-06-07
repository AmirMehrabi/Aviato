@extends('layouts.admin')

@section('title', $ticket->number)

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black text-[#0069FF]" dir="ltr">{{ $ticket->number }}</p>
                        <h1 class="mt-2 text-2xl font-black text-slate-950">{{ $ticket->subject }}</h1>
                        <p class="mt-2 text-sm font-bold text-slate-500">{{ $ticket->customer->name }} · {{ $ticket->category?->name ?? 'بدون دسته‌بندی' }}</p>
                    </div>
                    <span class="rounded-lg bg-[#EBF3FF] px-3 py-1.5 text-xs font-black text-[#0069FF]">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                </div>
            </div>

            @foreach($ticket->messages as $message)
                <article class="rounded-2xl border {{ $message->type === \App\Models\TicketMessage::TYPE_INTERNAL ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-black/5 pb-3">
                        <div>
                            <p class="font-black text-slate-950">{{ $message->author?->name ?? 'سیستم' }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-black {{ $message->type === \App\Models\TicketMessage::TYPE_INTERNAL ? 'bg-amber-200 text-amber-900' : 'bg-slate-100 text-slate-700' }}">{{ $message->type === \App\Models\TicketMessage::TYPE_INTERNAL ? 'یادداشت داخلی' : ($message->author_type === \App\Models\Customer::class ? 'مشتری' : 'پشتیبانی') }}</span>
                    </div>
                    <div class="prose prose-slate mt-4 max-w-none text-sm leading-8">{!! $message->renderedBody() !!}</div>
                </article>
            @endforeach

            <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-black text-slate-950">پاسخ یا یادداشت</h2>
                    <label class="flex items-center gap-2 text-sm font-black text-amber-700"><input type="checkbox" name="internal" value="1" class="rounded border-slate-300"> داخلی</label>
                </div>
                <textarea name="body" rows="9" data-ticket-editor class="mt-4 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold"></textarea>
                <input type="file" name="attachments[]" multiple data-ticket-attachments accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="mt-4 w-full rounded-lg border border-dashed border-slate-300 px-4 py-4 text-sm font-bold">
                <button class="mt-4 rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت</button>
            </form>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مسیر رسیدگی</h2>
                <form method="POST" action="{{ route('admin.tickets.assignment', $ticket) }}" class="mt-4 space-y-3">
                    @csrf @method('PATCH')
                    <select name="ticket_category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"><option value="">دسته‌بندی</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($ticket->ticket_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select>
                    <select name="support_team_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"><option value="">تیم خودکار</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected($ticket->support_team_id === $team->id)>{{ $team->name }}</option>@endforeach</select>
                    <select name="assigned_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold"><option value="">Auto assignment</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($ticket->assigned_user_id === $agent->id)>{{ $agent->name }}</option>@endforeach</select>
                    <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-black text-white">به‌روزرسانی مسیر</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">وضعیت</h2>
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="mt-4 flex gap-2">
                    @csrf @method('PATCH')
                    <select name="status" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-bold">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select>
                    <button class="rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">ذخیره</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">مشتری و سرویس</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold text-slate-500">مشتری</dt><dd class="mt-1 font-black">{{ $ticket->customer->name }}</dd></div>
                    <div><dt class="font-bold text-slate-500">راه ارتباط</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->customer->email ?: $ticket->customer->phone }}</dd></div>
                    <div><dt class="font-bold text-slate-500">VM</dt><dd class="mt-1 font-black" dir="ltr">{{ $ticket->virtualMachine?->name ?? '—' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
@endsection
