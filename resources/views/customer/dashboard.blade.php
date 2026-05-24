@extends('customer.layout')

@section('title', 'داشبورد مشتری')
@section('header_title', 'داشبورد')
@section('header_subtitle', 'ماشین ها، مصرف PAYG و وضعیت کیف پول در یک نمای ساده')

@php($activeNav = 'dashboard')

@section('search_data')
[
@foreach ($vmRows as $vm)
    {
        "title": @json($vm['name']),
        "description": @json($vm['ip'].' - '.$vm['region'].' - '.$vm['plan']),
        "type": "VM",
        "url": @json(route('dashboard', [], false).'#vm-list'),
        "keywords": @json($vm['name'].' '.$vm['ip'].' '.$vm['region'].' '.$vm['plan'].' '.$vm['status'])
    }@if (! $loop->last),@endif
@endforeach
]
@endsection

@section('content')
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-black text-[#0069FF]">نمای ابری</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">نمای عملیاتی حساب شما</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500">
                        هزینه ها در طول ماه از کیف پول کسر می شوند و صورتحساب ماهانه برای بررسی جزئیات صادر می شود.
                    </p>
                </div>
                <div class="grid shrink-0 grid-cols-2 gap-3 text-sm">
                    <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF]">کیف پول</a>
                    <a href="{{ route('customer.invoices.index', [], false) }}" class="inline-flex items-center justify-center rounded-lg bg-[#0069FF] px-4 py-2.5 font-black text-white transition hover:bg-[#0050D0]">صورتحساب ها</a>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'ماشین روشن', 'value' => $summary['running'], 'tone' => 'text-[#0069FF]'],
                    ['label' => 'ماشین متوقف', 'value' => $summary['stopped'], 'tone' => 'text-slate-950'],
                    ['label' => 'مصرف ماهانه', 'value' => $wallets->format($summary['monthly_spend']), 'tone' => 'text-slate-950'],
                    ['label' => 'مصرف ثبت نشده', 'value' => $wallets->format($pendingUsage), 'tone' => $pendingUsage > 0 ? 'text-amber-600' : 'text-emerald-600'],
                ] as $metric)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-black text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-2 truncate text-xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-slate-950">کیف پول</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $wallet->is_locked ? 'قفل شده' : 'فعال و آماده مصرف' }}</p>
                </div>
                <span class="rounded-md px-2 py-1 text-[11px] font-black {{ $wallet->is_locked ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">{{ $wallet->is_locked ? 'قفل' : 'فعال' }}</span>
            </div>
            <p class="mt-5 truncate text-3xl font-black {{ $wallet->balance < 0 ? 'text-red-600' : 'text-slate-950' }}">{{ $wallets->format($wallet->balance) }}</p>
            <p class="mt-2 text-sm text-slate-500">مصرف ثبت نشده: {{ $wallets->format($pendingUsage) }}</p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <a href="{{ route('customer.wallet.show', [], false) }}" class="inline-flex justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50">تراکنش ها</a>
                <a href="{{ route('customer.wallet.show', ['topup' => 1], false) }}" class="inline-flex justify-center rounded-lg bg-[#0069FF] px-3 py-2 text-sm font-black text-white transition hover:bg-[#0050D0]">شارژ</a>
            </div>
        </aside>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div id="vm-list" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-950">ماشین های ابری</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $virtualMachines->count() }} ماشین در این حساب</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-md bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">PAYG فعال</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-right text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-black text-slate-500">
                        <tr>
                            <th class="px-5 py-3">ماشین</th>
                            <th class="px-5 py-3">منطقه</th>
                            <th class="px-5 py-3">پلن</th>
                            <th class="px-5 py-3">منابع</th>
                            <th class="px-5 py-3">وضعیت</th>
                            <th class="px-5 py-3 text-left">هزینه ماهانه</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($vmRows as $vm)
                            <tr class="transition hover:bg-[#F8FBFF]">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-black text-slate-950" dir="ltr">{{ $vm['name'] }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500" dir="ltr">{{ $vm['ip'] }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['region'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $vm['plan'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-bold text-slate-500">CPU {{ $vm['cpu'] }} / RAM {{ $vm['ram'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-black {{ $vm['statusClass'] }}">
                                        <span class="size-2 rounded-full {{ $vm['dot'] }}"></span>
                                        {{ $vm['status'] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-left font-black text-slate-950">{{ $wallets->format($vm['cost']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm font-bold text-slate-500">هنوز ماشینی برای این حساب ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-black text-slate-950">آخرین تراکنش ها</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($transactions as $transaction)
                        <div class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">{{ $transaction->description ?: 'بدون توضیح' }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $transaction->created_at?->format('Y/m/d H:i') }}</p>
                                </div>
                                <span class="shrink-0 text-xs font-black {{ $transaction->amount >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $wallets->format($transaction->amount) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm font-bold text-slate-500">هنوز تراکنشی ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-base font-black text-slate-950">اعلان های مالی</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($notifications as $notification)
                        <div class="flex gap-3">
                            <span class="mt-2 size-2 shrink-0 rounded-full {{ $notification['tone'] }}"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-slate-950">{{ $notification['title'] }}</p>
                                <p class="mt-1 text-sm leading-7 text-slate-500">{{ $notification['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </section>
@endsection
