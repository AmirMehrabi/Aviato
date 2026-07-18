@extends('customer.layout')
@inject('money', 'App\Services\WalletService')

@section('title', 'مشتریان فروشندگی')

@section('content')
<div class="px-4 py-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-black">مشتریان اختصاص‌یافته</h1>
        <p class="mt-1 text-sm text-slate-500">فهرست مشتریانی که از طریق لینک معرفی یا اختصاص دستی به شما متصل شده‌اند</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">نام</th>
                    <th class="px-4 py-3">ایمیل</th>
                    <th class="px-4 py-3">تاریخ اختصاص</th>
                    <th class="px-4 py-3">روش</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($assignments as $assignment)
                    <tr class="hover:bg-slate-50/50">
                        <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $assignment->customer->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $assignment->customer->email ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ \App\Support\Jalali::format($assignment->created_at, 'Y/m/d') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($assignment->assigned_via === 'referral')
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">لینک معرفی</span>
                            @else
                                <span class="rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-bold text-purple-700">دستی</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">
                            <div class="flex flex-col items-center gap-2">
                                <p>هنوز مشتری اختصاص داده نشده است.</p>
                                <a href="{{ route('customer.reseller.referral') }}" class="text-sm font-bold text-[#0069FF] hover:underline">لینک معرفی خود را کپی کنید</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $assignments->links() }}
    </div>
</div>
@endsection
