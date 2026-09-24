<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\VmActivity;
use Throwable;

class VmActivityRecorder
{
    public function record(VirtualMachine $vm, string $event, string $outcome, string $title, ?string $detail = null, ?Customer $actor = null, array $metadata = []): ?VmActivity
    {
        try {
            return $vm->activities()->create([
                'actor_customer_id' => $actor?->id,
                'event' => $event,
                'outcome' => $outcome,
                'title' => $title,
                'detail' => $detail,
                'metadata' => $metadata ?: null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
