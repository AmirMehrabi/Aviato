<?php

namespace App\Mail;

use App\Models\Customer;

class CustomerVerificationCodeMail extends BrandedNotificationMail
{
    public function __construct(public Customer $customer, string $code)
    {
        parent::__construct('email_verification', ['name' => $customer->name], code: $code);
    }
}
