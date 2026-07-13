@extends('customer.layout')

@section('title', 'پروفایل')
@section('header_title', 'پروفایل حساب')
@section('header_subtitle', 'اطلاعات هویتی، سطح حساب و سهمیه ساخت ماشین مجازی')

@php
    $activeNav = 'profile';
@endphp

@section('content')
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black text-[#0069FF]">Account Level</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">{{ $customer->hasVerifiedNationalCode() ? 'حساب تایید شده' : 'حساب تایید نشده' }}</h2>
                    <p class="mt-2 text-sm font-bold leading-7 text-slate-500">
                        {{ $customer->hasVerifiedNationalCode() ? 'کد ملی این حساب ثبت و تایید شده است.' : ($nationalCodeVerificationEnabled ? 'برای افزایش سقف ساخت ماشین مجازی، کد ملی معتبر خود را ثبت کنید تا از طریق سرویس شاهکار بررسی شود.' : 'برای افزایش سقف ساخت ماشین مجازی، کد ملی معتبر خود را ثبت کنید. تایید برخط در حال حاضر غیرفعال است.') }}
                    </p>
                </div>
                <span class="inline-flex rounded-xl px-4 py-2 text-sm font-black {{ $customer->hasVerifiedNationalCode() ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' }}">
                    {{ $customer->hasVerifiedNationalCode() ? 'تایید شده' : ($nationalCodeVerificationEnabled ? 'نیازمند استعلام' : 'نیازمند ثبت') }}
                </span>
            </div>

            @if (session('status'))
                <div class="mt-5 rounded-xl border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
            @endif

            @if (! $customer->hasVerifiedNationalCode())
                <form method="POST" action="{{ route('customer.profile.national-code.update', [], false) }}" class="mt-6 space-y-4" data-submit-loading>
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="national_code" class="block text-xs font-black text-slate-500">کد ملی</label>
                        <input id="national_code" name="national_code" value="{{ old('national_code') }}" inputmode="numeric" autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-bold text-slate-900 focus:border-[#0069FF] focus:outline-none" dir="ltr" placeholder="0012345678">
                        @error('national_code')
                            <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button class="inline-flex items-center justify-center rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">
                        {{ $nationalCodeVerificationEnabled ? 'ثبت و استعلام کد ملی' : 'ثبت کد ملی' }}
                    </button>
                </form>
            @else
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm font-bold leading-7 text-emerald-800">کد ملی در تاریخ {{ $customer->national_code_verified_at?->format('Y/m/d H:i') }} تایید شده است.</p>
                </div>
            @endif
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <h2 class="text-lg font-black text-slate-950">سهمیه ماشین مجازی</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-bold text-slate-500">ماشین های فعال</dt>
                        <dd class="font-black text-slate-950">{{ $quota['active_count'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-bold text-slate-500">حذف شده در cooldown</dt>
                        <dd class="font-black text-slate-950">{{ $quota['cooldown_count'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-bold text-slate-500">سقف حساب</dt>
                        <dd class="font-black text-slate-950">{{ $quota['limit'] > 0 ? $quota['limit'] : ($quota['verified'] ? 'بدون سقف' : 'نیازمند تایید کد ملی') }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">مصرف سهمیه</p>
                        <p class="mt-2 text-2xl font-black {{ $quota['can_create'] ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $quota['limit'] > 0 ? $quota['used'].' / '.$quota['limit'] : ($quota['verified'] ? $quota['used'].' / بدون سقف' : 'نیازمند تایید') }}
                        </p>
                        @if ($quota['message'])
                            <p class="mt-2 text-xs font-bold leading-6 text-red-600">{{ $quota['message'] }}</p>
                        @endif
                    </div>
                </dl>
            </section>
        </aside>
    </div>

    <section class="mt-5 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm shadow-slate-200/60" id="api-access">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-black text-[#0069FF]">PUBLIC API</p>
                <h2 class="mt-2 text-xl font-black text-slate-950">دسترسی API</h2>
                <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-500">برای خواندن موجودی و تراکنش‌های یک فضای کاری، یک کلید بسازید و آن را در هدر Bearer درخواست‌های API ارسال کنید. کلید را فقط یک‌بار می‌بینید.</p>
            </div>
            <a href="{{ route('api.documentation') }}" target="_blank" class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-black text-blue-700 transition hover:bg-blue-100">مشاهده مستندات API</a>
        </div>

        @if (session('api_token'))
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-black text-amber-900">کلید جدید را همین حالا کپی و در محل امن ذخیره کنید:</p>
                <code class="mt-3 block overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-emerald-300" dir="ltr">{{ session('api_token') }}</code>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.api-tokens.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
            @csrf
            <input name="name" value="{{ old('name') }}" required maxlength="100" placeholder="مثلا: سرور مانیتورینگ" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold focus:border-[#0069FF] focus:outline-none">
            <button class="rounded-xl bg-[#0069FF] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0050D0]">ساخت کلید API</button>
        </form>
        @error('name')<p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>@enderror

        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full text-right text-sm">
                <thead class="bg-slate-50 text-xs font-black text-slate-500"><tr><th class="px-4 py-3">نام کلید</th><th class="px-4 py-3">دسترسی</th><th class="px-4 py-3">آخرین استفاده</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($apiTokens as $token)
                        <tr><td class="px-4 py-3 font-bold">{{ $token->name }}</td><td class="px-4 py-3 font-mono text-xs text-slate-500" dir="ltr">wallet:read</td><td class="px-4 py-3 text-slate-500">{{ $token->last_used_at?->format('Y/m/d H:i') ?? 'هنوز استفاده نشده' }}</td><td class="px-4 py-3"><form method="POST" action="{{ route('customer.profile.api-tokens.destroy', $token) }}" onsubmit="return confirm('این کلید لغو شود؟')">@csrf @method('DELETE')<button class="text-xs font-black text-red-600 hover:text-red-800">لغو کلید</button></form></td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">هنوز کلیدی نساخته‌اید.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-black text-slate-950">آخرین درخواست‌های API</h3>
            <div class="mt-3 space-y-2">
                @forelse ($apiLogs as $log)
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between"><span class="font-mono text-slate-700" dir="ltr">{{ $log->method }} {{ $log->route }}</span><span class="{{ $log->status_code < 400 ? 'text-emerald-700' : 'text-red-700' }} font-black" dir="ltr">{{ $log->status_code }} · {{ $log->duration_ms }}ms · {{ $log->created_at?->format('Y/m/d H:i') }}</span></div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-4 py-4 text-xs text-slate-500">هنوز درخواستی ثبت نشده است.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
