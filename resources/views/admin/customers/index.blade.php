@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'مدیریت مشتریان')

@section('content')
<div
    class="px-4 py-6 md:px-8 lg:px-10"
    x-data="{
        timer: null,
        submit() { clearTimeout(this.timer); this.timer = setTimeout(() => this.$refs.filters.submit(), 450); }
    }"
>
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="relative overflow-hidden rounded-2xl bg-[#0A3D37] p-6 text-white shadow-xl shadow-[#0A3D37]/15">
        <div class="absolute -left-16 -top-16 size-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-20 right-1/3 size-56 rounded-full bg-emerald-300/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-bold text-emerald-50/60">Customer Operations</p>
                <h1 class="mt-2 text-2xl font-black md:text-4xl">مشتریان و وضعیت سرویس‌ها</h1>
                <p class="mt-3 max-w-3xl leading-8 text-emerald-50/75">جستجوی سمت سرور با debounce، فیلترهای زنده و جدول reusable برای استفاده در ماژول‌های بعدی.</p>
            </div>
            <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-black text-[#0A3D37] transition hover:bg-slate-100">افزودن مشتری</a>
        </div>

        <div class="relative mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['کل مشتریان', $stats['total']], ['فعال', $stats['active']], ['تعلیق شده', $stats['suspended']], ['حساب تایید شده', $stats['verified']]] as [$label, $value])
                <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                    <p class="text-xs font-bold text-emerald-50/60">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <form x-ref="filters" method="GET" action="{{ route('admin.customers.index') }}" class="sticky top-24 z-10 mt-6 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" @input="submit()" placeholder="جستجوی سمت سرور: نام، ایمیل یا موبایل..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-[#105D52] focus:bg-white focus:outline-none">
            </div>
            <select name="status" @change="$refs.filters.submit()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#105D52] focus:bg-white focus:outline-none">
                <option value="">همه وضعیت‌ها</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>فعال</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>تعلیق شده</option>
            </select>
            <select name="verification" @change="$refs.filters.submit()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#105D52] focus:bg-white focus:outline-none">
                <option value="">همه تاییدها</option>
                <option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>تایید شده</option>
                <option value="unverified" @selected(($filters['verification'] ?? '') === 'unverified')>تایید نشده</option>
            </select>
            <select name="sort" @change="$refs.filters.submit()" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-[#105D52] focus:bg-white focus:outline-none">
                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>جدیدترین</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>قدیمی‌ترین</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>نام</option>
            </select>
            <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-black text-slate-700 transition hover:bg-slate-50">پاک کردن</a>
        </div>
    </form>

    <section class="mt-6">
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
                    $statusClass = $customer->status === 'suspended' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                @endphp
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-xl bg-[#F1F7F5] font-black text-[#105D52]">{{ mb_substr($customer->name, 0, 1) }}</span>
                            <div>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-black text-slate-950 hover:text-[#105D52]">{{ $customer->name }}</a>
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
                        <p class="mt-2 text-xs text-slate-500">{{ $customer->email_verified_at ? 'حساب تایید شده' : 'حساب تایید نشده' }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-black {{ $credit < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $money->format($credit) }}</p>
                        <p class="mt-1 text-xs text-slate-500">موجودی فعلی کیف پول</p>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $customer->created_at?->format('Y/m/d') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-lg bg-[#105D52] px-3 py-2 text-xs font-black text-white">نمایش</a>
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700">ویرایش</a>
                            <form method="POST" action="{{ route('admin.customers.impersonate', $customer) }}" target="_blank">
                                @csrf
                                <button class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black text-sky-700">Impersonate</button>
                            </form>
                            @if($customer->status === 'suspended')
                                <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">@csrf @method('PATCH') <button class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700">فعال‌سازی</button></form>
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
                        <a href="{{ route('admin.customers.create') }}" class="mt-5 inline-flex rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">افزودن مشتری</a>
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
