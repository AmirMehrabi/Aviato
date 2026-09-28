<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceWalletAlertPreferences
{
    public function update(Request $request, Project $project): void
    {
        $data = $request->validate([
            'threshold_mode' => ['required', Rule::in(['default', 'custom'])],
            'thresholds' => ['required_if:threshold_mode,custom', 'nullable', 'string', 'max:50'],
            'recipient_mode' => ['required', Rule::in(['default', 'custom'])],
            'recipient_ids' => ['array', 'max:100'],
            'recipient_ids.*' => ['integer', 'distinct', Rule::exists('project_members', 'customer_id')->where('project_id', $project->id)],
        ]);

        if ($data['recipient_mode'] === 'custom' && isset($data['recipient_ids'])) {
            $activeCount = $project->members()->whereIn('customer_id', $data['recipient_ids'])
                ->whereHas('customer', fn ($query) => $query->where('status', Customer::STATUS_ACTIVE))->count();
            if ($activeCount !== count($data['recipient_ids'])) {
                throw ValidationException::withMessages(['recipient_ids' => 'فقط اعضای فعال فضای کاری می‌توانند اعلان دریافت کنند.']);
            }
        }

        $thresholds = null;
        if ($data['threshold_mode'] === 'custom') {
            $values = array_map('trim', explode(',', (string) $data['thresholds']));
            if (count($values) > 10 || count($values) !== count(array_unique(array_map('intval', $values))) || collect($values)->contains(fn (string $value): bool => ! ctype_digit($value) || (int) $value < 1 || (int) $value > 100)) {
                throw ValidationException::withMessages(['thresholds' => 'درصدها باید عددهای یکتای بین ۱ تا ۱۰۰ باشند و با ویرگول جدا شوند.']);
            }
            $thresholds = array_map('intval', $values);
        }

        $project->update([
            'wallet_alert_thresholds' => $thresholds,
            'wallet_alert_recipient_ids' => $data['recipient_mode'] === 'default' ? null : array_map('intval', $data['recipient_ids'] ?? []),
        ]);
    }
}
