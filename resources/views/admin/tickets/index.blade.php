@extends('layouts.admin')

@section('title', 'تیکت‌ها')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ticketFilters', () => ({
        timer: null,
        loading: false,
        error: '',
        announcement: '',

        fetchResults() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this._doFetch(true), 450);
        },

        fetchNow() {
            clearTimeout(this.timer);
            this._doFetch(true);
        },

        async _doFetch(pushState) {
            const params = new URLSearchParams(new FormData(this.$refs.filters));
            const url = this.$refs.filters.action + '?' + params.toString();
            if (pushState) history.pushState({}, '', url);
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Request failed');
                this._applyHtml(await response.text());
            } catch (error) {
                this.error = 'بارگذاری تیکت‌ها انجام نشد. اتصال را بررسی و دوباره تلاش کنید.';
            } finally {
                this.loading = false;
            }
        },

        _applyHtml(html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector('[x-ref="results"]');
            if (next) this.$refs.results.innerHTML = next.innerHTML;
            this.announcement = `${next?.dataset.resultsCount || 0} تیکت نمایش داده شد.`;
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
                this._doFetch(false);
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

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="خلاصه صف پشتیبانی">
        @foreach([
            ['label' => 'در حال پیگیری', 'value' => $ticketCounts['open'], 'url' => route('admin.tickets.index', ['status' => \App\Models\Ticket::STATUS_OPEN])],
            ['label' => 'پاسخ جدید مشتری', 'value' => $ticketCounts['unread'], 'url' => route('admin.tickets.index', ['attention' => 'unread'])],
            ['label' => 'فوری', 'value' => $ticketCounts['urgent'], 'url' => route('admin.tickets.index', ['priority' => \App\Models\Ticket::PRIORITY_URGENT])],
            ['label' => 'بدون مسئول', 'value' => $ticketCounts['unassigned'], 'url' => route('admin.tickets.index', ['attention' => 'unassigned'])],
        ] as $stat)
            <a href="{{ $stat['url'] }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-[border-color,box-shadow,transform] duration-150 hover:-translate-y-0.5 hover:border-[#B8D6FF] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                <span class="block text-2xl font-black text-slate-950">{{ number_format($stat['value']) }}</span>
                <span class="mt-1 block text-xs font-black text-slate-500">{{ $stat['label'] }}</span>
            </a>
        @endforeach
    </section>

    <form x-ref="filters" @submit.prevent method="GET" action="{{ route('admin.tickets.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <label for="admin-ticket-search" class="sr-only">جستجوی تیکت‌ها</label>
                <input id="admin-ticket-search" name="search" value="{{ $filters['search'] ?? '' }}" @input="fetchResults()" placeholder="شماره، موضوع، مشتری" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8D6FF]">
            </div>
            <label for="admin-ticket-attention" class="sr-only">صف کاری</label>
            <select id="admin-ticket-attention" name="attention" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه صف‌ها</option>
                <option value="unread" @selected(($filters['attention'] ?? '') === 'unread')>پاسخ جدید مشتری</option>
                <option value="unassigned" @selected(($filters['attention'] ?? '') === 'unassigned')>بدون مسئول</option>
            </select>
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

    <div x-show="error" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
        <span x-text="error"></span>
        <button type="button" @click="fetchNow()" class="mr-2 font-black underline decoration-2 underline-offset-4">تلاش دوباره</button>
    </div>
    <p class="sr-only" role="status" aria-live="polite" x-text="announcement"></p>

    <section x-ref="results" class="relative mt-6" data-results-count="{{ $tickets->total() }}" :aria-busy="loading.toString()">
        <div x-show="loading" x-cloak class="absolute inset-x-0 top-0 z-20 h-1 overflow-hidden rounded-full bg-[#D7E8FF]" aria-hidden="true"><span class="block h-full w-1/3 animate-pulse rounded-full bg-[#0069FF]"></span></div>

        <div class="space-y-3 md:hidden">
            @forelse($tickets as $ticket)
                @php($hasUnread = $ticket->unread_replies_count > 0)
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="relative block overflow-hidden rounded-2xl border bg-white p-4 shadow-sm {{ $hasUnread ? 'border-[#80B6FF]' : 'border-slate-200' }}">
                    @if($hasUnread)<span class="absolute inset-y-0 start-0 w-1 bg-[#0069FF]" aria-hidden="true"></span>@endif
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg bg-slate-950 px-2 py-1 text-[11px] font-black text-white" dir="ltr">{{ $ticket->number }}</span>
                        <x-admin.status-badge :value="$ticket->status" :label="$statuses[$ticket->status] ?? $ticket->status" />
                        @if($hasUnread)<span class="rounded-lg bg-[#0069FF] px-2 py-1 text-[11px] font-black text-white">{{ $ticket->unread_replies_count }} پاسخ جدید</span>@endif
                    </div>
                    <h2 class="mt-3 font-black leading-7 text-slate-950">{{ $ticket->subject }}</h2>
                    <p class="mt-1 text-sm font-bold text-slate-600">{{ $ticket->customer?->name ?? '—' }}</p>
                    @if($ticket->latestPublicMessage)<p class="mt-2 line-clamp-2 text-xs font-bold leading-6 text-slate-500">{{ \Illuminate\Support\Str::limit($ticket->latestPublicMessage->body, 120) }}</p>@endif
                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs font-bold text-slate-500">
                        <span>{{ $ticket->assignee?->name ?? 'بدون مسئول' }}</span>
                        <span dir="ltr">{{ \App\Support\Jalali::format($ticket->last_activity_at ?? $ticket->created_at) }}</span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm font-bold text-slate-500">تیکتی با این فیلترها پیدا نشد.</div>
            @endforelse
        </div>

        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <x-admin.sortable-heading label="تیکت" column="subject" :sort="$sort" />
                            <th class="px-4 py-3 text-right">مشتری</th>
                            <th class="px-4 py-3 text-right">مسیر</th>
                            <th class="px-4 py-3 text-right">مسئول</th>
                            <x-admin.sortable-heading label="وضعیت" column="status" :sort="$sort" />
                            <x-admin.sortable-heading label="آخرین فعالیت" column="last_activity_at" :sort="$sort" />
                            <th class="px-4 py-3 text-right">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50 {{ $ticket->unread_replies_count > 0 ? 'bg-[#F7FAFF]' : '' }}">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        @if($ticket->unread_replies_count > 0)<span class="size-2.5 shrink-0 rounded-full bg-[#0069FF]" aria-label="پاسخ جدید مشتری"></span>@endif
                                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-black text-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $ticket->subject }}</a>
                                    </div>
                                    <p class="mt-1 text-xs font-bold text-slate-400" dir="ltr">{{ $ticket->number }}</p>
                                    @if($ticket->latestPublicMessage)<p class="mt-2 max-w-sm truncate text-xs font-bold text-slate-500">{{ $ticket->latestPublicMessage->author_type === \App\Models\Customer::class ? 'مشتری: ' : 'پشتیبانی: ' }}{{ \Illuminate\Support\Str::limit($ticket->latestPublicMessage->body, 90) }}</p>@endif
                                    <div class="mt-2"><x-admin.status-badge :value="$ticket->priority" :label="$priorities[$ticket->priority] ?? $ticket->priority" :tone="$ticket->priority === 'urgent' ? 'danger' : ($ticket->priority === 'high' ? 'warning' : 'neutral')" /></div>
                                </td>
                                <td class="px-4 py-4 font-bold text-slate-700">{{ $ticket->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $ticket->category?->name ?? '—' }}<p class="mt-1 text-xs text-slate-400">{{ $ticket->supportTeam?->name ?? 'بدون تیم' }}</p></td>
                                <td class="px-4 py-4 font-bold text-slate-700">{{ $ticket->assignee?->name ?? 'خودکار / بدون مسئول' }}</td>
                                <td class="px-4 py-4"><x-admin.status-badge :value="$ticket->status" :label="$statuses[$ticket->status] ?? $ticket->status" /></td>
                                <td class="px-4 py-4 text-xs font-bold text-slate-400" dir="ltr">{{ $ticket->last_activity_at?->format('Y-m-d H:i') ?? $ticket->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-4"><x-admin.icon-action :href="route('admin.tickets.show', $ticket)" label="مشاهده تیکت" icon="view" tone="primary" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">تیکتی پیدا نشد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-5">{{ $tickets->links() }}</div>
    </section>
</div>
@endsection
