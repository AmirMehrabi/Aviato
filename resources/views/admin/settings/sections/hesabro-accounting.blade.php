<x-form.checkbox name="hesabro_accounting_enabled" label="ارسال خودکار پرداخت‌های موفق فعال باشد" :checked="$hesabroAccountingEnabled" wrapper-class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold" />
<p class="text-sm leading-7 text-slate-600">این اتصال مستقل از درگاه پرداخت حسابرو است. فقط پرداخت‌های موفق جدید به صف ارسال می‌شوند. پرداخت‌های قدیمی را از صفحه جزئیات هر پرداخت ارسال کنید.</p>
<div class="grid gap-4 md:grid-cols-2">
    <x-form.input name="hesabro_accounting_username" label="نام کاربری API" :value="$hesabroAccountingSettings['username']" dir-ltr />
    <x-form.input name="hesabro_accounting_password" type="password" label="رمز عبور API" value="" dir-ltr help="برای حفظ رمز فعلی، خالی بگذارید." />
    <x-form.input name="hesabro_accounting_client" label="شناسه حسابرو (بدون @)" :value="$hesabroAccountingSettings['client']" dir-ltr />
    <x-form.input name="hesabro_accounting_product_id" type="number" min="0" label="شناسه خدمت (product_id)" :value="$hesabroAccountingSettings['product_id']" dir-ltr />
    @foreach ([
        'branch_id' => 'Branch ID',
        'model_id' => 'Model ID',
        'm_id_debtor' => 'm_id_debtor',
        't_id_debtor_other' => 't_id_debtor_other',
        't_id_debtor' => 't_id_debtor',
        'm_id_creditor' => 'm_id_creditor',
        't_id_creditor_other' => 't_id_creditor_other',
        't_id_creditor' => 't_id_creditor',
    ] as $field => $label)
        <x-form.input :name="'hesabro_accounting_'.$field" type="number" min="0" :label="$label" :value="$hesabroAccountingSettings[$field]" dir-ltr />
    @endforeach
</div>
