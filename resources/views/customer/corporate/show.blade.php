@extends('customer.layout')
@section('title', 'احراز هویت حقوقی')
@section('header_title', 'درخواست حقوقی شماره '.$corporate->id)
@section('header_subtitle', 'مشخصات ثبتی و مدارک نمایندگی شرکت')
@php($activeNav = 'profile')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-6 space-y-5">
    <a href="{{ route('customer.profile.show') }}" class="text-blue-600">بازگشت به پروفایل</a>
    @if(session('status'))<p class="rounded-xl bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="rounded-xl bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <h2 class="text-xl font-black">{{ $corporate->company['name'] }}</h2>
    <p>وضعیت: {{ \App\Models\CorporateRequest::statuses()[$corporate->status] }}</p>
    <dl class="grid gap-3 md:grid-cols-2"><div>شناسه ملی: {{ $corporate->national_id }}</div><div>شماره ثبت: {{ $corporate->company['register_number'] ?? '—' }}</div><div>نوع شرکت: {{ $corporate->company['company_type'] ?? '—' }}</div><div>تاریخ استعلام: {{ \App\Support\Jalali::format($corporate->looked_up_at) }}</div></dl>
    @if($corporate->reason)<p class="rounded-xl bg-amber-50 p-4 whitespace-pre-line">{{ $corporate->reason }}</p>@endif
    @foreach($corporate->events->where('event', 'approved') as $event)
        <p class="rounded-xl bg-emerald-50 p-4">شروع صورتحساب حقوقی: {{ \App\Support\Jalali::format(\Carbon\Carbon::parse($event->snapshot['billing_effective_at'])) }}</p>
    @endforeach
    @if(in_array($corporate->status, ['draft', 'needs_correction']))
    <form method="POST" action="{{ route('customer.corporate.submit', $corporate) }}" enctype="multipart/form-data" class="space-y-4" data-submit-loading>
        @csrf
        <input type="hidden" name="version" value="{{ $corporate->version }}">
        <label class="block">سمت نماینده<select name="representative_role" class="mt-2 w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"><option value="signatory" @selected(old('representative_role', $corporate->representative_role) === 'signatory')>صاحب امضای مجاز</option><option value="delegate" @selected(old('representative_role', $corporate->representative_role) === 'delegate')>نماینده معرفی‌شده</option></select></label>
        @foreach(['billing_email' => 'ایمیل صورتحساب', 'company_phone' => 'تلفن شرکت', 'address' => 'نشانی ثبتی', 'postal_code' => 'کد پستی ده رقمی', 'tax_number' => 'شماره اقتصادی (در صورت نیاز)'] as $field => $label)
            <label class="block">{{ $label }}<input name="{{ $field }}" value="{{ old($field, $corporate->$field) }}" @required($field !== 'tax_number') maxlength="{{ $field === 'address' ? 2000 : 255 }}" class="mt-2 w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none" @if($field === 'billing_email') type="email" @endif></label>
        @endforeach
        <p class="text-sm text-slate-500">مدارک PDF، JPG یا PNG تا ۵ مگابایت. در درخواست اصلاح، مدارک قبلی حفظ می‌شوند؛ فقط مدارک نیازمند تغییر را بارگذاری کنید.</p>
        @foreach(['incorporation' => 'آگهی تأسیس', 'signatories' => 'آخرین آگهی صاحبان امضای مجاز', 'authorization' => 'معرفی‌نامه نماینده (برای نماینده معرفی‌شده الزامی)'] as $kind => $label)
            <label class="block">{{ $label }}<input type="file" name="{{ $kind }}" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"></label>
        @endforeach
        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm leading-7"><input type="checkbox" name="consent" value="1" required class="mt-2"><span>اختیار نمایندگی شرکت و صحت اطلاعات را تأیید می‌کنم. پس از تأیید، سرویس‌های فعلی و مانده کیف پول این حساب برای شرکت استفاده خواهند شد. صورتحساب حقوقی از ابتدای ماه شمسی بعد از تأیید آغاز می‌شود؛ قیمت‌ها و سوابق قبلی حفظ می‌شوند.</span></label>
        <button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">ارسال برای بررسی</button>
    </form>
    @endif
    <h3 class="font-black">مدارک ثبت‌شده</h3>
    @foreach($corporate->documents->where('version', $corporate->version) as $document)
        <a class="block text-blue-600" href="{{ route('customer.corporate.document', $document) }}">{{ ['incorporation' => 'آگهی تأسیس', 'signatories' => 'صاحبان امضای مجاز', 'authorization' => 'معرفی‌نامه نماینده'][$document->kind] ?? $document->kind }} — {{ $document->original_name }}</a>
    @endforeach
    <h3 class="font-black">تاریخچه درخواست</h3>
    @foreach($corporate->events as $event)<p class="text-sm text-slate-600">{{ \App\Support\Jalali::format($event->created_at) }} · نسخه {{ $event->version }} · {{ $event->event === 'submitted' ? 'ارسال برای بررسی' : (\App\Models\CorporateRequest::statuses()[$event->event] ?? $event->event) }}</p>@endforeach
</section>
@endsection
