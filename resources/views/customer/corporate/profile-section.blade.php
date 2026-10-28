@php
    $corporateProfile = \App\Models\CorporateProfile::where('customer_id', $customer->id)->first();
    $corporateRequests = \App\Models\CorporateRequest::where('customer_id', $customer->id)->latest('id')->get();
@endphp
<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 space-y-4" id="corporate-identity">
    <h2 class="text-xl font-black">اطلاعات حقوقی</h2>
    @if($corporateProfile)
        <p class="font-bold text-emerald-700">حساب حقوقی تأیید شده: {{ $corporateProfile->company['name'] }}</p>
        <p class="text-sm">شروع صورتحساب حقوقی: {{ \App\Support\Jalali::format($corporateProfile->billing_effective_at) }}</p>
    @else
        <p class="text-sm leading-7 text-slate-500">برای ثبت شرکت، شناسه ملی حقوقی و مدارک اختیار نماینده لازم است. هویت فردی شما برای ورود به حساب حفظ می‌شود.</p>
    @endif
    @foreach($corporateRequests as $corporate)
        <a class="block rounded-xl bg-slate-50 p-3 text-blue-700" href="{{ route('customer.corporate.show', $corporate) }}">درخواست {{ $corporate->id }} · {{ $corporate->company['name'] }} · {{ \App\Models\CorporateRequest::statuses()[$corporate->status] }}</a>
    @endforeach
    @if(\App\Models\AppSetting::getValue('corporate.enabled', false) && ! $corporateRequests->whereIn('status', ['draft', 'pending', 'needs_correction'])->count())
        @if($customer->hasVerifiedNationalCode())
        <form method="POST" action="{{ route('customer.corporate.start') }}" class="flex flex-col gap-3 sm:flex-row" data-submit-loading>
            @csrf
            <input name="national_id" value="{{ old('national_id', $corporateProfile?->national_id) }}" required inputmode="numeric" maxlength="11" placeholder="شناسه ملی ۱۱ رقمی شرکت" class="rounded-xl border p-3" dir="ltr">
            <button class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white">{{ $corporateProfile ? 'درخواست اصلاح مشخصات حقوقی' : 'درخواست تبدیل حساب به حقوقی' }}</button>
        </form>
        @error('national_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @else<p class="text-sm text-amber-700">ابتدا کد ملی فردی خود را در بخش بالا تأیید کنید.</p>@endif
    @endif
</section>
