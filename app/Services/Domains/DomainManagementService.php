<?php

namespace App\Services\Domains;

use App\Models\DomainOperation;
use App\Models\ManagedDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainManagementService
{
    public function __construct(private readonly DomainProvider $provider) {}

    public function nameservers(ManagedDomain $domain, array $nameservers): void
    {
        $operation = DB::transaction(function () use ($domain, $nameservers): DomainOperation {
            $domain = ManagedDomain::lockForUpdate()->findOrFail($domain->id);
            if (DomainOperation::where('managed_domain_id', $domain->id)->whereIn('status', ['pending', 'submitting', 'uncertain'])->exists()) {
                throw ValidationException::withMessages(['nameservers' => 'تغییر قبلی هنوز در حال انجام است. لطفاً نتیجه را بررسی کنید.']);
            }

            return DomainOperation::create(['managed_domain_id' => $domain->id, 'kind' => 'nameservers', 'payload' => $nameservers]);
        });
        try {
            $result = $this->provider->request('PUT', '/domain/'.$domain->name.'/dns', ['dnsList' => $nameservers]);
            if (! isset($result['trackId']) || ! preg_match('/^[0-9]+$/', (string) $result['trackId'])) {
                throw new \RuntimeException('No tracking id.');
            }
            $operation->update(['track_id' => (string) $result['trackId'], 'status' => 'pending']);
        } catch (Throwable) {
            $operation->update(['status' => 'uncertain']);
        }
    }

    public function reconcile(DomainOperation $operation): void
    {
        if (! $operation->track_id || $operation->kind !== 'nameservers' || $operation->status !== 'pending') {
            return;
        }
        try {
            $result = $this->provider->inquiry($operation->track_id);
            if (($result['item'] ?? null) !== $operation->domain->name || ($result['job'] ?? null) !== 'UpdateDnsJob') {
                $operation->update(['status' => 'uncertain']);

                return;
            }
            $status = $result['status'] ?? '';
            if (in_array($status, ['done', 'success', 'successful', 'completed'], true)) {
                DB::transaction(function () use ($operation): void {
                    $operation = DomainOperation::lockForUpdate()->findOrFail($operation->id);
                    if ($operation->status !== 'pending') {
                        return;
                    }
                    $operation->domain->update(['nameservers' => $operation->payload]);
                    $operation->update(['status' => 'done', 'checked_at' => now()]);
                });
            } elseif ($status === 'failed') {
                $operation->update(['status' => 'failed', 'checked_at' => now()]);
            }
        } catch (Throwable) { /* A tracked operation is safe to inquire again. */
        }
    }

    public function sync(ManagedDomain $domain): void
    {
        $info = $this->provider->info($domain->name);
        // An unexpected provider identity needs operator review, never reassignment.
        if ((string) $info['id'] !== (string) $domain->provider_id) {
            return;
        }
        $expiry = Carbon::parse($info['expire_at']);
        $domain->update(['expires_at' => $expiry, 'status' => $expiry->isPast() ? 'expired' : 'active', 'synced_at' => now()]);
    }
}
