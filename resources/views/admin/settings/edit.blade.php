@extends('layouts.admin')

@section('title', 'تنظیمات')

@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-[#B8D6FF] bg-[#EBF3FF] px-4 py-3 text-sm font-bold text-[#031B4E]">{{ session('status') }}</div>
    @endif

    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black">تنظیمات سیستم</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">واحد پولی برای نمایش قیمت‌ها، کیف پول و صورتحساب‌ها استفاده می‌شود. همچنین می‌توانید تایید ثبت‌نام مشتری را غیرفعال یا روی ایمیل / پیامک تنظیم کنید.</p>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-5">
            @csrf @method('PATCH')
            <x-form.select name="currency" label="واحد پولی Billing" :selected="$currency" :options="$currencies" />
            <x-form.select name="customer_verification_mode" label="روش تایید ثبت نام مشتری" :selected="$verificationMode" :options="$verificationModes" />
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">استعلام کد ملی با سرویس شاهکار</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">وقتی فعال باشد، ثبت کد ملی در پروفایل مشتری از طریق API سرویس Zohal/Shahkar بررسی می‌شود و هر مشتری در هر ساعت 5 بار فرصت استعلام دارد.</p>
                <div class="mt-4 grid gap-4">
                    <x-form.checkbox name="national_code_verification_enabled" label="استعلام برخط کد ملی فعال باشد" :checked="$nationalCodeVerificationEnabled" />
                    <x-form.input name="national_code_verification_token" type="password" label="توکن API استعلام کد ملی" value="" dir-ltr help="برای حفظ توکن فعلی، این فیلد را خالی بگذارید." />
                </div>
            </div>
            <x-form.select name="sms_gateway" label="درگاه ارسال پیامک" :selected="$smsGateway" :options="$smsGateways" />

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">هزینه اولیه ساخت VPS</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">در صورت فعال بودن، هنگام ثبت اولین درخواست ساخت VM درصدی از قیمت ماهانه پلن از کیف پول مالک پروژه کسر می‌شود.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.checkbox name="vm_creation_charge_enabled" label="کسر هزینه ساخت فعال باشد" :checked="$vmCreationChargeEnabled" />
                    <x-form.input name="vm_creation_charge_percentage" type="number" label="درصد هزینه ساخت" :value="$vmCreationChargePercentage" min="0" max="100" step="0.01" dir-ltr help="مثلا 10 یعنی ۱۰٪ قیمت ماهانه پلن." />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">سطح حساب و کنترل سوء استفاده</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">حساب تایید نشده با کد ملی محدود می‌شود. VM حذف شده تا پایان دوره cooldown همچنان سهمیه حساب تایید نشده را مصرف می‌کند.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="unverified_customer_vm_limit" type="number" label="سقف VM حساب تایید نشده" :value="$unverifiedCustomerVmLimit" min="0" max="1000000" dir-ltr help="0 یعنی مشتری قبل از ساخت اولین VM باید کد ملی را تایید کند. پیشنهاد فعلی: 2" />
                    <x-form.input name="verified_customer_vm_limit" type="number" label="سقف VM حساب تایید شده" :value="$verifiedCustomerVmLimit" min="0" max="1000000" dir-ltr help="0 یعنی بدون سقف." />
                    <x-form.input name="deleted_vm_cooldown_days" type="number" label="روزهای نگهداری سهمیه VM حذف شده" :value="$deletedVmCooldownDays" min="0" max="3650" dir-ltr help="برای جلوگیری از ساخت، حذف و ساخت دوباره." />
                    <x-form.input name="vm_rebuild_fee_multiplier_percentage" type="number" label="درصد هزینه بازسازی نسبت به هزینه ساخت" :value="$vmRebuildFeeMultiplierPercentage" min="0" max="100" step="0.01" dir-ltr help="50 یعنی نصف هزینه ساخت تنظیم شده." />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">تنظیمات درگاه پیامک SMS0098</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">زمانی استفاده می‌شود که روش تایید روی پیامک و درگاه انتخابی SMS0098 باشد.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="sms0098_username" label="Username" :value="$sms0098Username" dir-ltr />
                    <x-form.input name="sms0098_panel_no" label="Panel Number (PnlNo / FROM)" :value="$sms0098PanelNo" dir-ltr />
                </div>
                <div class="mt-4">
                    <x-form.input name="sms0098_password" type="password" label="Password" value="" dir-ltr help="برای حفظ رمز فعلی، این فیلد را خالی بگذارید." />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">تنظیمات Lookup کاوه‌نگار</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">کاوه‌نگار متن مستقیم دریافت نمی‌کند؛ کد تایید به عنوان token در template تنظیم‌شده ارسال می‌شود.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="kavenegar_template" label="Template" :value="$kavenegarTemplate" dir-ltr />
                    <x-form.input name="kavenegar_api_key" type="password" label="API Key" value="" dir-ltr help="برای حفظ کلید فعلی، این فیلد را خالی بگذارید." />
                </div>
            </div>

            <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره تنظیمات</button>
        </form>
    </div>
</div>
@endsection
