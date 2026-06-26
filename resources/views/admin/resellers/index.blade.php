@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'مدیریت فروشندگان')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('resellerFilters', () => ({
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
    x-data="resellerFilters"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">فروشندگان</h1>
            <p class="mt-2 text-sm text-slate-500">مدیریت فروشندگان، کمیسیون و برداشت‌ها</p>
        </div>
        <a href="{{ route('admin.resellers.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">افزودن فروشنده</a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['کل فروشندگان', $stats['total']], ['فعال', $stats['active']], ['تعلیق شده', $stats['suspended']], ['درخواست‌های برداشت', $stats['pending_withdrawals']]] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <form x-ref="filters" @submit.prevent method="GET" action="{{ route('admin.resellers.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" @input="fetchResults()" placeholder="جستجو: نام، ایمیل یا کد فروشنده..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select name="status" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه وضعیت‌ها</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>فعال</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>تعلیق شده</option>
            </select>
            <select name="sort" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>جدیدترین</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>قدیمی‌ترین</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>نام</option>
            </select>
        </div>
    </form>

    <div x-ref="results" class="mt-6">
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">نام</th>
                        <th class="px-4 py-3">کد فروشنده</th>
                        <th class="px-4 py-3">کمیسیون</th>
                        <th class="px-4 py-3">روش پرداخت</th>
                        <th class="px-4 py-3">مشتریان</th>
                        <th class="px-4 py-3">کل درآمد</th>
                        <th class="px-4 py-3">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($resellers as $reseller)
                        <tr class="hover:bg-slate-50/50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <a href="{{ route('admin.resellers.show', $reseller) }}" class="font-bold text-[#0069FF] hover:underline">{{ $reseller->name }}</a>
                                <span class="block text-xs text-slate-400">{{ $reseller->email }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <code class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold">{{ $reseller->reseller_code }}</code>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $reseller->reseller_commission_pct }}%</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($reseller->reseller_payout_method === 'auto_credit')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">شارژ کیف پول</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700">قابل برداشت</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-bold">{{ number_format($reseller->active_customers_count) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $money->format($reseller->reseller_commissions_sum_commission_amount ?? 0) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($reseller->reseller_status === 'active')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">فعال</span>
                                @else
                                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">تعلیق</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">هنوز فروشنده‌ای ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $resellers->links() }}
        </div>
    </div>
</div>
@endsection
