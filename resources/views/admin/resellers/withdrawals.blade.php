@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'درخواست‌های برداشت فروشندگان')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.resellers.index') }}" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 7h8m0 0v8m0-8-8 8-4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <h1 class="text-2xl font-black">درخواست‌های برداشت</h1>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['در انتظار', $stats['pending']], ['تایید شده', $stats['approved']], ['رد شده', $stats['rejected']], ['پرداخت شده', $stats['paid']]] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">فروشنده</th>
                    <th class="px-4 py-3">مبلغ</th>
                    <th class="px-4 py-3">تاریخ درخواست</th>
                    <th class="px-4 py-3">وضعیت</th>
                    <th class="px-4 py-3">یادداشت</th>
                    <th class="px-4 py-3">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($withdrawals as $withdrawal)
                    <tr class="hover:bg-slate-50/50">
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('admin.resellers.show', $withdrawal->reseller) }}" class="font-bold text-[#0069FF] hover:underline">{{ $withdrawal->reseller->name }}</a>
                            <span class="block text-xs text-slate-400">{{ $withdrawal->reseller->email }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $money->format($withdrawal->amount) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $withdrawal->created_at->format('Y/m/d H:i') }}</td>
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
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($withdrawal->status === 'pending')
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('admin.resellers.withdrawals.approve', $withdrawal) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">تایید</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.resellers.withdrawals.reject', $withdrawal) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-100">رد</button>
                                    </form>
                                </div>
                            @elseif ($withdrawal->status === 'approved')
                                <form method="POST" action="{{ route('admin.resellers.withdrawals.paid', $withdrawal) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100">پرداخت شد</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">هنوز درخواست برداشتی ثبت نشده است.</td>
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
