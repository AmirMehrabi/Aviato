<?php

namespace App\Services;

use App\Models\VirtualMachine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VmActivityTimeline
{
    public function forVm(VirtualMachine $vm): LengthAwarePaginator
    {
        // Operations own their full lifecycle; omit their duplicate activity receipts.
        // Keep legacy/unrelated activities, including receipts whose operation no longer exists.
        $activities = $vm->activities()->selectRaw("id, created_at, 'activity' as kind")
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('vm_operations as operation')
                    ->whereColumn('operation.virtual_machine_id', 'vm_activities.virtual_machine_id')
                    ->where(function ($query): void {
                        $query->whereColumn('operation.id', 'vm_activities.metadata->operation_id')
                            ->orWhere(function ($query): void {
                                $query->where('operation.action', 'upgrade')
                                    ->where('vm_activities.event', 'upgrade')
                                    ->whereColumn('operation.details->upgrade_order_id', 'vm_activities.metadata->order_id');
                            });
                    });
            });
        $operations = $vm->operations()->selectRaw("id, created_at, 'operation' as kind");
        $page = DB::query()->fromSub($activities->toBase()->unionAll($operations->toBase()), 'timeline')
            ->orderByDesc('created_at')->orderByDesc('id')->orderBy('kind')->paginate(20)->withQueryString();
        $rows = $page->getCollection();
        $activityModels = $vm->activities()->whereIn('id', $rows->where('kind', 'activity')->pluck('id'))->get()->keyBy('id');
        $operationModels = $vm->operations()->whereIn('id', $rows->where('kind', 'operation')->pluck('id'))->get()->keyBy('id');

        return $page->through(function ($row) use ($activityModels, $operationModels): object {
            $operation = $row->kind === 'operation' ? $operationModels[$row->id] : null;
            $activity = $row->kind === 'activity' ? $activityModels[$row->id] : null;

            return (object) [
                'title' => $operation ? $operation->label() : $activity->title,
                'outcome' => $operation ? $operation->status : $activity->outcome,
                'detail' => $operation ? $operation->progressLabel() : $activity->detail,
                'created_at' => ($operation ?? $activity)->created_at,
                'operation' => $operation,
            ];
        });
    }
}
