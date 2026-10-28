@extends('layouts.admin')
@section('title', 'بررسی درخواست حقوقی')
@section('content')
<div class="p-6 space-y-5">
    <a class="text-blue-600" href="{{ route('admin.corporate.index') }}">بازگشت به درخواست‌ها</a>
    <h1 class="text-2xl font-black">درخواست شماره {{ $corporate->id }} — {{ $corporate->company['name'] }}</h1>
    @if(session('status'))<p class="rounded-xl bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="rounded-xl bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-3">
        <p>مشتری: {{ $corporate->customer->name }} · {{ $corporate->customer->phone }} · {{ $corporate->customer->hasVerifiedNationalCode() ? 'هویت فردی ثبت و تأیید شده' : 'هویت فردی تأیید نشده' }}</p>
        <p>وضعیت: {{ \App\Models\CorporateRequest::statuses()[$corporate->status] }} · نسخه {{ $corporate->version }}</p>
        <p>آخرین استعلام: {{ \App\Support\Jalali::format($corporate->looked_up_at) }}</p>
        <dl class="grid gap-3 md:grid-cols-2">@foreach($corporate->registry_result as $key => $value)<div><dt class="text-xs text-slate-500">{{ ['national_id' => 'شناسه ملی', 'name' => 'نام ثبتی', 'register_number' => 'شماره ثبت', 'register_date' => 'تاریخ ثبت', 'company_type' => 'نوع شرکت', 'address' => 'نشانی ثبتی', 'postal_code' => 'کد پستی', 'phone_number' => 'تلفن', 'email_address' => 'ایمیل', 'activity_end_date' => 'پایان فعالیت'][$key] ?? $key }}</dt><dd>{{ $value ?? '—' }}</dd></div>@endforeach</dl>
        <hr>
        @foreach(['representative_role' => 'سمت نماینده', 'address' => 'نشانی اعلامی', 'postal_code' => 'کد پستی', 'billing_email' => 'ایمیل مالی', 'company_phone' => 'تلفن شرکت', 'tax_number' => 'شماره اقتصادی'] as $field => $label)<p>{{ $label }}: {{ $field === 'representative_role' ? (['signatory' => 'صاحب امضای مجاز', 'delegate' => 'نماینده معرفی‌شده'][$corporate->$field] ?? '—') : ($corporate->$field ?? '—') }}</p>@endforeach
        @foreach($corporate->documents->where('version', $corporate->version) as $document)<a class="block text-blue-600" href="{{ route('admin.corporate.document', $document) }}">{{ ['incorporation' => 'آگهی تأسیس', 'signatories' => 'صاحبان امضای مجاز', 'authorization' => 'معرفی‌نامه نماینده'][$document->kind] ?? $document->kind }} — {{ $document->original_name }}</a>@endforeach
    </section>
    @if($corporate->status === 'pending')
    @adminRoute('admin.corporate.review')
    <form method="POST" action="{{ route('admin.corporate.review', $corporate) }}" class="rounded-xl border border-slate-200 bg-white p-5 space-y-4" data-submit-loading>
        @csrf @method('PATCH')<input type="hidden" name="version" value="{{ $corporate->version }}">
        <p class="text-sm text-amber-800">پیش از تأیید، اختیار نماینده و نحوه امضای مجاز را با مدارک بررسی کنید. صورتحساب حقوقی از ابتدای ماه شمسی بعد آغاز می‌شود.</p>
        <label class="block">نتیجه<select name="decision" class="block w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"><option value="needs_correction">نیازمند اصلاح</option><option value="approved">تأیید درخواست</option><option value="rejected">رد درخواست</option></select></label>
        <label class="block">توضیح قابل مشاهده برای مشتری (برای اصلاح و رد الزامی)<textarea name="reason" maxlength="3000" class="block w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none">{{ old('reason') }}</textarea></label>
        <label class="block">یادداشت داخلی<textarea name="internal_notes" maxlength="5000" class="block w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none">{{ old('internal_notes') }}</textarea></label>
        <button class="rounded-xl bg-blue-600 px-5 py-3 text-white">ثبت تصمیم</button>
    </form>
    @endadminRoute
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-3"><h2 class="font-black">تاریخچه و نسخه‌های مدارک</h2>
    @foreach($corporate->events as $event)
        <div class="border-t py-3"><p>{{ \App\Support\Jalali::format($event->created_at) }} · نسخه {{ $event->version }} · {{ $event->event === 'submitted' ? 'ارسال برای بررسی' : (\App\Models\CorporateRequest::statuses()[$event->event] ?? $event->event) }} · {{ $event->actor?->name ?? 'مشتری' }}</p><details class="mt-3"><summary class="cursor-pointer text-blue-600">اطلاعات ثبت‌شده این نسخه</summary>
            <dl class="mt-3 grid gap-3 md:grid-cols-2">
                <div><dt class="text-xs text-slate-500">نام شرکت</dt><dd>{{ $event->snapshot['company']['name'] ?? '—' }}</dd></div>
                @foreach(['national_id' => 'شناسه ملی', 'address' => 'نشانی', 'postal_code' => 'کد پستی', 'billing_email' => 'ایمیل مالی', 'company_phone' => 'تلفن شرکت', 'tax_number' => 'شماره اقتصادی', 'reason' => 'توضیح مشتری', 'internal_notes' => 'یادداشت داخلی'] as $field => $label)
                    <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-line">{{ $event->snapshot[$field] ?? '—' }}</dd></div>
                @endforeach
                @if(auth('admin')->user()->allows('billing.read'))<div><dt class="text-xs text-slate-500">مانده کیف پول هنگام ثبت رویداد</dt><dd>{{ $event->snapshot['wallet_balance'] ?? '—' }}</dd></div>@endif
                @if(isset($event->snapshot['billing_effective_at']))<div><dt class="text-xs text-slate-500">شروع صورتحساب حقوقی</dt><dd>{{ \App\Support\Jalali::format(\Carbon\Carbon::parse($event->snapshot['billing_effective_at'])) }}</dd></div>@endif
            </dl>
        </details>
        @foreach($corporate->documents->where('version', $event->version) as $document)<a class="block text-blue-600" href="{{ route('admin.corporate.document', $document) }}">{{ ['incorporation' => 'آگهی تأسیس', 'signatories' => 'صاحبان امضای مجاز', 'authorization' => 'معرفی‌نامه نماینده'][$document->kind] ?? $document->kind }} — {{ $document->original_name }}</a>@endforeach</div>
    @endforeach</section>
    <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-black">ارسال اعلان‌ها</h2>@foreach($deliveries as $delivery)<p class="py-2 text-sm">{{ $delivery->recipient_type }} #{{ $delivery->recipient_id }} · {{ $delivery->channel }} · {{ $delivery->status }} · {{ $delivery->provider_id }} {{ $delivery->error }}</p>
        @if(in_array($delivery->status, ['failed', 'uncertain']))
        @adminRoute('admin.corporate.retry')
        <form method="POST" action="{{ route('admin.corporate.retry', $delivery) }}" class="flex flex-wrap gap-3 text-sm">@csrf
            @if($delivery->status === 'uncertain')<label><input type="checkbox" name="confirmed_not_sent" value="1" required> عدم ارسال را در سرویس‌دهنده بررسی و تأیید کردم</label>@endif
            <button class="rounded-lg border px-3 py-2 text-blue-700">ارسال مجدد</button>
        </form>
        @endadminRoute
        @endif
    @endforeach</section>
</div>
@endsection
