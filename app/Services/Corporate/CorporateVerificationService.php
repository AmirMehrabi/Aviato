<?php

namespace App\Services\Corporate;

use App\Jobs\DeliverCorporateNotification;
use App\Models\AppSetting;
use App\Models\BillingIdentity;
use App\Models\CorporateDelivery;
use App\Models\CorporateProfile;
use App\Models\CorporateRequest;
use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notifications\EmailQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class CorporateVerificationService
{
    public function start(Customer $customer, string $nationalId): CorporateRequest
    {
        $company = app(CompanyInquiryClient::class)->lookup($nationalId);

        return DB::transaction(function () use ($customer, $nationalId, $company) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if (CorporateRequest::where('customer_id', $customer->id)->whereIn('status', ['draft', 'pending', 'needs_correction'])->exists()) {
                throw ValidationException::withMessages(['national_id' => 'ابتدا درخواست باز قبلی را تکمیل کنید.']);
            }
            $profile = CorporateProfile::where('customer_id', $customer->id)->first();
            if (($profile && $profile->national_id !== $nationalId) || CorporateProfile::where('national_id', $nationalId)->where('customer_id', '!=', $customer->id)->exists()) {
                throw ValidationException::withMessages(['national_id' => 'تغییر مالکیت یا شناسه شرکت نیازمند بررسی پشتیبانی است.']);
            }

            return CorporateRequest::create(['customer_id' => $customer->id, 'national_id' => $nationalId, 'company' => $company,
                'registry_result' => $company, 'looked_up_at' => now(), 'address' => $company['address'] ?? null,
                'postal_code' => $company['postal_code'] ?? null, 'company_phone' => $company['phone_number'] ?? null]);
        });
    }

    public function submit(CorporateRequest $request, array $data, array $files): void
    {
        $stored = [];
        try {
            foreach ($files as $kind => $file) {
                if ($file) {
                    $path = $file->store('corporate/'.$request->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Document storage failed.');
                    }
                    $stored[$kind] = ['path' => $path, 'original_name' => $file->getClientOriginalName()];
                }
            }
            DB::transaction(function () use ($request, $data, $stored) {
                $request = CorporateRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                if (! in_array($request->status, ['draft', 'needs_correction']) || $request->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['version' => 'درخواست تغییر کرده است؛ صفحه را تازه کنید.']);
                }
                if ($request->looked_up_at->lt(now()->subDays(7))) {
                    $registry = app(CompanyInquiryClient::class)->lookup($request->national_id);
                    $request->fill(['company' => $registry, 'registry_result' => $registry, 'looked_up_at' => now()]);
                }
                $previous = $request->documents()->where('version', $request->version)->get()->keyBy('kind');
                foreach (['incorporation', 'signatories', ...($data['representative_role'] === 'delegate' ? ['authorization'] : [])] as $kind) {
                    if (! isset($stored[$kind]) && ! isset($previous[$kind])) {
                        throw ValidationException::withMessages([$kind => 'این مدرک الزامی است.']);
                    }
                }
                $request->fill(Arr::only($data, ['representative_role', 'billing_email', 'company_phone', 'address', 'postal_code', 'tax_number']));
                $request->version++;
                $request->fill(['status' => 'pending', 'submitted_at' => now(), 'consented_at' => now(), 'reason' => null, 'reviewer_id' => null, 'reviewed_at' => null, 'internal_notes' => null])->save();
                foreach (['incorporation', 'signatories', 'authorization'] as $kind) {
                    $document = $stored[$kind] ?? (isset($previous[$kind]) ? $previous[$kind]->only(['path', 'original_name']) : null);
                    if ($document) {
                        $request->documents()->create($document + ['kind' => $kind, 'version' => $request->version]);
                    }
                }
                $this->event($request, 'submitted');
            });
        } catch (\Throwable $e) {
            foreach ($stored as $file) {
                Storage::disk('local')->delete($file['path']);
            }
            throw $e;
        }
    }

    public function review(CorporateRequest $request, User $reviewer, array $data): void
    {
        DB::transaction(function () use ($request, $reviewer, $data) {
            $customer = Customer::whereKey($request->customer_id)->lockForUpdate()->firstOrFail();
            $request = CorporateRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending' || $request->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'این نسخه دیگر قابل بررسی نیست.']);
            }
            if ($data['decision'] === 'approved') {
                if ($request->looked_up_at->lt(now()->subDays(7))) {
                    $registry = app(CompanyInquiryClient::class)->lookup($request->national_id);
                    if ($registry !== $request->registry_result) {
                        throw ValidationException::withMessages(['decision' => 'اطلاعات ثبتی تغییر کرده است؛ درخواست اصلاح اطلاعات ارسال کنید.']);
                    }
                    $request->looked_up_at = now();
                }
                if (CorporateProfile::where('national_id', $request->national_id)->where('customer_id', '!=', $customer->id)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'این شرکت متعلق به حساب دیگری است.']);
                }
                $billing = app(BillingIdentityService::class);
                $billing->ensureBaseline($customer);
                // Freeze older documents before changing the account's identity.
                foreach ([Invoice::class, Payment::class, DomainOrder::class] as $model) {
                    $model::where('customer_id', $customer->id)->whereNull('billing_identity')->eachById(function ($document) {
                        if ($document->canSnapshotBillingIdentity()) {
                            $document->forceFill(['billing_identity' => $document->resolveBillingIdentity()])->saveQuietly();
                        }
                    });
                }
                $effective = Jalalian::fromCarbon(now('Asia/Tehran'))->getFirstDayOfMonth()->addMonths()->toCarbon()->startOfDay()->setTimezone(config('app.timezone'));
                $identity = ['type' => 'corporate', 'name' => $request->company['name'], 'national_id' => $request->national_id,
                    'register_number' => $request->company['register_number'] ?? null, 'address' => $request->address,
                    'postal_code' => $request->postal_code, 'email' => $request->billing_email, 'phone' => $request->company_phone,
                    'tax_number' => $request->tax_number, 'source' => 'corporate_approval', 'request_id' => $request->id];
                CorporateProfile::updateOrCreate(['customer_id' => $customer->id], ['national_id' => $request->national_id,
                    'corporate_request_id' => $request->id, 'company' => $identity, 'approved_at' => now(), 'billing_effective_at' => $effective]);
                BillingIdentity::create(['customer_id' => $customer->id, 'effective_at' => $effective, 'identity' => $identity]);
            }
            $request->fill(['status' => $data['decision'], 'reviewer_id' => $reviewer->id, 'reviewed_at' => now(),
                'reason' => $data['reason'] ?? null, 'internal_notes' => $data['internal_notes'] ?? null])->save();
            $this->event($request, $data['decision'], $reviewer);
        });
    }

    private function event(CorporateRequest $request, string $event, ?User $actor = null): void
    {
        $snapshot = $request->only(['company', 'national_id', 'address', 'postal_code', 'representative_role', 'billing_email', 'company_phone', 'tax_number', 'reason', 'internal_notes', 'consented_at']);
        $snapshot['wallet_balance'] = $request->customer->wallet?->balance;
        if ($event === 'approved') {
            $snapshot['billing_effective_at'] = CorporateProfile::where('customer_id', $request->customer_id)->first()->billing_effective_at->toISOString();
        }
        $record = $request->events()->create(['version' => $request->version, 'event' => $event, 'actor_id' => $actor?->id, 'snapshot' => $snapshot]);
        $recipients = collect([$request->customer]);
        if ($event === 'submitted') {
            $ids = AppSetting::getValue('corporate.reviewer_ids', []);
            $recipients = $recipients->merge(User::where('is_active', true)->whereIn('id', $ids)->get()->filter(fn ($user) => $user->allows('corporate.read') && $user->allows('corporate.review')));
        }
        foreach ($recipients as $recipient) {
            foreach (['email', 'sms', 'in_app'] as $channel) {
                $delivery = CorporateDelivery::create(['corporate_request_event_id' => $record->id, 'recipient_type' => $recipient instanceof Customer ? 'customer' : 'admin', 'recipient_id' => $recipient->id, 'channel' => $channel]);
                DB::afterCommit(function () use ($delivery, $channel) {
                    try {
                        $job = new DeliverCorporateNotification($delivery->id);
                        if ($channel === 'email') {
                            $job->onConnection(EmailQueue::connection())->onQueue('mail');
                        }
                        Bus::dispatch($job);
                    } catch (\Throwable) {
                        Log::warning('Corporate notification remains queued for recovery.', ['delivery_id' => $delivery->id]);
                    }
                });
            }
        }
    }
}
