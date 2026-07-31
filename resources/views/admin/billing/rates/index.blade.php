@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'قیمت منابع')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))<div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>@endif
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-black">قیمت منابع</h1>
            <p class="mt-2 text-sm text-slate-500">CPU و RAM فقط در حالت روشن، Disk و IP همیشه محاسبه می‌شوند.</p>
        </div>
        <a href="{{ route('admin.billing.rates.create') }}" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">قیمت جدید</a>
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-right text-sm">
            <thead class="bg-slate-50 text-xs font-black text-slate-500"><tr><x-admin.sortable-heading label="منبع" column="resource" :sort="$sort" /><th class="px-5 py-4">واحد</th><x-admin.sortable-heading label="ماهانه" column="monthly_price" :sort="$sort" /><x-admin.sortable-heading label="ساعتی" column="hourly_price" :sort="$sort" /><x-admin.sortable-heading label="قانون" column="billing_policy" :sort="$sort" /><th class="px-5 py-4">عملیات</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rates as $rate)
                    <tr>
                        <td class="px-5 py-4"><span class="font-black">{{ $rate->label }}</span><span class="block text-xs text-slate-500" dir="ltr">{{ $rate->resource }}</span></td>
                        <td class="px-5 py-4">{{ $rate->unit }}</td>
                        <td class="px-5 py-4 font-black">{{ $money->format($rate->monthly_price) }}</td>
                        <td class="px-5 py-4" dir="ltr">{{ number_format((float) $rate->hourly_price, 2) }}</td>
                        <td class="px-5 py-4">{{ $rate->billing_policy === 'always' ? 'همیشه' : 'فقط روشن' }}</td>
                        <td class="px-5 py-4"><x-admin.icon-action :href="route('admin.billing.rates.edit', $rate)" label="ویرایش قیمت منبع" icon="edit" tone="primary" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">قیمتی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
