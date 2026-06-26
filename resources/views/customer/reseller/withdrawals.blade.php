@extends('customer.layout')
@inject('money', 'App\Services\WalletService')

@section('title', 'درخواست‌های برداشت')

@section('content')
<div class="px-4 py-6 lg:px-8">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-6">
        <h1 class="text-2xl font-black">درخواست‌های برداشت</h1>
        <p class="mt-1 text-sm text-slate-500">موجودی قابل برداشت: <span class="font-bold text-amber-600">{{ $money->format($customer->reseller_earnings_balance ?? 0) }}</span></p>
    </div>

    @if ($customer->reseller_payout_method === 'withdrawable')
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">درخواست برداشت جدید</h2>
            <form method="POST" action="{{ route('customer.reseller.withdrawals.store') }}" class="mt-4 flex items-end gap-4">
                @csrf
                <div class="flex-1">
                    <label for="amount" class="block text-sm font-bold text-slate-700">مبلغ (تومان)</label>
                    <div class="relative mt-1">
                        <input type="number" name="amount" id="amount" min="10000" max="{{ $customer->reseller_earnings_balance }}" value="{{ old('amount') }}" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">تومان</span>
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-lg bg-[#0069FF] px-6 py-3 text-sm font-black text-white transition hover:bg-[#0069FF]/90">درخواست برداشت</button>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">تاریخ درخواست</th>
                    <th class="px-4 py-3">مبلغ</th>
                    <th class="px-4 py-3">وضعیت</th>
                    <th class="px-4 py-3">یادداشت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($withdrawals as $withdrawal)
                    <tr class="hover:bg-slate-50/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $withdrawal->created_at->format('Y/m/d H:i') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $money->format($withdrawal->amount) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($withdrawal->status === 'pending')
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700">در انتظار</span>
                            @elseif ($withdrawal->status === 'approved')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">تایید شده</span>
                            @elseif ($withdrawal->status === 'rejected')
                                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">رد شده</span>
                            @else
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">پرداخت شده</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $withdrawal->admin_note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">هنوز درخواست برداشتی ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $withdrawals->links() }}
    </div>
</div>
@endsection
