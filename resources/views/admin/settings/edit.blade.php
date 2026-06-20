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
            <div
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                x-data="{ gatewayTab: @js(old('default_payment_gateway', $defaultPaymentGateway)) }"
            >
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-950">درگاه‌های پرداخت</h2>
                        <p class="mt-1 text-xs leading-6 text-slate-500">پرداخت آنلاین را یک‌جا غیرفعال کنید یا چند درگاه را هم‌زمان در اختیار مشتری قرار دهید.</p>
                    </div>
                    <div class="w-full md:w-72">
                        <x-form.checkbox name="payments_enabled" label="پرداخت آنلاین فعال باشد" :checked="$paymentsEnabled" wrapper-class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm font-bold" />
                    </div>
                </div>

                @error('payments_enabled')
                    <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-5 grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="space-y-2">
                        @foreach ($paymentGateways as $gateway => $label)
                            @php
                                $enabled = $gateway === 'mellat' ? $mellatPaymentEnabled : $hesabroPaymentEnabled;
                            @endphp
                            <button
                                type="button"
                                @click="gatewayTab = '{{ $gateway }}'"
                                class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-right transition"
                                :class="gatewayTab === '{{ $gateway }}' ? 'border-[#2563EB] bg-blue-50 text-[#1D4ED8]' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            >
                                <span class="text-sm font-black">{{ $label }}</span>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black {{ $enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $enabled ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </button>
                        @endforeach

                        <div class="pt-2">
                            <x-form.select name="default_payment_gateway" label="درگاه پیش‌فرض" :selected="$defaultPaymentGateway" :options="$paymentGateways" help="این درگاه در صفحه کیف پول از ابتدا انتخاب می‌شود." />
                        </div>
                    </div>

                    <div>
                        <section x-show="gatewayTab === 'mellat'" x-cloak class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black text-slate-950">بانک ملت</h3>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">اتصال مستقیم Behpardakht با تایید و تسویه تراکنش.</p>
                                </div>
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">Mellat</span>
                            </div>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <x-form.checkbox name="mellat_payment_enabled" label="درگاه ملت فعال باشد" :checked="$mellatPaymentEnabled" wrapper-class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-bold md:col-span-2" />
                                <x-form.select name="mellat_payment_mode" label="محیط درگاه" :selected="$mellatPaymentMode" :options="$mellatPaymentModes" />
                                <x-form.input name="mellat_terminal_id" type="number" label="Terminal ID" :value="$mellatTerminalId" min="1" dir-ltr />
                                <x-form.input name="mellat_username" label="Username" :value="$mellatUsername" dir-ltr />
                                <x-form.input name="mellat_password" type="password" label="Password" value="" dir-ltr help="برای حفظ رمز فعلی، خالی بگذارید." />
                            </div>
                        </section>

                        <section x-show="gatewayTab === 'hesabro'" x-cloak class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black text-slate-950">حسابرو</h3>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">ایجاد درخواست شارژ کیف پول حسابرو با Basic Authentication و هدایت مشتری به لینک پرداخت.</p>
                                </div>
                                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-black text-violet-700">Hesabro</span>
                            </div>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <x-form.checkbox name="hesabro_payment_enabled" label="درگاه حسابرو فعال باشد" :checked="$hesabroPaymentEnabled" wrapper-class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-bold md:col-span-2" />
                                <x-form.input name="hesabro_client" label="Client (@client)" :value="$hesabroClient" dir-ltr help="بدون @ وارد کنید." />
                                <x-form.input name="hesabro_client_id" label="Client ID" :value="$hesabroClientId" dir-ltr />
                                <x-form.input name="hesabro_client_secret" type="password" label="Client Secret" value="" dir-ltr help="برای حفظ مقدار فعلی، خالی بگذارید." />
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-6 text-slate-600 md:col-span-2" dir="ltr">
                                    Base URL: https://api.hesabro.ir
                                    Endpoint: /@client/payment-service/wallet/user-charge
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">قیمت‌گذاری Hetzner</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">قیمت‌های Hetzner دلاری هستند. قبل از شارژ مشتری، قیمت با نرخ فعلی دلار به ریال تبدیل می‌شود و در metadata تراکنش ذخیره می‌شود.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="hetzner_usd_to_irr_rate" type="number" label="نرخ USD به IRR" :value="$hetznerUsdToIrrRate" min="0" dir-ltr help="اگر 0 باشد، ساخت و شارژ VMهای Hetzner متوقف می‌شود." />
                    <x-form.input name="hetzner_price_markup_percentage" type="number" label="درصد Markup روی قیمت Hetzner" :value="$hetznerPriceMarkupPercentage" min="0" step="0.01" dir-ltr />
                </div>
            </div>
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
                <h2 class="text-sm font-black text-slate-900">هشدار کیف پول منفی مشتری</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">وقتی موجودی کیف پول به این مقدار یا پایین‌تر برسد، برای مشتری پیامک هشدار ارسال می‌شود. پس از 3 هشدار، حساب به‌طور خودکار تعلیق و VMهای فعال خاموش می‌شوند.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.input name="customer_wallet_negative_threshold" type="number" label="آستانه کیف پول منفی" :value="$customerWalletNegativeThreshold" dir-ltr />
                    <x-form.checkbox name="customer_wallet_negative_sms_enabled" label="ارسال پیامک هشدار کیف پول فعال باشد" :checked="$customerWalletNegativeSmsEnabled" />
                    <x-form.input name="customer_wallet_negative_sms_template" label="Template کاوه‌نگار هشدار کیف پول" :value="$customerWalletNegativeSmsTemplate" dir-ltr />
                </div>
                <p class="mt-3 text-xs leading-6 text-slate-500">این Template فقط یک token می‌گیرد و باید از placeholder کاوه‌نگار %token برای نام کوچک مشتری استفاده کند.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">اعلان‌های تیکت و SMTP</h2>
                <p class="mt-1 text-xs leading-6 text-slate-500">ارسال ایمیل و پیامک تیکت اختیاری است. اعلان داخلی پنل همیشه برای رویدادهای تیکت ثبت می‌شود.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-form.checkbox name="ticket_email_notifications_enabled" label="ارسال ایمیل برای تیکت فعال باشد" :checked="$ticketEmailNotificationsEnabled" />
                    <x-form.checkbox name="ticket_sms_notifications_enabled" label="ارسال پیامک برای تیکت فعال باشد" :checked="$ticketSmsNotificationsEnabled" />
                    <x-form.input name="smtp_host" label="SMTP Host" :value="$smtpHost" dir-ltr />
                    <x-form.input name="smtp_port" type="number" label="SMTP Port" :value="$smtpPort" dir-ltr />
                    <x-form.input name="smtp_username" label="SMTP Username" :value="$smtpUsername" dir-ltr />
                    <x-form.input name="smtp_password" type="password" label="SMTP Password" value="" dir-ltr help="برای حفظ رمز فعلی، این فیلد را خالی بگذارید." />
                    <x-form.select name="smtp_encryption" label="Encryption" :selected="$smtpEncryption" :options="$smtpEncryptions" />
                    <x-form.input name="smtp_from_address" label="From Email" :value="$smtpFromAddress" dir-ltr />
                    <x-form.input name="smtp_from_name" label="From Name" :value="$smtpFromName" />
                </div>
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h3 class="text-xs font-black text-amber-900">Template های کاوه‌نگار برای تیکت</h3>
                    <p class="mt-1 text-xs leading-6 text-amber-800">توکن‌ها: token = شماره تیکت، token2 = نام مشتری/اپراتور، token3 = دسته‌بندی یا وضعیت.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <x-form.input name="ticket_kavenegar_customer_created_template" label="Customer: ticket created" :value="$ticketKavenegarCustomerCreatedTemplate" dir-ltr />
                        <x-form.input name="ticket_kavenegar_admin_new_template" label="Admin: new ticket" :value="$ticketKavenegarAdminNewTemplate" dir-ltr />
                        <x-form.input name="ticket_kavenegar_customer_reply_template" label="Customer: admin reply" :value="$ticketKavenegarCustomerReplyTemplate" dir-ltr />
                        <x-form.input name="ticket_kavenegar_admin_reply_template" label="Admin: customer reply" :value="$ticketKavenegarAdminReplyTemplate" dir-ltr />
                        <x-form.input name="ticket_kavenegar_assignment_template" label="Assignment notice" :value="$ticketKavenegarAssignmentTemplate" dir-ltr />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-900">هزینه اولیه ساخت ماشین مجازی</h2>
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
