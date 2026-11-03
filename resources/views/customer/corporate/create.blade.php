@extends('customer.layout')
@section('title', 'درخواست حساب حقوقی')
@section('header_title', 'درخواست حساب حقوقی')
@section('header_subtitle', '۱. شناسایی شرکت · ۲. نماینده و مدارک · ۳. بازبینی و ارسال')
@php($activeNav = 'profile')
@section('content')
<section class="mx-auto max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6">
    <a href="{{ route('customer.profile.show') }}#corporate-identity" class="text-sm font-bold text-blue-600">بازگشت به پروفایل</a>
    @if(session('status'))<p role="status" class="rounded-xl bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @include('customer.corporate.letter-notice')
    <div><h2 class="text-xl font-black">قبل از شروع</h2><p class="mt-3 text-sm leading-8 text-slate-600">آگهی تأسیس، آخرین آگهی تغییرات صاحبان امضا و نامه رسمی با سربرگ شرکت و امضای صاحبان امضا را آماده کنید. مدارک می‌توانند PDF، JPG یا PNG باشند. حداکثر حجم هر فایل ۵ مگابایت است.</p></div>
    <p class="rounded-xl bg-slate-50 p-4 text-sm leading-8">پس از تأیید، سرویس‌های فعلی و مانده کیف پول این حساب برای شرکت استفاده خواهند شد. صورتحساب حقوقی از ابتدای ماه شمسی بعد از تأیید آغاز می‌شود؛ قیمت‌ها و سوابق قبلی حفظ می‌شوند. هویت فردی شما برای ورود به حساب حفظ می‌شود.</p>
    @if(! $customer->hasVerifiedNationalCode())
        <div class="rounded-xl bg-amber-50 p-4 text-sm leading-8"><p>ابتدا هویت فردی خود را تأیید کنید، سپس برای استعلام شرکت به این صفحه برگردید.</p><a href="{{ route('customer.profile.show', ['corporate' => 1]) }}#personal-identity" class="font-bold text-blue-700">تأیید هویت فردی و ادامه درخواست</a></div>
    @else
        <form method="POST" action="{{ route('customer.corporate.start') }}" class="space-y-4" data-submit-loading>
            @csrf
            <label for="company-national-id" class="block text-sm font-bold">شناسه ملی شرکت — ۱۱ رقم</label>
            <input id="company-national-id" name="national_id" value="{{ old('national_id') }}" required inputmode="numeric" maxlength="11" aria-describedby="national-id-help{{ $errors->has('national_id') ? ' national-id-error' : '' }}" @if($errors->has('national_id')) aria-invalid="true" @endif class="w-full rounded-xl border border-slate-200 p-3" dir="ltr">
            <p id="national-id-help" class="text-sm text-slate-500">شناسه ملی شرکت با کد ملی فردی متفاوت است. در مرحله بعد مشخصات شرکت را بررسی می‌کنید.</p>
            @error('national_id')<p id="national-id-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">استعلام شرکت و ادامه</button>
        </form>
    @endif
</section>
@endsection
