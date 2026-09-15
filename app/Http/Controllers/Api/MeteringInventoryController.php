<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeteringInventoryAssignment;
use App\Models\MeteringInventoryChange;
use App\Services\MeteringInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeteringInventoryController extends Controller
{
    public function changes(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'integer', 'min:0'], 'limit' => ['nullable', 'integer', 'between:1,500']]);
        $cursor = (int) ($data['cursor'] ?? 0);
        $limit = (int) ($data['limit'] ?? 500);
        $rows = MeteringInventoryChange::query()->where('stream_id', '>', $cursor)->orderBy('stream_id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $next = (int) ($rows->last()?->stream_id ?? $cursor);

        return response()->json([
            'schema_version' => 1,
            'items' => $rows->map(fn ($row) => ['event_id' => (int) $row->stream_id, 'action' => $row->action] + $row->payload)->values(),
            'next_cursor' => (string) $next,
            'has_more' => $hasMore,
            'generated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public function snapshot(MeteringInventoryService $inventory): JsonResponse
    {
        $ceiling = (int) (MeteringInventoryChange::query()->max('stream_id') ?? 0);
        $items = MeteringInventoryAssignment::query()->where('active', true)->orderBy('id')->get()
            ->map(fn ($assignment) => ['action' => 'upsert'] + $inventory->payload($assignment));

        return response()->json(['schema_version' => 1, 'items' => $items, 'next_cursor' => (string) $ceiling, 'has_more' => false, 'generated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')]);
    }
}
