<?php

return [
    'admin' => [
        'domain' => env('ADMIN_PORTAL_DOMAIN', 'admin.aviato.ir'),
        'login_path' => trim(env('ADMIN_LOGIN_PATH', 'login'), '/'),
        'home_path' => trim(env('ADMIN_HOME_PATH', 'dashboard'), '/'),
    ],

    'customer' => [
        'domain' => env('CUSTOMER_PORTAL_DOMAIN', 'cp.aviato.ir'),
        'login_path' => trim(env('CUSTOMER_LOGIN_PATH', 'login'), '/'),
        'register_path' => trim(env('CUSTOMER_REGISTER_PATH', 'register'), '/'),
        'home_path' => trim(env('CUSTOMER_HOME_PATH', 'dashboard'), '/'),
    ],
];
