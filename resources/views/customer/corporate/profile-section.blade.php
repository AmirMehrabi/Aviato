@php
    $corporateProfile = \App\Models\CorporateProfile::where('customer_id', $customer->id)->first();
    $corporateRequests = \App\Models\CorporateRequest::where('customer_id', $customer->id)->latest('id')->get();
    $openCorporate = $corporateRequests->first(fn ($item) => in_array($item->status, ['draft', 'pending', 'needs_correction']));
@endphp
<section class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" id="corporate-identity">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-black">حساب حقوقی</h2>
        <span class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-bold">{{ $corporateProfile ? 'حقوقی تأیید شده' : 'حساب حقیقی' }}</span>
    </div>
    @if($corporateProfile)
        <p class="font-bold text-emerald-700">{{ $corporateProfile->company['name'] }}</p>
        <p class="text-sm">شروع صورتحساب حقوقی: {{ \App\Support\Jalali::format($corporateProfile->billing_effective_at) }}</p>
    @else
        <p class="text-sm leading-7 text-slate-500">برای استفاده از حساب به نام شرکت و دریافت صورتحساب حقوقی، مشخصات و مدارک شرکت را برای بررسی ارسال کنید.</p>
    @endif
    @if($openCorporate)
        <div class="space-y-3 rounded-xl bg-blue-50 p-4">
            <p class="font-bold">{{ $openCorporate->company['name'] }} · {{ \App\Models\CorporateRequest::statuses()[$openCorporate->status] }}</p>
            <p class="text-sm leading-7">{{ ['draft' => 'درخواست شما هنوز ارسال نشده است. اطلاعات را تکمیل کنید و برای بررسی بفرستید.', 'pending' => 'درخواست ارسال شده است. نتیجه از طریق اعلان‌های حساب اطلاع‌رسانی می‌شود.', 'needs_correction' => 'درخواست نیازمند اصلاح است. توضیح کارشناس را بررسی و موارد خواسته‌شده را تکمیل کنید.'][$openCorporate->status] }}</p>
            <a class="inline-flex rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white" href="{{ route('customer.corporate.show', $openCorporate) }}">{{ ['draft' => 'ادامه درخواست', 'pending' => 'مشاهده وضعیت درخواست', 'needs_correction' => 'اصلاح و ارسال مجدد'][$openCorporate->status] }}</a>
        </div>
    @elseif(\App\Models\AppSetting::getValue('corporate.enabled', false))
        <a class="inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white" href="{{ route('customer.corporate.create') }}">{{ $corporateProfile ? 'درخواست اصلاح مشخصات حقوقی' : 'درخواست حساب حقوقی' }}</a>
    @else
        <p class="text-sm text-slate-500">ثبت درخواست جدید در حال حاضر غیرفعال است.</p>
    @endif
    @if($corporateRequests->whereNotIn('status', ['draft', 'pending', 'needs_correction'])->isNotEmpty())
        <details class="rounded-xl border border-slate-100 p-4"><summary class="cursor-pointer text-sm font-bold">درخواست‌های قبلی</summary>
            @foreach($corporateRequests->whereNotIn('status', ['draft', 'pending', 'needs_correction']) as $corporate)
                <a class="mt-3 block text-sm text-blue-700" href="{{ route('customer.corporate.show', $corporate) }}">{{ $corporate->company['name'] }} · {{ \App\Models\CorporateRequest::statuses()[$corporate->status] }} · {{ \App\Support\Jalali::format($corporate->created_at) }}</a>
            @endforeach
        </details>
    @endif
</section>
