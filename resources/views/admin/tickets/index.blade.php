@extends('layouts.admin')

@section('title', 'تیکت‌ها')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ticketFilters', () => ({
        timer: null,

        fetchResults() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this._doFetch(true), 450);
        },

        fetchNow() {
            clearTimeout(this.timer);
            this._doFetch(true);
        },

        _doFetch(pushState) {
            const params = new URLSearchParams(new FormData(this.$refs.filters));
            const url = this.$refs.filters.action + '?' + params.toString();
            if (pushState) history.pushState({}, '', url);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => this._applyHtml(html));
        },

        _applyHtml(html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector('[x-ref="results"]');
            if (next) this.$refs.results.innerHTML = next.innerHTML;
            const input = doc.querySelector('input[name="search"]');
            if (input && this.$refs.filters.querySelector('input[name="search"]')) {
                this.$refs.filters.querySelector('input[name="search"]').value = input.value;
            }
            this.$refs.filters.querySelectorAll('select').forEach(sel => {
                const fresh = doc.querySelector('select[name="' + sel.name + '"]');
                if (fresh) sel.value = fresh.value;
            });
        },

        init() {
            window.addEventListener('popstate', () => {
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => this._applyHtml(html));
            });
        }
    }));
});
</script>

<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="ticketFilters"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-950">مرکز تیکت‌ها</h1>
            <p class="mt-2 text-sm text-slate-500">ورودی پشتیبانی، پاسخ‌ها، دسته‌بندی و مسئول رسیدگی.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.ticket-categories.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700">دسته‌بندی‌ها</a>
            <a href="{{ route('admin.support-teams.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700">تیم‌ها</a>
            <a href="{{ route('admin.tickets.create') }}" class="rounded-lg bg-[#0069FF] px-4 py-2.5 text-sm font-black text-white">تیکت جدید</a>
        </div>
    </div>

    <form x-ref="filters" @submit.prevent method="GET" action="{{ route('admin.tickets.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" @input="fetchResults()" placeholder="شماره، موضوع، مشتری" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select name="status" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">وضعیت</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="priority" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">اولویت</option>
                @foreach($priorities as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['priority'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="ticket_category_id" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">دسته‌بندی</option>
                @foreach($categories as $id => $name)
                    <option value="{{ $id }}" @selected(($filters['ticket_category_id'] ?? '') == $id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="assigned_user_id" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">مسئول</option>
                @foreach($agents as $id => $name)
                    <option value="{{ $id }}" @selected(($filters['assigned_user_id'] ?? '') == $id)>{{ $name }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.tickets.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</a>
        </div>
    </form>

    <section x-ref="results" class="mt-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-right">تیکت</th>
                            <th class="px-4 py-3 text-right">مشتری</th>
                            <th class="px-4 py-3 text-right">مسیر</th>
                            <th class="px-4 py-3 text-right">مسئول</th>
                            <th class="px-4 py-3 text-right">وضعیت</th>
                            <th class="px-4 py-3 text-right">آخرین فعالیت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-black text-slate-950">{{ $ticket->subject }}</a>
                                    <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $ticket->number }}</p>
                                </td>
                                <td class="px-4 py-4 font-bold text-slate-700">{{ $ticket->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $ticket->category?->name ?? '—' }}<p class="mt-1 text-xs text-slate-400">{{ $ticket->supportTeam?->name ?? 'بدون تیم' }}</p></td>
                                <td class="px-4 py-4 font-bold text-slate-700">{{ $ticket->assignee?->name ?? 'Auto / Unassigned' }}</td>
                                <td class="px-4 py-4"><span class="rounded-lg bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">{{ $statuses[$ticket->status] ?? $ticket->status }}</span></td>
                                <td class="px-4 py-4 text-xs font-bold text-slate-400" dir="ltr">{{ $ticket->last_activity_at?->format('Y-m-d H:i') ?? $ticket->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">تیکتی پیدا نشد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-5">{{ $tickets->links() }}</div>
    </section>
</div>
@endsection
