@extends('layouts.admin')
@section('title', 'تنظیمات احراز هویت حقوقی')
@section('content')
<div class="p-6 space-y-5">
    <h1 class="text-2xl font-black">تنظیمات احراز هویت حقوقی</h1>
    @if(session('status'))<p class="rounded-xl bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="POST" action="{{ route('admin.settings.corporate.update') }}" class="rounded-xl border border-slate-200 bg-white p-5 space-y-5">@csrf @method('PATCH')
    @foreach(['enabled' => 'پذیرش درخواست حقوقی فعال باشد', 'email_enabled' => 'اعلان ایمیل', 'sms_enabled' => 'اعلان پیامک', 'in_app_enabled' => 'اعلان درون برنامه', 'submission_sms_enabled' => 'پیامک تأیید ثبت برای مشتری'] as $key => $label)
        <input type="hidden" name="{{ $key }}" value="0"><label class="block"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, \App\Models\AppSetting::getValue('corporate.'.$key, !in_array($key, ['enabled', 'submission_sms_enabled']))))> {{ $label }}</label>
    @endforeach
    <p class="text-sm">توکن زحل از تنظیمات استعلام کد ملی استفاده می‌شود. قالب‌های کاوه‌نگار باید تأیید شده و خط اصلی هر الگو روی <b dir="ltr">200000171</b> تنظیم شده باشد.</p>
    <h2 class="font-black">مدیران دریافت‌کننده درخواست‌های جدید</h2>
    @foreach($reviewers as $reviewer)<label class="block"><input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}" @checked(in_array($reviewer->id, old('reviewer_ids', \App\Models\AppSetting::getValue('corporate.reviewer_ids', []))))> {{ $reviewer->name }} — {{ $reviewer->phone }} — {{ $reviewer->email }}</label>@endforeach
    @foreach($templates as $event => $template)<label class="block">{{ $event }}<input name="templates[{{ $event }}]" value="{{ old('templates.'.$event, \App\Models\AppSetting::getValue('corporate.template.'.$event, $template['name'])) }}" class="block w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none" dir="ltr"></label><pre class="whitespace-pre-wrap text-sm">{{ $template['text'] }}</pre>@endforeach
    <button class="rounded-xl bg-blue-600 px-5 py-3 text-white">ذخیره تنظیمات</button>
    </form>
</div>
@endsection
