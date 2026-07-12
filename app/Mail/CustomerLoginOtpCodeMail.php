<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerLoginOtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'کد ورود یک‌بارمصرف پنل مشتری');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-login-otp-code');
    }
}
