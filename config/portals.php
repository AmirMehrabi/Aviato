<?php

return [
    'admin' => [
        'login_path' => trim(env('ADMIN_LOGIN_PATH', 'admin/login'), '/'),
        'home_path' => trim(env('ADMIN_HOME_PATH', 'admin/dashboard'), '/'),
    ],

    'customer' => [
        'login_path' => trim(env('CUSTOMER_LOGIN_PATH', 'login'), '/'),
        'register_path' => trim(env('CUSTOMER_REGISTER_PATH', 'register'), '/'),
        'home_path' => trim(env('CUSTOMER_HOME_PATH', 'dashboard'), '/'),
    ],
];
