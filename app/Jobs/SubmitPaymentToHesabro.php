<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Payment;
use App\Services\HesabroAccountingService;
use App\Services\HesabroSubmissionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SubmitPaymentToHesabro implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $paymentId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hesabro-payment-'.$this->paymentId))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(HesabroAccountingService $hesabro): void
    {
        $payment = Payment::query()->with('customer')->find($this->paymentId);
        if (! $payment || ! $payment->isSuccessful() || $payment->hesabro_status === 'submitted') {
            return;
        }
        if (! AppSetting::hesabroAccountingEnabled()) {
            $payment->forceFill(['hesabro_status' => null])->save();

            return;
        }

        $payment->increment('hesabro_attempts');

        try {
            $result = $hesabro->submit($payment);
            $payment->forceFill([
                'hesabro_status' => 'submitted',
                'hesabro_factor_id' => $result['factor_id'],
                'hesabro_error' => null,
                'hesabro_submitted_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $retryable = ! $exception instanceof HesabroSubmissionException || $exception->retryable;
            $payment->forceFill([
                'hesabro_status' => $retryable ? 'queued' : 'failed',
                'hesabro_error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();
            if ($retryable) {
                throw $exception;
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Payment::query()->whereKey($this->paymentId)->where('hesabro_status', '!=', 'submitted')
            ->update(['hesabro_status' => 'failed', 'hesabro_error' => mb_substr($exception->getMessage(), 0, 500)]);
    }
}
