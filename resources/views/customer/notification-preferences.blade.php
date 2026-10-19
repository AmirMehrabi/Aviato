@extends('customer.layout')
@php($activeNav = 'profile')
@section('title', 'تنظیمات اعلان‌ها')
@section('content')
<div class="space-y-5 px-4 py-6 md:px-8">
    <a href="{{ route('customer.profile.show') }}" class="text-sm font-bold text-blue-600">→ بازگشت به حساب کاربری</a>
    <h1 class="text-2xl font-black">تنظیمات اعلان‌ها</h1>
    <p class="text-sm leading-7 text-slate-500">انتخاب کنید چه خبرهایی از آویاتو دریافت کنید. کد ورود، تأیید ایمیل و بازیابی رمز عبور همیشه ارسال می‌شوند. کانال غیرفعال‌شده توسط مدیریت، تا زمان فعال‌سازی ارسال نخواهد داشت.</p>
    @if(session('status'))<p role="status" class="rounded-lg bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<p role="alert" class="text-red-600">تنظیمات معتبر نیست. دوباره تلاش کنید.</p>@endif
    <form method="POST" action="{{ route('customer.notification-preferences.update') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5">
        @csrf @method('PATCH')
        <div class="overflow-x-auto"><table class="w-full text-right text-sm"><thead><tr><th class="p-3">رویداد</th><th class="p-3">ایمیل</th><th class="p-3">داخل پنل</th></tr></thead><tbody>
        @foreach($templates as $event => $template)
            <tr class="border-t border-slate-100"><th class="p-3 font-medium">{{ $template['label'] }}</th>
            @foreach(['email', 'in_app'] as $channel)
                <td class="p-3"><input type="hidden" name="preferences[{{ $event }}][{{ $channel }}_enabled]" value="0"><input type="checkbox" name="preferences[{{ $event }}][{{ $channel }}_enabled]" value="1" aria-label="{{ $template['label'] }} - {{ $channel === 'email' ? 'ایمیل' : 'داخل پنل' }}" @checked($preferences->get($event)?->{$channel.'_enabled'} ?? true)>
                @unless(app(\App\Services\Notifications\NotificationTemplates::class)->enabled($event, $channel))<span class="mr-2 text-xs text-slate-500">غیرفعال توسط مدیریت</span>@endunless</td>
            @endforeach</tr>
        @endforeach
        </tbody></table></div>
        <button class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-bold text-white">ذخیره تنظیمات</button>
    </form>
</div>
@endsection
