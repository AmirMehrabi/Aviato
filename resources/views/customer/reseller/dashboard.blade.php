@extends('customer.layout')
@inject('money', 'App\Services\WalletService')

@section('title', 'داشبورد فروشندگی')

@section('content')
<div class="px-4 py-6 lg:px-8">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-6">
        <h1 class="text-2xl font-black">داشبورد فروشندگی</h1>
        <p class="mt-1 text-sm text-slate-500">نمای کلی درآمد و فعالیت فروشندگی شما</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">کل درآمد</p>
            <p class="mt-2 text-2xl font-black text-[#0069FF]">{{ $money->format($stats['total_earned']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">موجودی قابل برداشت</p>
            <p class="mt-2 text-2xl font-black text-amber-600">{{ $money->format($stats['pending_balance']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">مشتریان فعال</p>
            <p class="mt-2 text-2xl font-black">{{ number_format($stats['active_customers']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-500">درآمد این ماه</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">{{ $money->format($stats['monthly_commissions']) }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black">نمودار درآمد ماهانه</h2>
        <div class="mt-4 h-64">
            <canvas x-data x-init="
                new Chart($el, {
                    type: 'bar',
                    data: {
                        labels: {!! json_encode($monthlyCommissions->pluck('month')) !!},
                        datasets: [{
                            label: 'کمیسیون',
                            data: {!! json_encode($monthlyCommissions->pluck('total')) !!},
                            backgroundColor: 'rgba(0, 105, 255, 0.8)',
                            borderRadius: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString('fa-IR'); } } }
                        }
                    }
                });
            "></canvas>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-black">آخرین کمیسیون‌ها</h2>
            <a href="{{ route('customer.reseller.commissions') }}" class="text-sm font-bold text-[#0069FF] hover:underline">مشاهده همه</a>
        </div>
        @if ($recentCommissions->isEmpty())
            <p class="mt-4 text-sm text-slate-400">هنوز کمیسیونی ثبت نشده است.</p>
        @else
            <div class="mt-4 overflow-x-auto">
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
                        @foreach ($recentCommissions as $commission)
                            <tr class="hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ \App\Support\Jalali::format($commission->service_date, 'Y/m/d') }}</td>
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black">لینک معرفی شما</h2>
        <div class="mt-4 flex items-center gap-3">
            <input type="text" value="{{ route('customer.register', ['ref' => $customer->reseller_code], false) }}" readonly class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm" x-data x-init="$el.value = '{{ route('customer.register', ['ref' => $customer->reseller_code], false) }}'">
            <button type="button" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white" x-data x-clipboard="$el.previousElementSibling.value">کپی</button>
        </div>
        <p class="mt-2 text-xs text-slate-400">این لینک را با مشتریان خود به اشتراک بگذارید. با ثبت‌نام از طریق این لینک، مشتری به طور خودکار به شما اختصاص داده می‌شود.</p>
    </div>
</div>
@endsection
