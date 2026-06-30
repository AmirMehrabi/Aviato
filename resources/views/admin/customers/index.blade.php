@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'مدیریت مشتریان')

@section('content')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customerFilters', () => ({
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
    x-data="customerFilters"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">مشتریان</h1>
            <p class="mt-2 text-sm text-slate-500">مدیریت مشتریان و وضعیت سرویس‌ها</p>
        </div>
        <a href="{{ route('admin.customers.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">افزودن مشتری</a>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['کل مشتریان', $stats['total']], ['فعال', $stats['active']], ['تعلیق شده', $stats['suspended']], ['تایید شده', $stats['verified']]] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <form x-ref="filters" @submit.prevent method="GET" action="{{ route('admin.customers.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" @input="fetchResults()" placeholder="جستجو: نام، ایمیل یا موبایل..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
            </div>
            <select name="status" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه وضعیت‌ها</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>فعال</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>تعلیق شده</option>
            </select>
            <select name="verification" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="">همه تاییدها</option>
                <option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>تایید شده</option>
                <option value="unverified" @selected(($filters['verification'] ?? '') === 'unverified')>تایید نشده</option>
            </select>
            <select name="sort" @change="fetchNow()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>جدیدترین</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>قدیمی‌ترین</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>نام</option>
            </select>
            <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</a>
        </div>
    </form>

    <section x-ref="results" class="mt-6">
        <x-admin.index-table :columns="[
            ['label' => 'مشتری'],
            ['label' => 'تماس'],
            ['label' => 'وضعیت'],
            ['label' => 'کیف پول'],
            ['label' => 'تاریخ ایجاد'],
            ['label' => 'عملیات', 'class' => 'text-left'],
        ]">
            @forelse ($customers as $customer)
                @php
                    $credit = $customer->wallet?->balance ?? 0;
                    $statusClass = $customer->status === 'suspended' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-[#EBF3FF] text-[#0069FF] ring-[#B8D6FF]';
                @endphp
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-xl bg-[#EBF3FF] font-black text-[#0069FF]">{{ mb_substr($customer->name, 0, 1) }}</span>
                            <div>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-black text-slate-950 hover:text-[#0069FF]">{{ $customer->name }}</a>
                                <p class="mt-1 text-xs text-slate-500">#{{ $customer->id }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-left" dir="ltr">
                        <p class="font-bold text-slate-800">{{ $customer->email ?: '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $customer->phone ?: '—' }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <span class="rounded-md px-2.5 py-1 text-xs font-black ring-1 {{ $statusClass }}">{{ $customer->status === 'suspended' ? 'تعلیق شده' : 'فعال' }}</span>
                        <p class="mt-2 text-xs text-slate-500">{{ $customer->national_code_verified_at ? 'حساب تایید شده' : 'حساب تایید نشده' }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-black {{ $credit < 0 ? 'text-red-600' : 'text-[#0069FF]' }}">{{ $money->format($credit) }}</p>
                        <p class="mt-1 text-xs text-slate-500">موجودی فعلی کیف پول</p>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $customer->created_at?->format('Y/m/d') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-lg bg-[#0069FF] px-3 py-2 text-xs font-black text-white">نمایش</a>
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700">ویرایش</a>
                            <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}" target="_blank">
                                @csrf
                                <button class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black text-sky-700">ورود به‌جای مشتری</button>
                            </form>
                            @if($customer->status === 'suspended')
                                <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-[#EBF3FF] px-3 py-2 text-xs font-black text-[#0069FF]">فعال‌سازی</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.customers.suspend', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700">تعلیق</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-14 text-center">
                        <h2 class="text-xl font-black text-slate-900">مشتری‌ای پیدا نشد</h2>
                        <p class="mt-2 text-slate-500">فیلترها را تغییر دهید یا اولین مشتری را اضافه کنید.</p>
                        <a href="{{ route('admin.customers.create') }}" class="mt-5 inline-flex rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">افزودن مشتری</a>
                    </td>
                </tr>
            @endforelse

            <x-slot:pagination>
                {{ $customers->links() }}
            </x-slot:pagination>
        </x-admin.index-table>
    </section>
</div>
@endsection
