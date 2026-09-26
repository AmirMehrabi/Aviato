<div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
    <h2 class="font-black text-blue-950">هشدار موجودی کیف‌پول</h2>
    <p class="mt-2 text-xs leading-6 text-blue-900">درصد موجودی مؤثر نسبت به هزینه ماهانه همه منابعی که از کیف‌پول مالک پرداخت می‌شوند. این درصدها برای همه فضاهای کاری پیش‌فرض هستند و در مدیریت هر فضای کاری قابل تغییرند.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <x-form.input name="wallet_alert_percentages" label="درصدهای هشدار" :value="old('wallet_alert_percentages', implode(', ', $walletAlertPercentages))" dir-ltr help="مثال: 15, 10, 5" />
        <x-form.select name="wallet_alert_recipient_policy" label="دریافت‌کنندگان پیش‌فرض" :selected="old('wallet_alert_recipient_policy', $walletAlertRecipientPolicy)" :options="['owner' => 'فقط مالک فضای کاری', 'owner_and_billing' => 'مالک و اعضای مالی']" />
        <x-form.checkbox name="customer_wallet_negative_sms_enabled" label="ارسال پیامک فعال باشد" :checked="$customerWalletNegativeSmsEnabled" />
        <x-form.input name="customer_wallet_negative_sms_template" label="Template کاوه‌نگار" :value="$customerWalletNegativeSmsTemplate" dir-ltr help="token: نام، token2: موجودی در واحد صورتحساب، token3: درصد باقی‌مانده" />
    </div>
</div>
<div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><h2 class="font-black text-amber-950">زمان اعمال محدودیت</h2><p class="text-xs leading-6 text-amber-900">محدودیت استفاده و توقف ماشین‌های فعال فقط زمانی اعمال می‌شود که موجودی مؤثر کیف‌پول به صفر یا کمتر برسد. موجودی مؤثر شامل مصرف ثبت‌نشده نیز هست.</p></div>
<div class="grid gap-4 md:grid-cols-2"><x-form.input name="unverified_customer_vm_limit" type="number" label="سقف VM حساب تأییدنشده" :value="$unverifiedCustomerVmLimit" min="0" max="1000000" dir-ltr /><x-form.input name="verified_customer_vm_limit" type="number" label="سقف VM حساب تأییدشده" :value="$verifiedCustomerVmLimit" min="0" max="1000000" dir-ltr /><x-form.input name="deleted_vm_cooldown_days" type="number" label="روزهای نگهداری سهمیه VM حذف‌شده" :value="$deletedVmCooldownDays" min="0" max="3650" dir-ltr /><x-form.input name="vm_rebuild_fee_multiplier_percentage" type="number" label="درصد هزینه بازسازی" :value="$vmRebuildFeeMultiplierPercentage" min="0" max="100" step="0.01" dir-ltr /></div>
