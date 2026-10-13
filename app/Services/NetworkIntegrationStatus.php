<?php

namespace App\Services;

use App\Models\MeteringInventoryAssignment;
use App\Models\MeteringInventoryChange;
use App\Models\NetworkIntegrationRun;
use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;

class NetworkIntegrationStatus
{
    public function overview(): array
    {
        $runs = NetworkIntegrationRun::latest('id')->limit(8)->get();
        $latestRun = $runs->first();
        $attentionRuns = NetworkIntegrationRun::where(fn ($q) => $q->where('status', 'failed')->orWhere('rejected_count', '>', 0))->latest('id')->limit(10)->get();
        $runs = $runs->concat($attentionRuns)->unique('id');
        $healthRun = NetworkIntegrationRun::whereNotNull('health')->latest('id')->first();
        $health = $healthRun?->health ?? [];
        $machines = VirtualMachine::notDeleted()->latest('id')->paginate(15);
        $assignments = MeteringInventoryAssignment::whereIn('virtual_machine_id', $machines->modelKeys())->where('active', true)->get()->keyBy('virtual_machine_id');
        $events = MeteringInventoryChange::whereIn('assignment_id', $assignments->pluck('assignment_id'))->selectRaw('assignment_id, MAX(stream_id) as latest_event')->groupBy('assignment_id')->get()->keyBy('assignment_id');
        $usage = NetworkUsageBucket::where('source', config('services.ipdr.source', 'ipdr'))->whereIn('virtual_machine_id', $machines->modelKeys())->selectRaw('virtual_machine_id, MAX(interval_end) as latest_interval')->groupBy('virtual_machine_id')->get()->keyBy('virtual_machine_id');
        $states = [];
        $freshHealth = $healthRun && $healthRun->updated_at->gt(now()->subMinutes(5)) && empty($health['unavailable']);
        foreach ($machines as $vm) {
            $assignment = $assignments->get($vm->id);
            $event = $assignment ? $events->get($assignment->assignment_id)?->latest_event : null;
            $registered = $freshHealth && $event && (int) ($health['inventory']['cursor'] ?? 0) >= $event;
            $states[$vm->id] = ['label' => ! $assignment ? 'در انتظار تخصیص IP' : ($registered ? 'ثبت در IPDR تأیید شد' : ($freshHealth ? 'در انتظار ثبت در IPDR' : 'وضعیت ثبت نیازمند بررسی')), 'latest' => $usage->get($vm->id)?->latest_interval];
        }

        return ['runs' => $runs, 'latestRun' => $latestRun, 'health' => $health, 'healthAt' => $healthRun?->updated_at, 'machines' => $machines, 'machineStates' => $states,
            'activeRun' => NetworkIntegrationRun::whereIn('status', ['queued', 'running'])->where('created_at', '>', now()->subMinutes(15))->latest('id')->first(),
            'latestUsage' => NetworkUsageBucket::where('source', config('services.ipdr.source', 'ipdr'))->where('status', 'final')->max('interval_end'),
            'exceptionCount' => NetworkUsageBucket::where('source', config('services.ipdr.source', 'ipdr'))->where(function ($q) {
                $q->where('processing_status', 'quarantined')->orWhereIn('completeness', ['partial', 'missing']);
            })->count(),
            'deliveryEnabled' => filled(config('services.ipdr.integration_token'))];
    }
}
