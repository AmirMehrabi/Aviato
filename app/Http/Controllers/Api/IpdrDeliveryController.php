<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunNetworkIntegration;
use App\Models\NetworkIntegrationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpdrDeliveryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['batch_id' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'], 'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['required', 'array']]);
        $hash = hash('sha256', json_encode($data['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (! hash_equals($hash, $data['batch_id'])) {
            return response()->json(['error' => ['code' => 'BATCH_CONFLICT']], 422);
        }
        $run = NetworkIntegrationRun::query()->firstOrCreate(['batch_id' => $hash], ['kind' => 'delivery', 'payload' => ['items' => $data['items']], 'status' => 'queued']);
        if ($run->wasRecentlyCreated) {
            try {
                RunNetworkIntegration::dispatch($run->id)->afterCommit();
            } catch (\Throwable $e) {
                report($e);
            } // persisted receipt is drained by the scheduler
        }

        return response()->json(['accepted' => true, 'batch_id' => $hash, 'status' => $run->fresh()->status], 202);
    }
}
