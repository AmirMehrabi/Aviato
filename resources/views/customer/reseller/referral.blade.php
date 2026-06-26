@extends('customer.layout')

@section('title', 'لینک معرفی')

@section('content')
<div class="px-4 py-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-black">لینک معرفی</h1>
        <p class="mt-1 text-sm text-slate-500">لینک اختصاصی خود را با مشتریان به اشتراک بگذارید</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black">کد فروشنده شما</h2>
        <div class="mt-3 inline-block rounded-xl bg-slate-100 px-6 py-3">
            <code class="text-2xl font-black tracking-wider text-[#0069FF]">{{ $customer->reseller_code }}</code>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-bold text-slate-700">لینک ثبت‌نام</h3>
            <div class="mt-2 flex items-center gap-3">
                <input type="text" value="{{ $referralUrl }}" readonly class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm" x-data x-init="$el.value = '{{ $referralUrl }}'">
                <button type="button" class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white" x-data x-clipboard="$el.previousElementSibling.value">کپی</button>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
            <h3 class="text-sm font-bold text-blue-800">چگونه کار می‌کند؟</h3>
            <ul class="mt-2 space-y-1 text-sm text-blue-700">
                <li>۱. لینک بالا را با مشتریان خود به اشتراک بگذارید</li>
                <li>۲. مشتریان از طریق این لینک ثبت‌نام کنند</li>
                <li>۳. آن‌ها به طور خودکار به حساب فروشنده شما متصل می‌شوند</li>
                <li>۴. از مصرف روزانه مشتریان خود کمیسیون دریافت کنید</li>
            </ul>
        </div>
    </div>
</div>
@endsection
