@extends('customer.layout')

@section('title', 'پروفایل')
@section('header_title', 'پروفایل حساب')
@section('header_subtitle', 'اطلاعات هویتی، سطح حساب و سهمیه ساخت VPS')

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
                        {{ $customer->hasVerifiedNationalCode() ? 'کد ملی این حساب ثبت و تایید شده است.' : ($nationalCodeVerificationEnabled ? 'برای افزایش سقف ساخت VPS، کد ملی معتبر خود را ثبت کنید تا از طریق سرویس شاهکار بررسی شود.' : 'برای افزایش سقف ساخت VPS، کد ملی معتبر خود را ثبت کنید. تایید برخط در حال حاضر غیرفعال است.') }}
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
                <h2 class="text-lg font-black text-slate-950">سهمیه VPS</h2>
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
                        <dd class="font-black text-slate-950">{{ $quota['limit'] > 0 ? $quota['limit'] : 'بدون سقف' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-black text-slate-500">مصرف سهمیه</p>
                        <p class="mt-2 text-2xl font-black {{ $quota['can_create'] ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $quota['limit'] > 0 ? $quota['used'].' / '.$quota['limit'] : $quota['used'].' / بدون سقف' }}
                        </p>
                        @if ($quota['message'])
                            <p class="mt-2 text-xs font-bold leading-6 text-red-600">{{ $quota['message'] }}</p>
                        @endif
                    </div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
