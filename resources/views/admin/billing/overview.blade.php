@extends('layouts.admin')
@section('title', 'مرکز مالی')
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @include('admin.billing._header', ['title' => 'مرکز مالی', 'subtitle' => 'مبالغ وصول‌شده از درگاه و درآمد مصرف‌شده را جداگانه بررسی و تطبیق دهید.', 'export' => 'payments'])
    <form class="mt-5 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input name="from" value="{{ request('from', \Morilog\Jalali\Jalalian::fromCarbon($from)->format('Y/m/d')) }}" dir="ltr" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input name="to" value="{{ request('to', \Morilog\Jalali\Jalalian::fromCarbon($to)->format('Y/m/d')) }}" dir="ltr" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <button class="rounded-lg bg-[#0069FF] px-4 py-2 text-sm font-black text-white">به‌روزرسانی</button>
    </form>
    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['مبالغ وصول‌شده', $wallets->format($cash), 'text-[#0069FF]'],
            ['درآمد مصرف‌شده', $wallets->format($consumption), 'text-amber-600'],
            ['پرداخت‌های موفق', number_format($successfulPayments), 'text-emerald-600'],
            ['کیف پول‌های منفی', number_format($negativeWallets), 'text-red-600'],
        ] as [$label,$value,$tone])
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black text-slate-500">{{ $label }}</p><p class="mt-3 text-2xl font-black {{ $tone }}">{{ $value }}</p></article>
        @endforeach
    </section>
    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">وصول وجه و مصرف</h2>
            @php($max = max(1, $trend->max(fn($d) => max($d['cash'], $d['consumption']))))
            <div class="mt-6 flex h-64 items-end gap-1 overflow-hidden border-b border-slate-200">
                @foreach($trend as $point)
                    <div class="group flex h-full min-w-2 flex-1 items-end justify-center gap-px" title="{{ $point['label'] }} | وصول: {{ $wallets->format($point['cash']) }} | مصرف: {{ $wallets->format($point['consumption']) }}">
                        <span class="w-1/2 rounded-t bg-[#0069FF]" style="height: {{ max(2, ($point['cash'] / $max) * 100) }}%"></span>
                        <span class="w-1/2 rounded-t bg-amber-400" style="height: {{ max(2, ($point['consumption'] / $max) * 100) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-5 text-xs font-bold text-slate-500"><span class="before:ml-2 before:inline-block before:size-2 before:bg-[#0069FF]">وصول درگاه</span><span class="before:ml-2 before:inline-block before:size-2 before:bg-amber-400">مصرف تسویه‌شده</span></div>
        </div>
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-black">خلاصه تطبیق</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between"><dt>وصول‌شده</dt><dd class="font-black text-blue-600">{{ $wallets->format($cash) }}</dd></div><div class="flex justify-between"><dt>مصرف‌شده</dt><dd class="font-black text-amber-600">{{ $wallets->format($consumption) }}</dd></div><div class="flex justify-between border-t pt-3"><dt>مانده کیف پول‌ها</dt><dd class="font-black">{{ $wallets->format($walletBalance) }}</dd></div></dl><p class="mt-4 rounded-lg bg-blue-50 p-3 text-xs leading-6 text-blue-800">شارژ کیف پول درآمد نیست؛ فقط مصرف تسویه‌شده به‌عنوان درآمد مصرفی نمایش داده می‌شود.</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-black">نیازمند بررسی</h2><div class="mt-3 space-y-2 text-sm"><a class="flex justify-between text-amber-700" href="{{ route('admin.billing.payments.index', ['status'=>'pending']) }}"><span>پرداخت در انتظار</span><b>{{ $pendingPayments }}</b></a><a class="flex justify-between text-red-700" href="{{ route('admin.billing.payments.index', ['status'=>'failed']) }}"><span>ناموفق ۷ روز اخیر</span><b>{{ $failedPayments }}</b></a><a class="flex justify-between text-red-700" href="{{ route('admin.billing.wallets.index', ['state'=>'negative']) }}"><span>کیف پول منفی</span><b>{{ $negativeWallets }}</b></a><a class="flex justify-between border-t pt-2 text-slate-700" href="{{ route('admin.resellers.withdrawals') }}"><span>تعهد فروشندگان</span><b>{{ $wallets->format($resellerLiability) }}</b></a></div></div>
        </aside>
    </section>
    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b p-5"><h2 class="font-black">آخرین رویدادهای مالی</h2><a href="{{ route('admin.billing.transactions.index') }}" class="text-sm font-black text-[#0069FF]">مشاهده همه</a></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-right">مشتری</th><th>رویداد</th><th>مرجع</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead><tbody class="divide-y">@foreach($recent as $event)<tr><td class="px-5 py-4 font-bold">{{ $event['customer']?->name }}</td><td>{{ $event['label'] }}</td><td dir="ltr">{{ $event['reference'] }}</td><td class="font-black {{ $event['amount'] < 0 ? 'text-red-600':'text-emerald-600' }}">{{ $wallets->format($event['amount']) }}</td><td>{{ $event['status'] }}</td><td>{{ \Morilog\Jalali\Jalalian::fromCarbon($event['at'])->format('Y/m/d H:i') }}</td><td><a class="font-black text-blue-600" href="{{ $event['url'] }}">جزئیات</a></td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection
