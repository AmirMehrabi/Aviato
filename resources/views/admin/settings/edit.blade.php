@extends('layouts.admin')

@section('title', 'تنظیمات')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black">تنظیمات سیستم</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">واحد پولی برای نمایش قیمت‌ها، کیف پول و صورتحساب‌ها استفاده می‌شود. همچنین می‌توانید تایید ثبت‌نام مشتری را غیرفعال یا روی ایمیل / پیامک تنظیم کنید.</p>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-5">
            @csrf @method('PATCH')
            <x-form.select name="currency" label="واحد پولی Billing" :selected="$currency" :options="$currencies" />
            <x-form.select name="customer_verification_mode" label="روش تایید ثبت نام مشتری" :selected="$verificationMode" :options="$verificationModes" />

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">تنظیمات درگاه پیامک SMS0098</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">فقط زمانی استفاده می‌شود که روش تایید روی پیامک باشد.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="sms0098_username" label="Username" :value="$sms0098Username" dir-ltr />
                    <x-form.input name="sms0098_panel_no" label="Panel Number (PnlNo / FROM)" :value="$sms0098PanelNo" dir-ltr />
                </div>
                <div class="mt-4">
                    <x-form.input name="sms0098_password" type="password" label="Password" value="" dir-ltr help="برای حفظ رمز فعلی، این فیلد را خالی بگذارید." />
                </div>
            </div>

            <button class="rounded-lg bg-[#105D52] px-5 py-3 text-sm font-black text-white">ذخیره تنظیمات</button>
        </form>
    </div>
</div>
@endsection
