<?php

namespace App\Mail;

use App\Models\Customer;

class CustomerPasswordResetCodeMail extends BrandedNotificationMail
{
    public function __construct(public Customer $customer, string $code)
    {
        parent::__construct('password_reset', ['name' => $customer->name], code: $code);
    }
}
