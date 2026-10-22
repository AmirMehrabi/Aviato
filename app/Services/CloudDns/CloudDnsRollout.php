<?php

namespace App\Services\CloudDns;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloudDnsRollout
{
    public const KEY = 'cloud-dns.rollout';

    public const MODES = ['disabled', 'selected', 'all'];

    /** Read authorization directly so grants/revocations take effect on the next request. */
    public function configuration(): array
    {
        return $this->normalize(AppSetting::where('key', self::KEY)->first()?->value);
    }

    public function allows(Customer $customer, Project $project): bool
    {
        if (! config('cloud-dns.enabled')) {
            return false;
        }
        $rollout = $this->configuration();

        return $rollout['mode'] === 'all' || ($rollout['mode'] === 'selected'
            && in_array((int) $customer->id, $rollout['customer_ids'], true)
            && in_array((int) $project->owner_customer_id, $rollout['customer_ids'], true));
    }

    public function update(string $action, ?string $mode, ?int $customerId): void
    {
        DB::transaction(function () use ($action, $mode, $customerId): void {
            AppSetting::firstOrCreate(['key' => self::KEY], [
                'value' => ['mode' => 'selected', 'customer_ids' => []], 'type' => 'json', 'group' => 'cloud-dns',
            ]);
            $setting = AppSetting::where('key', self::KEY)->lockForUpdate()->firstOrFail();
            $rollout = $this->normalize($setting->value);
            if ($action === 'mode') {
                $rollout['mode'] = $mode;
            } elseif ($action === 'add') {
                if (count($rollout['customer_ids']) >= 5000 && ! in_array($customerId, $rollout['customer_ids'], true)) {
                    throw ValidationException::withMessages(['identifier' => 'فهرست انتشار به سقف مجاز رسیده است.']);
                }
                $rollout['customer_ids'] = array_values(array_unique([...$rollout['customer_ids'], $customerId]));
            } elseif ($action === 'remove') {
                $rollout['customer_ids'] = array_values(array_diff($rollout['customer_ids'], [$customerId]));
            }
            $setting->update(['value' => $this->normalize($rollout)]);
            DB::afterCommit(fn () => Cache::forget('settings.'.self::KEY));
        });
    }

    private function normalize(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $ids = is_array($value['customer_ids'] ?? null) ? $value['customer_ids'] : [];

        return [
            'mode' => in_array($value['mode'] ?? null, self::MODES, true) ? $value['mode'] : 'selected',
            'customer_ids' => array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)))),
        ];
    }
}
