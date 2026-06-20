<?php

return [
    'default' => env('PAYMENTS_DEFAULT_PROVIDER', 'mellat'),

    'mellat' => [
        'test_wsdl' => env('MELLAT_TEST_WSDL', 'https://pgw.dev.bpmellat.ir/pgwchannel/services/pgw?wsdl'),
        'production_wsdl' => env('MELLAT_PRODUCTION_WSDL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'test_redirect_url' => env('MELLAT_TEST_REDIRECT_URL', 'https://pgw.dev.bpmellat.ir/pgwchannel/startpay.mellat'),
        'production_redirect_url' => env('MELLAT_PRODUCTION_REDIRECT_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat'),
    ],

    'hesabro' => [
        'base_url' => env('HESABRO_BASE_URL', 'https://api.hesabro.ir'),
    ],
];
