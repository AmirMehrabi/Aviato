@extends('layouts.admin')
@inject('money', 'App\Services\WalletService')

@section('title', 'فروشنده: '.$customer->name)

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.resellers.index') }}" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 7h8m0 0v8m0-8-8 8-4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-black">{{ $customer->name }}</h1>
            <p class="text-sm text-slate-500">کد فروشنده: <code class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold">{{ $customer->reseller_code }}</code></p>
        </div>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['مشتریان فعال', $stats['active_customers']], ['کل درآمد', $money->format($stats['total_earned'])], ['موجودی قابل برداشت', $money->format($stats['pending_balance'])], ['درآمد این ماه', $money->format($stats['monthly_commissions'])]] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <h2 class="text-lg font-black">تنظیمات فروشنده</h2>
            <div class="flex items-center gap-2">
                @if ($customer->reseller_status === 'active')
                    <form method="POST" action="{{ route('admin.resellers.suspend', $customer) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-100">تعلیق</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.resellers.activate', $customer) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">فعال‌سازی</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.resellers.destroy', $customer) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 transition hover:bg-red-100" onclick="return confirm('آیا از غیرفعال کردن فروشنده اطمینان دارید؟')">غیرفعال کردن</button>
                </form>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.resellers.update', $customer) }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="commission_pct" class="block text-sm font-bold text-slate-700">درصد کمیسیون</label>
                    <div class="relative mt-1">
                        <input type="number" name="commission_pct" id="commission_pct" step="0.01" min="0.01" max="100" value="{{ $customer->reseller_commission_pct }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                </div>
                <div>
                    <label for="payout_method" class="block text-sm font-bold text-slate-700">روش پرداخت</label>
                    <select name="payout_method" id="payout_method" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <option value="auto_credit" @selected($customer->reseller_payout_method === 'auto_credit')>شارژ کیف پول</option>
                        <option value="withdrawable" @selected($customer->reseller_payout_method === 'withdrawable')>قابل برداشت</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-[#0069FF] px-5 py-2.5 text-sm font-black text-white transition hover:bg-[#0069FF]/90">ذخیره تغییرات</button>
        </form>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <h2 class="text-lg font-black">اختصاص مشتری</h2>
        </div>
        <form method="POST" action="{{ route('admin.resellers.assign', $customer) }}" class="mt-4">
            @csrf
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label for="assign_customer_id" class="block text-sm font-bold text-slate-700">مشتری</label>
                    <input type="text" id="assign_customer_id" name="customer_id" placeholder="نام، ایمیل یا شماره مشتری..." class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none" required>
                </div>
                <button type="submit" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0069FF]/90">اختصاص</button>
            </div>
        </form>
    </div>

    <div x-data="{ activeTab: 'customers' }" class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex border-b border-slate-200">
            <button @click="activeTab = 'customers'" :class="activeTab === 'customers' ? 'border-b-2 border-[#0069FF] text-[#0069FF]' : 'text-slate-500 hover:text-slate-700'" class="px-6 py-4 text-sm font-bold transition">مشتریان اختصاص‌یافته</button>
            <button @click="activeTab = 'commissions'" :class="activeTab === 'commissions' ? 'border-b-2 border-[#0069FF] text-[#0069FF]' : 'text-slate-500 hover:text-slate-700'" class="px-6 py-4 text-sm font-bold transition">تاریخچه کمیسیون</button>
            <button @click="activeTab = 'withdrawals'" :class="activeTab === 'withdrawals' ? 'border-b-2 border-[#0069FF] text-[#0069FF]' : 'text-slate-500 hover:text-slate-700'" class="px-6 py-4 text-sm font-bold transition">درخواست‌های برداشت</button>
        </div>

        <div x-show="activeTab === 'customers'" class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2">نام</th>
                            <th class="px-4 py-2">ایمیل</th>
                            <th class="px-4 py-2">روش اختصاص</th>
                            <th class="px-4 py-2">تاریخ اختصاص</th>
                            <th class="px-4 py-2">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignments as $assignment)
                            <tr class="hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('admin.customers.show', $assignment->customer) }}" class="font-bold text-[#0069FF] hover:underline">{{ $assignment->customer->name }}</a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $assignment->customer->email ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($assignment->assigned_via === 'referral')
                                        <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">لینک معرفی</span>
                                    @else
                                        <span class="rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-bold text-purple-700">دستی</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $assignment->created_at->format('Y/m/d') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <form method="POST" action="{{ route('admin.resellers.unassign', [$customer, $assignment->customer]) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-red-600 hover:underline" onclick="return confirm('آیا از جدا کردن مشتری اطمینان دارید؟')">جدا کردن</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">هنوز مشتری اختصاص داده نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $assignments->links() }}</div>
        </div>

        <div x-show="activeTab === 'commissions'" class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2">تاریخ</th>
                            <th class="px-4 py-2">مشتری</th>
                            <th class="px-4 py-2">مبلغ مصرف</th>
                            <th class="px-4 py-2">کمیسیون</th>
                            <th class="px-4 py-2">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($commissions as $commission)
                            <tr class="hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $commission->service_date->format('Y/m/d') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-bold">{{ $commission->customer->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $money->format($commission->settlement_amount) }}</td>
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
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">هنوز کمیسیونی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $commissions->links() }}</div>
        </div>

        <div x-show="activeTab === 'withdrawals'" class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2">تاریخ</th>
                            <th class="px-4 py-2">مبلغ</th>
                            <th class="px-4 py-2">وضعیت</th>
                            <th class="px-4 py-2">یادداشت</th>
                            <th class="px-4 py-2">عملیات</th>
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
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($withdrawal->status === 'pending')
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('admin.resellers.withdrawals.approve', $withdrawal) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-xs font-bold text-emerald-600 hover:underline">تایید</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.resellers.withdrawals.reject', $withdrawal) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-xs font-bold text-red-600 hover:underline">رد</button>
                                            </form>
                                        </div>
                                    @elseif ($withdrawal->status === 'approved')
                                        <form method="POST" action="{{ route('admin.resellers.withdrawals.paid', $withdrawal) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-xs font-bold text-blue-600 hover:underline">پرداخت شد</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">هنوز درخواست برداشتی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $withdrawals->links() }}</div>
        </div>
    </div>
</div>
@endsection
