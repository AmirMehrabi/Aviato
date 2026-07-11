<?php

return [
    'required' => 'وارد کردن فیلد :attribute الزامی است.',
    'required_without' => 'وارد کردن فیلد :attribute الزامی است، مگر اینکه :values وارد شده باشد.',
    'required_without_all' => 'وارد کردن فیلد :attribute الزامی است.',
    'required_with' => 'وارد کردن فیلد :attribute الزامی است.',
    'required_with_all' => 'وارد کردن فیلد :attribute الزامی است.',
    'email' => 'فیلد :attribute باید یک ایمیل معتبر باشد.',
    'max' => [
        'string' => 'تعداد نویسه‌های :attribute نباید بیشتر از :max باشد.',
    ],
    'min' => [
        'string' => 'تعداد نویسه‌های :attribute باید حداقل :min باشد.',
    ],
    'confirmed' => 'تکرار :attribute با مقدار آن مطابقت ندارد.',
    'unique' => 'این مقدار برای :attribute قبلا ثبت شده است.',
    'regex' => 'قالب :attribute معتبر نیست.',
    'string' => 'فیلد :attribute باید به صورت متن وارد شود.',
    'password' => [
        'letters' => 'رمز عبور باید حداقل یک حرف داشته باشد.',
        'mixed' => 'رمز عبور باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => 'رمز عبور باید حداقل یک عدد داشته باشد.',
        'symbols' => 'رمز عبور باید حداقل یک نماد داشته باشد.',
        'uncompromised' => 'این رمز عبور در یک نشت اطلاعاتی دیده شده است؛ رمز دیگری انتخاب کنید.',
    ],
    'custom' => [
        'email' => [
            'required_without' => 'وارد کردن ایمیل یا موبایل الزامی است.',
        ],
        'phone' => [
            'required_without' => 'وارد کردن ایمیل یا موبایل الزامی است.',
        ],
    ],
    'attributes' => [
        'login' => 'ایمیل یا شماره موبایل',
        'password' => 'رمز عبور',
        'password_confirmation' => 'تکرار رمز عبور',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'email' => 'ایمیل',
        'phone' => 'موبایل',
    ],
];
