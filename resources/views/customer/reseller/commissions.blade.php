@extends('customer.layout')
@inject('money', 'App\Services\WalletService')

@section('title', 'کمیسیون‌ها')

@section('content')
<div class="px-4 py-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-black">تاریخچه کمیسیون</h1>
        <p class="mt-1 text-sm text-slate-500">جزئیات کمیسیون‌های روزانه از مصرف مشتریان شما</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">تاریخ</th>
                    <th class="px-4 py-3">مشتری</th>
                    <th class="px-4 py-3">مبلغ مصرف</th>
                    <th class="px-4 py-3">درصد</th>
                    <th class="px-4 py-3">کمیسیون</th>
                    <th class="px-4 py-3">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($commissions as $commission)
                    <tr class="hover:bg-slate-50/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $commission->service_date->format('Y/m/d') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $commission->customer->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $money->format($commission->settlement_amount) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $commission->commission_pct }}%</td>
                        <td class="whitespace-nowrap px-4 py-3 font-bold text-emerald-600">{{ $money->format($commission->commission_amount) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($commission->status === 'credited')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">شارژ شده</span>
                            @elseif ($commission->status === 'withdrawn')
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">برداشت شده</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700">در انتظار</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">هنوز کمیسیونی ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $commissions->links() }}
    </div>
</div>
@endsection
