@extends('layouts.admin')

@section('title', 'افزودن فروشنده')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.resellers.index') }}" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 7h8m0 0v8m0-8-8 8-4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <h1 class="text-2xl font-black">افزودن فروشنده جدید</h1>
    </div>

    <form method="POST" action="{{ route('admin.resellers.store') }}" class="mt-6 max-w-2xl space-y-6">
        @csrf

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">اطلاعات فروشنده</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="customer_id" class="block text-sm font-bold text-slate-700">مشتری</label>
                    <select name="customer_id" id="customer_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <option value="">انتخاب مشتری...</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} ({{ $customer->email ?? $customer->phone }})</option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="commission_pct" class="block text-sm font-bold text-slate-700">درصد کمیسیون</label>
                    <div class="relative mt-1">
                        <input type="number" name="commission_pct" id="commission_pct" step="0.01" min="0.01" max="100" value="{{ old('commission_pct', '10') }}" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#0069FF] focus:bg-white focus:outline-none">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">درصدی از مصرف روزانه مشتریان که به فروشنده تعلق می‌گیرد.</p>
                    @error('commission_pct')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700">روش پرداخت</label>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-[#0069FF]/30 hover:bg-[#0069FF]/5 has-[:checked]:border-[#0069FF] has-[:checked]:bg-[#0069FF]/5">
                            <input type="radio" name="payout_method" value="auto_credit" @checked(old('payout_method', 'auto_credit') === 'auto_credit') class="text-[#0069FF] focus:ring-[#0069FF]">
                            <div>
                                <p class="text-sm font-bold text-slate-800">شارژ خودکار کیف پول</p>
                                <p class="text-xs text-slate-400">کمیسیون مستقیماً به کیف پول فروشنده اضافه می‌شود</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-[#0069FF]/30 hover:bg-[#0069FF]/5 has-[:checked]:border-[#0069FF] has-[:checked]:bg-[#0069FF]/5">
                            <input type="radio" name="payout_method" value="withdrawable" @checked(old('payout_method') === 'withdrawable') class="text-[#0069FF] focus:ring-[#0069FF]">
                            <div>
                                <p class="text-sm font-bold text-slate-800">قابل برداشت</p>
                                <p class="text-xs text-slate-400">کمیسیون در موجودی جداگانه ذخیره و قابل برداشت می‌شود</p>
                            </div>
                        </label>
                    </div>
                    @error('payout_method')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-[#0069FF] px-6 py-3 text-sm font-black text-white transition hover:bg-[#0069FF]/90">فعال‌سازی فروشنده</button>
            <a href="{{ route('admin.resellers.index') }}" class="rounded-lg border border-slate-200 px-6 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">انصراف</a>
        </div>
    </form>
</div>
@endsection
