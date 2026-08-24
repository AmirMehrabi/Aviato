<?php

return [
    'code_prefix' => 'AVT',
    'code_pepper' => env('PROMOTION_CODE_PEPPER', env('APP_KEY')),
    'reservation_minutes' => (int) env('PROMOTION_RESERVATION_MINUTES', 30),
    'default_expiry_days' => (int) env('PROMOTION_DEFAULT_EXPIRY_DAYS', 90),
    'max_credit_amount' => (int) env('PROMOTION_MAX_CREDIT_AMOUNT', 500_000_000),
    'max_bonus_amount' => (int) env('PROMOTION_MAX_BONUS_AMOUNT', 500_000_000),
    'max_batch_size' => (int) env('PROMOTION_MAX_BATCH_SIZE', 25_000),
    'max_campaign_liability' => (int) env('PROMOTION_MAX_CAMPAIGN_LIABILITY', 500_000_000_000),
];
