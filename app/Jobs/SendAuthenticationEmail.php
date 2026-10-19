<?php

namespace App\Jobs;

use App\Mail\CustomerLoginOtpCodeMail;
use App\Mail\CustomerPasswordResetCodeMail;
use App\Mail\CustomerVerificationCodeMail;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAuthenticationEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public int $deliveryId, public int $customerId, public string $event, public string $code, public int $expiresAt) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(): void
    {
        $delivery = DB::table('notification_deliveries')->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'suppressed'])) {
            return;
        }
        $customer = Customer::find($this->customerId);
        if (! $customer || $customer->email !== $delivery->recipient || now()->timestamp >= $this->expiresAt || ! $this->validCode($customer)) {
            DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'suppressed', 'updated_at' => now()]);

            return;
        }
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->increment('attempts');
        $mail = match ($this->event) {
            'password_reset' => new CustomerPasswordResetCodeMail($customer, $this->code),
            'login_code' => new CustomerLoginOtpCodeMail($customer, $this->code),
            'email_verification' => new CustomerVerificationCodeMail($customer, $this->code),
        };
        // Never silently send security codes to Laravel's log/array transport.
        Mail::mailer('settings_smtp')->to($delivery->recipient)->send($mail);
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
    }

    private function validCode(Customer $customer): bool
    {
        if ($this->event === 'password_reset') {
            $record = DB::table('customer_password_reset_tokens')->where('email', $customer->email)->first();

            return $record && $record->created_at && Carbon::parse($record->created_at)->addMinutes(10)->isFuture() && Hash::check($this->code, $record->token);
        }
        if ($this->event === 'email_verification') {
            return ! $customer->email_verified_at && $customer->email_verification_expires_at?->isFuture()
                && $customer->email_verification_code && Hash::check($this->code, $customer->email_verification_code);
        }
        $record = DB::table('customer_login_otp_challenges')->where('customer_id', $customer->id)
            ->where('identifier', $customer->email)->where('channel', 'email')->whereNull('consumed_at')
            ->where('expires_at', '>', now())->latest('id')->first();

        return $record && Hash::check($this->code, $record->token);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
