@extends('customer.layout')
@section('title', 'احراز هویت حقوقی')
@section('header_title', 'درخواست حقوقی شماره '.$corporate->id)
@section('header_subtitle', 'مشخصات ثبتی و مدارک نمایندگی شرکت')
@php($activeNav = 'profile')
@section('content')
<section class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 space-y-5">
    <a href="{{ route('customer.profile.show') }}" class="text-blue-600">بازگشت به پروفایل</a>
    @if(session('status'))<p class="rounded-xl bg-blue-50 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="text-sm leading-7 text-slate-600">موارد مشخص‌شده را اصلاح کنید. فایل‌هایی که هنوز ذخیره نشده‌اند باید دوباره انتخاب شوند.</p><ul class="rounded-xl bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <h2 class="text-xl font-black">{{ $corporate->company['name'] }}</h2>
    <p class="inline-flex rounded-lg bg-blue-50 px-3 py-2 text-sm font-bold">وضعیت: {{ \App\Models\CorporateRequest::statuses()[$corporate->status] }}</p>
    <dl class="grid gap-3 md:grid-cols-2"><div>شناسه ملی: {{ $corporate->national_id }}</div><div>شماره ثبت: {{ $corporate->company['register_number'] ?? '—' }}</div><div>نوع شرکت: {{ $corporate->company['company_type'] ?? '—' }}</div><div>تاریخ استعلام: {{ \App\Support\Jalali::format($corporate->looked_up_at) }}</div></dl>
    @if($corporate->status === 'pending')<div class="rounded-xl bg-blue-50 p-4 text-sm leading-8">درخواست در تاریخ {{ \App\Support\Jalali::format($corporate->submitted_at) }} برای بررسی ارسال شده است. نتیجه از طریق اعلان‌های حساب اطلاع‌رسانی می‌شود.</div>@endif
    @if($corporate->status === 'rejected')<p class="text-sm leading-8">دلیل رد درخواست را بررسی کنید. برای راهنمایی با پشتیبانی تماس بگیرید؛ برای ثبت درخواست دوباره به پروفایل برگردید.</p>@endif
    @if($corporate->reason)<p class="rounded-xl bg-amber-50 p-4 whitespace-pre-line">{{ $corporate->reason }}</p>@endif
    @foreach($corporate->events->where('event', 'approved') as $event)
        <p class="rounded-xl bg-emerald-50 p-4">شروع صورتحساب حقوقی: {{ \App\Support\Jalali::format(\Carbon\Carbon::parse($event->snapshot['billing_effective_at'])) }}</p>
    @endforeach
    @if(in_array($corporate->status, ['draft', 'needs_correction']))
    @include('customer.corporate.letter-notice')
    <form method="POST" action="{{ route('customer.corporate.submit', $corporate) }}" enctype="multipart/form-data" x-ref="application" class="space-y-5" data-submit-loading
        x-data="corporateApplication({{ $errors->any() || $corporate->status === 'needs_correction' ? 2 : 1 }}, @js(old('representative_role', $corporate->representative_role ?? 'signatory')))"
        @submit="if ($event.submitter?.value !== 'PATCH' && stage !== 3) { $event.preventDefault(); if (stage === 2) next(); }">
        @csrf
        <input type="hidden" name="version" value="{{ $corporate->version }}">
        <ol class="flex flex-wrap gap-3 text-sm font-bold" aria-label="مراحل درخواست">
            <li :aria-current="stage === 1 ? 'step' : null" :class="stage === 1 ? 'text-blue-700' : 'text-slate-500'">۱. تأیید شرکت</li>
            <li :aria-current="stage === 2 ? 'step' : null" :class="stage === 2 ? 'text-blue-700' : 'text-slate-500'">۲. نماینده و مدارک</li>
            <li :aria-current="stage === 3 ? 'step' : null" :class="stage === 3 ? 'text-blue-700' : 'text-slate-500'">۳. بازبینی و ارسال</li>
        </ol>
        <div x-show="stage === 1" class="space-y-4">
            <p class="text-sm leading-8">مشخصات ثبتی بالا را بررسی کنید. آیا این شرکت مورد نظر شماست؟ اطلاعات تماس و مدارک را در مرحله بعد تکمیل می‌کنید.</p>
            <button type="button" @click="stage = 2; $nextTick(() => $refs.detailsHeading.focus())" class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">تأیید شرکت و ادامه</button>
        </div>
        <div x-show="stage === 2" x-cloak class="space-y-4">
        <h3 x-ref="detailsHeading" tabindex="-1" class="text-lg font-black">نماینده، اطلاعات صورتحساب و مدارک</h3>
        <label class="block">سمت نماینده<select data-review="سمت نماینده" x-model="role" name="representative_role" class="mt-2 w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none"><option value="signatory" @selected(old('representative_role', $corporate->representative_role) === 'signatory')>صاحب امضای مجاز</option><option value="delegate" @selected(old('representative_role', $corporate->representative_role) === 'delegate')>نماینده معرفی‌شده</option></select></label>
        @foreach(['billing_email' => 'ایمیل صورتحساب', 'company_phone' => 'تلفن شرکت', 'address' => 'نشانی ثبتی', 'postal_code' => 'کد پستی ده رقمی', 'tax_number' => 'شماره اقتصادی (در صورت نیاز)'] as $field => $label)
            <label class="block">{{ $label }}<input data-review="{{ $label }}" name="{{ $field }}" value="{{ old($field, $corporate->$field) }}" :required="stage !== 1 && @js($field !== 'tax_number')" maxlength="{{ $field === 'address' ? 2000 : 255 }}" class="mt-2 w-full rounded-xl border border-slate-200 p-3 focus:border-blue-500 focus:outline-none" @if($field === 'billing_email') type="email" @endif></label>
            @error($field)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @endforeach
        <p class="text-sm text-slate-500">مدارک PDF، JPG یا PNG تا ۵ مگابایت. در درخواست اصلاح، مدارک قبلی حفظ می‌شوند؛ فقط مدارک نیازمند تغییر را بارگذاری کنید.</p>
        @foreach(\App\Models\CorporateDocument::labels() as $kind => $label)
            @php($existingDocument = $corporate->documents->where('version', $corporate->version)->firstWhere('kind', $kind))
            <div data-document="{{ $label }}" class="space-y-2 rounded-xl border border-slate-200 p-4">
                <label class="block font-bold" for="document-{{ $kind }}">{{ $label }} <span class="text-xs text-slate-500">{{ $existingDocument ? '(ثبت شده؛ جایگزینی اختیاری)' : '(الزامی)' }}</span></label>
                @if($existingDocument)<a href="{{ route('customer.corporate.document', $existingDocument) }}" class="block text-sm text-blue-600">{{ $existingDocument->original_name }}</a>@endif
                <input id="document-{{ $kind }}" type="file" name="{{ $kind }}" accept=".pdf,.jpg,.jpeg,.png" :required="stage !== 1 && !@js((bool) $existingDocument)" aria-describedby="document-{{ $kind }}-help{{ $errors->has($kind) ? ' document-'.$kind.'-error' : '' }}" @if($errors->has($kind)) aria-invalid="true" @endif @change="if ($el.files[0]?.size > 5 * 1024 * 1024) $el.setCustomValidity('حداکثر حجم فایل ۵ مگابایت است.'); else $el.setCustomValidity('')" class="block w-full text-sm">
                <p id="document-{{ $kind }}-help" class="text-xs text-slate-500">فایل PDF، JPG یا PNG، حداکثر ۵ مگابایت.</p>
                @error($kind)<p id="document-{{ $kind }}-error" role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="stage = 1" class="rounded-xl border px-4 py-3 text-sm font-bold">بازگشت</button>
            <button type="button" @click="next()" class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">بازبینی درخواست</button>
        </div>
        </div>
        <div x-show="stage === 3" x-cloak class="space-y-4">
        <h3 x-ref="reviewHeading" tabindex="-1" class="text-lg font-black">بازبینی و ارسال درخواست</h3>
        <p class="font-bold">{{ $corporate->company['name'] }} · {{ $corporate->national_id }}</p>
        <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"><template x-for="item in review" :key="item.label"><div><dt class="text-xs text-slate-500" x-text="item.label"></dt><dd class="mt-1 break-words text-sm" x-text="item.value"></dd></div></template></dl>
        <p class="text-sm leading-8">مدارک انتخاب‌شده همراه با درخواست ارسال می‌شوند. برای بررسی یا تغییر آن‌ها به مرحله قبل برگردید.</p>
        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm leading-7"><input type="checkbox" name="consent" value="1" :disabled="stage !== 3" required class="mt-2"><span>اختیار نمایندگی شرکت و صحت اطلاعات را تأیید می‌کنم. پس از تأیید، سرویس‌های فعلی و مانده کیف پول این حساب برای شرکت استفاده خواهند شد. صورتحساب حقوقی از ابتدای ماه شمسی بعد از تأیید آغاز می‌شود؛ قیمت‌ها و سوابق قبلی حفظ می‌شوند.</span></label>
        <div class="flex flex-wrap gap-3"><button type="button" @click="stage = 2" class="rounded-xl border px-4 py-3 font-bold">ویرایش اطلاعات</button><button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">{{ $corporate->status === 'needs_correction' ? 'ارسال مجدد برای بررسی' : 'ارسال برای بررسی' }}</button></div>
        </div>
        <button type="submit" name="_method" value="PATCH" formaction="{{ route('customer.corporate.save', $corporate) }}" formnovalidate class="rounded-xl border border-blue-200 px-4 py-3 text-sm font-bold text-blue-700">ذخیره و ادامه بعداً</button>
        <p class="text-xs text-slate-500">ذخیره پیش‌نویس، درخواست را برای بررسی ارسال نمی‌کند. مدارک بارگذاری‌شده نیز ذخیره می‌شوند.</p>
    </form>
    @if($corporate->status === 'draft')
        <details class="rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-bold">شرکت اشتباه است؟</summary><p class="my-3 text-sm leading-7">برای استعلام شرکت دیگری، این پیش‌نویس و اطلاعات آن را حذف کنید.</p><form method="POST" action="{{ route('customer.corporate.discard', $corporate) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-red-700">حذف پیش‌نویس و انتخاب شرکت دیگر</button></form></details>
    @endif
    @endif
    @if($corporate->documents->where('version', $corporate->version)->isNotEmpty())
    <h3 class="font-black">مدارک ثبت‌شده</h3>
    @foreach($corporate->documents->where('version', $corporate->version) as $document)
        <a class="block text-blue-600" href="{{ route('customer.corporate.document', $document) }}">{{ \App\Models\CorporateDocument::labels()[$document->kind] ?? $document->kind }} — {{ $document->original_name }}</a>
    @endforeach
    @endif
    @if($corporate->events->isNotEmpty())
    <details class="rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer font-bold">تاریخچه درخواست</summary>
    @foreach($corporate->events as $event)<p class="text-sm text-slate-600">{{ \App\Support\Jalali::format($event->created_at) }} · نسخه {{ $event->version }} · {{ $event->event === 'submitted' ? 'ارسال برای بررسی' : (\App\Models\CorporateRequest::statuses()[$event->event] ?? $event->event) }}</p>@endforeach
    </details>
    @endif
</section>
@endsection
