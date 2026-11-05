@extends('layouts.admin')
@section('title', 'پیامک استقرار')
@section('content')
<div class="space-y-6 p-6">
    <h1 class="text-2xl font-black">پیامک استقرار</h1>
    <p>ارسال مستقل از صف برنامه انجام می‌شود. %token شناسه تغییرات و %token2 شماره انتشار فعال است. قالب‌ها را فقط پس از تأیید کاوه‌نگار فعال کنید. خاموش‌کردن ارسال، پیامک‌های منتظر را متوقف می‌کند. شماره حذف‌شده یا قالب تغییرکرده باعث لغو پیامک منتظر می‌شود.</p>
    @if(session('status'))<p class="rounded bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="rounded bg-red-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php($labels = ['started'=>'شروع استقرار', 'success'=>'استقرار موفق', 'failure'=>'استقرار ناموفق', 'rollback'=>'بازگشت به انتشار قبلی', 'recovery'=>'موفقیت پس از خطا'])
    <form method="POST" action="{{ route('admin.settings.deployment-sms.update') }}" class="space-y-4 rounded-xl border bg-white p-5">
        @csrf @method('PATCH')
        <x-form.checkbox name="enabled" label="ارسال پیامک استقرار فعال باشد" :checked="old('enabled', $settings['enabled'])" />
        <x-form.input name="recipients" label="شماره گیرندگان (با ویرگول جدا کنید)" :value="old('recipients', implode(', ', $settings['recipients']))" dir-ltr />
        <x-form.input name="max_attempts" type="number" label="حداکثر تلاش برای هر پیامک" :value="old('max_attempts', $settings['max_attempts'])" min="1" max="10" />
        @foreach($events as $event)
        <div class="grid gap-3 border-t pt-4 md:grid-cols-2">
            <x-form.checkbox :name="'events['.$event.'][enabled]'" :label="$labels[$event]" :checked="old('events.'.$event.'.enabled', $settings['events'][$event]['enabled'] ?? false)" />
            <x-form.input :name="'events['.$event.'][template]'" label="قالب کاوه‌نگار" :value="old('events.'.$event.'.template', $settings['events'][$event]['template'] ?? '')" dir-ltr />
        </div>
        @endforeach
        <button class="rounded bg-blue-600 px-5 py-3 text-white">ذخیره تنظیمات</button>
    </form>
    <form method="POST" action="{{ route('admin.settings.deployment-sms.action') }}" class="space-y-3 rounded-xl border bg-white p-5">
        @csrf <input type="hidden" name="action" value="test">
        <p>آزمایش واقعی برای همه گیرندگان ذخیره‌شده ارسال می‌شود. مقادیر آزمایشی: %token = test-xx و %token2 = test. ابتدا تنظیمات را ذخیره کنید.</p>
        <select name="event" class="rounded border p-2">@foreach($events as $event)<option value="{{ $event }}">{{ $labels[$event] }}</option>@endforeach</select>
        <button class="rounded bg-slate-800 px-4 py-2 text-white">ارسال آزمایشی</button>
    </form>
    <h2 class="text-xl font-black">۱۰۰ ارسال اخیر</h2>
    <div class="overflow-x-auto"><table class="w-full border bg-white text-sm"><thead><tr><th>رویداد</th><th>تغییرات / انتشار</th><th>گیرنده</th><th>وضعیت / تلاش</th><th>زمان / شناسه پیامک</th><th>خطا</th><th>عملیات</th></tr></thead><tbody>
    @foreach($deliveries as $delivery)
    <tr class="border-t"><td class="p-3">{{ $labels[$delivery['event']] ?? $delivery['event'] }}</td><td dir="ltr">{{ substr($delivery['revision'],0,7) }} / {{ $delivery['release'] }}</td><td>{{ $delivery['phone'] }}</td><td>{{ $delivery['status'] }} / {{ $delivery['attempts'] }}</td><td dir="ltr">{{ date('Y-m-d H:i', (int)$delivery['created_at']) }} / {{ $delivery['provider_id'] }}</td><td>{{ $delivery['error'] }}</td><td>
    @foreach(['cancel'=>'لغو', 'retry'=>'تلاش مجدد'] as $action=>$label)
    @if(($action === 'cancel' && in_array($delivery['status'], ['pending','failed'])) || ($action === 'retry' && in_array($delivery['status'], ['failed','cancelled'])))
    <form method="POST" action="{{ route('admin.settings.deployment-sms.action') }}" class="inline">@csrf<input type="hidden" name="id" value="{{ $delivery['id'] }}"><input type="hidden" name="action" value="{{ $action }}"><button class="p-2 text-blue-600">{{ $label }}</button></form>
    @endif
    @endforeach
    </td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endsection
