<?php

namespace App\Mail;

use App\Models\Customer;

class CustomerLoginOtpCodeMail extends BrandedNotificationMail
{
    public function __construct(public Customer $customer, string $code)
    {
        parent::__construct('login_code', ['name' => $customer->name], code: $code);
    }
}
