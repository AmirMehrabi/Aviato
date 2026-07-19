<?php

return [
    'required' => ':attribute را وارد کنید.',
    'required_without' => ':attribute را وارد کنید.',
    'required_without_all' => ':attribute را وارد کنید.',
    'required_with' => ':attribute را وارد کنید.',
    'required_with_all' => ':attribute را وارد کنید.',
    'email' => 'ایمیل واردشده معتبر نیست.',
    'max' => [
        'string' => 'تعداد نویسه‌های :attribute نباید بیشتر از :max باشد.',
    ],
    'min' => [
        'string' => 'تعداد نویسه‌های :attribute باید حداقل :min باشد.',
    ],
    'confirmed' => ':attribute و تکرار آن یکی نیستند.',
    'unique' => 'این :attribute قبلاً ثبت شده است.',
    'regex' => 'فرمت :attribute درست نیست.',
    'string' => ':attribute را به صورت متن وارد کنید.',
    'password' => [
        'letters' => 'رمز عبور باید حداقل یک حرف داشته باشد.',
        'mixed' => 'رمز عبور باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => 'رمز عبور باید حداقل یک عدد داشته باشد.',
        'symbols' => 'رمز عبور باید حداقل یک نماد داشته باشد.',
        'uncompromised' => 'این رمز عبور در یک نشت اطلاعاتی دیده شده است؛ رمز دیگری انتخاب کنید.',
    ],
    'custom' => [
        'email' => [
            'required_without' => 'ایمیل یا شماره موبایل را وارد کنید.',
        ],
        'phone' => [
            'required_without' => 'ایمیل یا شماره موبایل را وارد کنید.',
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
