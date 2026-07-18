@extends('customer.layout')

@section('title', 'تیکت‌ها')
@section('header_title', 'تیکت‌های پشتیبانی')
@section('header_subtitle', 'درخواست‌های پشتیبانی، پاسخ‌ها و وضعیت پیگیری')
@section('breadcrumbs')
    <span class="truncate text-slate-700">تیکت‌ها</span>
@endsection
@php($activeNav = 'tickets')

@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-lg font-black text-slate-950">درخواست‌ها</h2>
            <p class="mt-1 text-sm text-slate-500">برای هر موضوع یک تیکت جداگانه بسازید تا مسیر پیگیری شفاف بماند.</p>
        </div>
        <a href="{{ route('customer.tickets.create', [], false) }}" class="inline-flex w-fit rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">تیکت جدید</a>
    </div>

    <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_220px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold outline-none focus:border-[#0069FF]" placeholder="شماره یا موضوع تیکت">
        <select name="status" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold outline-none focus:border-[#0069FF]">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">فیلتر</button>
    </form>

    <div class="mt-5 space-y-3">
        @forelse($tickets as $ticket)
            <a href="{{ route('customer.tickets.show', $ticket, false) }}" class="block rounded-2xl border border-slate-200 p-4 transition hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg bg-slate-950 px-2.5 py-1 text-xs font-black text-white" dir="ltr">{{ $ticket->number }}</span>
                            <span class="rounded-lg bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">{{ \App\Models\Ticket::priorities()[$ticket->priority] ?? $ticket->priority }}</span>
                        </div>
                        <h3 class="mt-3 truncate text-base font-black text-slate-950">{{ $ticket->subject }}</h3>
                        <p class="mt-1 text-sm font-bold text-slate-500">{{ $ticket->category?->name ?? 'بدون دسته‌بندی' }} @if($ticket->virtualMachine) · <span dir="ltr">{{ $ticket->virtualMachine->name }}</span> @endif</p>
                    </div>
                    <p class="shrink-0 text-xs font-bold text-slate-400" dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</p>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                <p class="font-black text-slate-950">هنوز تیکتی ندارید.</p>
                <a href="{{ route('customer.tickets.create', [], false) }}" class="mt-4 inline-flex rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ثبت اولین تیکت</a>
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $tickets->links() }}</div>
</section>
@endsection
