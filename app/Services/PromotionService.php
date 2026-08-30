<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PromotionCampaign;
use App\Models\PromotionCode;
use App\Models\PromotionEvent;
use App\Models\PromotionException;
use App\Models\PromotionRedemption;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PromotionService
{
    public function __construct(private readonly WalletService $wallets) {}

    /** @return array<int, PromotionCode> */
    public function generateCodes(PromotionCampaign $campaign, User $actor, ?Request $request = null): array
    {
        if ($campaign->rules_locked_at || $campaign->codes()->exists()) {
            throw ValidationException::withMessages(['campaign' => 'کدهای این کمپین قبلاً تولید شده‌اند.']);
        }

        return DB::transaction(function () use ($campaign, $actor, $request): array {
            $campaign = PromotionCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $codes = [];

            for ($i = 0; $i < $campaign->code_count; $i++) {
                do {
                    $plain = $this->newCode();
                    try {
                        $code = $campaign->codes()->create([
                            'code_digest' => $this->digest($plain),
                            'encrypted_code' => $plain,
                            'status' => 'available',
                        ]);
                        $created = true;
                    } catch (QueryException $exception) {
                        if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                            throw $exception;
                        }
                        $created = false;
                    }
                } while (! $created);

                $codes[] = $code;
            }

            $campaign->forceFill(['rules_locked_at' => now(), 'updated_by_id' => $actor->id])->save();
            $this->event('codes_generated', $campaign, null, $actor, null, $request, ['count' => count($codes), 'liability' => $campaign->maximum_liability]);

            return $codes;
        });
    }

    public function redeemCredit(string $plainCode, Customer $redeemer, Project $project, ?Request $request = null): PromotionRedemption
    {
        return DB::transaction(function () use ($plainCode, $redeemer, $project, $request): PromotionRedemption {
            $code = $this->lockedCode($plainCode);
            $campaign = $code->campaign;
            $wallet = $this->wallets->walletFor($project->owner);

            $this->releaseIfExpired($code);
            $this->assertRedeemable($code, $campaign, $project->owner, $wallet);

            if ($campaign->type !== PromotionCampaign::TYPE_CREDIT || $campaign->requiresPayment()) {
                throw ValidationException::withMessages(['code' => 'این کد باید هنگام افزایش موجودی استفاده شود.']);
            }

            $redemption = PromotionRedemption::create([
                'promotion_campaign_id' => $campaign->id,
                'promotion_code_id' => $code->id,
                'wallet_id' => $wallet->id,
                'customer_id' => $project->owner->id,
                'redeemed_by_customer_id' => $redeemer->id,
                'project_id' => $project->id,
                'base_amount' => 0,
                'benefit_amount' => $campaign->credit_amount,
                'redeemed_at' => now(),
            ]);

            $transaction = $this->wallets->credit(
                $project->owner,
                $campaign->credit_amount,
                'اعتبار هدیه: '.$campaign->name,
                reference: $redemption,
                metadata: ['category' => 'promotion_credit', 'campaign_id' => $campaign->id, 'promotion_code_id' => $code->id, 'project_id' => $project->id],
            );

            $redemption->forceFill(['wallet_transaction_id' => $transaction->id])->save();
            $code->forceFill(['status' => 'redeemed', 'redeemed_at' => now(), 'reserved_payment_id' => null, 'reserved_wallet_id' => null, 'reserved_until' => null])->save();
            $this->event('credit_redeemed', $campaign, $code, null, $redeemer, $request, ['wallet_id' => $wallet->id, 'benefit_amount' => $campaign->credit_amount]);

            return $redemption;
        });
    }

    public function reserveForPayment(Payment $payment, string $plainCode, Customer $redeemer, Project $project, ?Request $request = null): PromotionCode
    {
        return DB::transaction(function () use ($payment, $plainCode, $redeemer, $project, $request): PromotionCode {
            $code = $this->lockedCode($plainCode);
            $campaign = $code->campaign;
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($payment->wallet_id);

            $this->releaseIfExpired($code);
            $this->assertRedeemable($code, $campaign, $project->owner, $wallet);

            if (! $campaign->requiresPayment()) {
                throw ValidationException::withMessages(['promotion_code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
            }

            if ($campaign->type === PromotionCampaign::TYPE_PERCENTAGE) {
                if ($payment->amount < (int) $campaign->minimum_top_up) {
                    throw ValidationException::withMessages(['promotion_code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
                }

                $bonus = min(intdiv($payment->amount * $campaign->percentage, 100), $campaign->maximum_bonus);
            } else {
                $bonus = (int) $campaign->credit_amount;
            }
            $until = now()->addMinutes(config('promotions.reservation_minutes'));
            $code->forceFill(['status' => 'reserved', 'reserved_payment_id' => $payment->id, 'reserved_wallet_id' => $wallet->id, 'reserved_until' => $until])->save();
            $payment->forceFill([
                'promotion_code_id' => $code->id,
                'promotion_redeemer_customer_id' => $redeemer->id,
                'promotion_project_id' => $project->id,
                'promotion_bonus_amount' => $bonus,
            ])->save();
            $this->event('code_reserved', $campaign, $code, null, $redeemer, $request, ['payment_id' => $payment->id, 'wallet_id' => $wallet->id, 'reserved_until' => $until->toIso8601String(), 'quoted_bonus' => $bonus]);

            return $code;
        });
    }

    public function releasePaymentReservation(Payment $payment, string $reason): void
    {
        if (! $payment->promotion_code_id) {
            return;
        }

        DB::transaction(function () use ($payment, $reason): void {
            $code = PromotionCode::query()->lockForUpdate()->find($payment->promotion_code_id);
            if (! $code || $code->status !== 'reserved' || (int) $code->reserved_payment_id !== (int) $payment->id) {
                return;
            }
            $code->forceFill(['status' => 'available', 'reserved_payment_id' => null, 'reserved_wallet_id' => null, 'reserved_until' => null])->save();
            $this->event('reservation_released', $code->campaign, $code, metadata: ['payment_id' => $payment->id, 'reason' => $reason]);
        });
    }

    public function completePaymentBonus(Payment $payment): ?PromotionRedemption
    {
        if (! $payment->promotion_code_id || $payment->promotion_bonus_amount <= 0) {
            return null;
        }

        $code = PromotionCode::query()->with('campaign')->lockForUpdate()->find($payment->promotion_code_id);
        if (! $code) {
            return null;
        }

        $wallet = Wallet::query()->lockForUpdate()->findOrFail($payment->wallet_id);
        $canClaim = ($code->status === 'reserved' && (int) $code->reserved_payment_id === (int) $payment->id)
            || $code->status === 'available';

        if (! $canClaim || PromotionRedemption::query()->where('promotion_campaign_id', $code->promotion_campaign_id)->where('wallet_id', $wallet->id)->exists()) {
            PromotionException::query()->firstOrCreate(['payment_id' => $payment->id], [
                'promotion_campaign_id' => $code->promotion_campaign_id,
                'promotion_code_id' => $code->id,
                'wallet_id' => $wallet->id,
                'expected_bonus' => $payment->promotion_bonus_amount,
            ]);
            $this->event('late_bonus_exception', $code->campaign, $code, metadata: ['payment_id' => $payment->id, 'expected_bonus' => $payment->promotion_bonus_amount]);

            return null;
        }

        $redemption = PromotionRedemption::create([
            'promotion_campaign_id' => $code->promotion_campaign_id,
            'promotion_code_id' => $code->id,
            'wallet_id' => $wallet->id,
            'customer_id' => $payment->customer_id,
            'redeemed_by_customer_id' => $payment->promotion_redeemer_customer_id ?: $payment->customer_id,
            'project_id' => $payment->promotion_project_id,
            'payment_id' => $payment->id,
            'base_amount' => $payment->amount,
            'benefit_amount' => $payment->promotion_bonus_amount,
            'redeemed_at' => now(),
        ]);
        $transaction = $this->wallets->credit(
            $payment->customer,
            $payment->promotion_bonus_amount,
            ($code->campaign->type === PromotionCampaign::TYPE_CREDIT ? 'اعتبار هدیه پس از پرداخت: ' : 'پاداش افزایش موجودی: ').$code->campaign->name,
            reference: $redemption,
            metadata: ['category' => 'promotion_top_up_bonus', 'campaign_id' => $code->promotion_campaign_id, 'promotion_code_id' => $code->id, 'payment_id' => $payment->id, 'project_id' => $payment->promotion_project_id],
        );
        $redemption->forceFill(['wallet_transaction_id' => $transaction->id])->save();
        $code->forceFill(['status' => 'redeemed', 'redeemed_at' => now(), 'reserved_payment_id' => null, 'reserved_wallet_id' => null, 'reserved_until' => null])->save();
        $this->event('bonus_redeemed', $code->campaign, $code, metadata: ['payment_id' => $payment->id, 'benefit_amount' => $payment->promotion_bonus_amount]);

        return $redemption;
    }

    public function releaseExpiredReservations(): int
    {
        PromotionCampaign::query()->where('status', 'scheduled')->where('starts_at', '<=', now())->where('expires_at', '>', now())->update(['status' => 'active', 'updated_at' => now()]);
        PromotionCampaign::query()->whereIn('status', ['scheduled', 'active', 'paused'])->where('expires_at', '<=', now())->update(['status' => 'ended', 'updated_at' => now()]);
        $ids = PromotionCode::query()->where('status', 'reserved')->where('reserved_until', '<=', now())->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count): void {
                $code = PromotionCode::query()->lockForUpdate()->find($id);
                if ($code && $code->status === 'reserved' && $code->reserved_until?->isPast()) {
                    $paymentId = $code->reserved_payment_id;
                    $code->forceFill(['status' => 'available', 'reserved_payment_id' => null, 'reserved_wallet_id' => null, 'reserved_until' => null])->save();
                    $this->event('reservation_expired', $code->campaign, $code, metadata: ['payment_id' => $paymentId]);
                    $count++;
                }
            });
        }

        return $count;
    }

    public function digest(string $plainCode): string
    {
        $pepper = (string) config('promotions.code_pepper');
        if ($pepper === '') {
            throw new RuntimeException('PROMOTION_CODE_PEPPER must be configured.');
        }

        return hash_hmac('sha256', $this->normalize($plainCode), $pepper);
    }

    public function normalize(string $plainCode): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($plainCode))) ?: '';
        $prefix = strtoupper((string) config('promotions.code_prefix', 'AVT'));

        return str_starts_with($normalized, $prefix) ? substr($normalized, strlen($prefix)) : $normalized;
    }

    public function resolveAvailableCode(string $plainCode): PromotionCode
    {
        $code = PromotionCode::query()
            ->with('campaign')
            ->where('code_digest', $this->digest($plainCode))
            ->first();

        if (! $code || $code->status !== 'available' || ! $code->campaign?->isAvailable()) {
            throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
        }

        return $code;
    }

    public function event(string $action, ?PromotionCampaign $campaign = null, ?PromotionCode $code = null, ?User $user = null, ?Customer $customer = null, ?Request $request = null, array $metadata = []): PromotionEvent
    {
        return PromotionEvent::create([
            'promotion_campaign_id' => $campaign?->id,
            'promotion_code_id' => $code?->id,
            'user_id' => $user?->id,
            'customer_id' => $customer?->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function lockedCode(string $plainCode): PromotionCode
    {
        $code = PromotionCode::query()->with('campaign')->where('code_digest', $this->digest($plainCode))->lockForUpdate()->first();
        if (! $code) {
            throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
        }

        return $code;
    }

    private function releaseIfExpired(PromotionCode $code): void
    {
        if ($code->status === 'reserved' && $code->reserved_until?->isPast()) {
            $code->forceFill(['status' => 'available', 'reserved_payment_id' => null, 'reserved_wallet_id' => null, 'reserved_until' => null])->save();
        }
    }

    private function assertRedeemable(PromotionCode $code, PromotionCampaign $campaign, Customer $owner, Wallet $wallet): void
    {
        if ($code->status !== 'available' || ! $campaign->isAvailable() || $wallet->is_locked) {
            throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
        }
        if (PromotionRedemption::query()->where('promotion_campaign_id', $campaign->id)->where('wallet_id', $wallet->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
        }
        if ($campaign->audience === PromotionCampaign::AUDIENCE_ALLOWLIST && ! $campaign->allowedCustomers()->whereKey($owner->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
        }
        if ($campaign->audience === PromotionCampaign::AUDIENCE_NEW) {
            $funded = Payment::query()->where('customer_id', $owner->id)->where('status', Payment::STATUS_SUCCESSFUL)->exists();
            $redeemed = PromotionRedemption::query()->where('customer_id', $owner->id)->exists();
            if ($funded || $redeemed) {
                throw ValidationException::withMessages(['code' => 'کد هدیه معتبر یا قابل استفاده نیست.']);
            }
        }
    }

    private function newCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $buffer = 0;
        $bits = 0;
        $body = '';
        foreach (str_split(random_bytes(10)) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $body .= $alphabet[($buffer >> $bits) & 31];
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        return config('promotions.code_prefix', 'AVT').'-'.implode('-', str_split($body, 4));
    }
}
