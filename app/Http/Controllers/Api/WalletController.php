<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\WalletResource;
use App\Http\Resources\Api\WalletTransactionResource;
use App\Models\Project;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly WalletService $wallets,
    ) {}

    public function show(Request $request, Project $project): WalletResource|JsonResponse
    {
        if (! $this->canReadWallet($request, $project)) {
            return $this->error('You do not have billing access to this project.', 'project_forbidden', 403);
        }

        return WalletResource::make($this->wallets->walletFor($project->owner));
    }

    public function transactions(Request $request, Project $project): AnonymousResourceCollection|JsonResponse
    {
        if (! $this->canReadWallet($request, $project)) {
            return $this->error('You do not have billing access to this project.', 'project_forbidden', 403);
        }

        $validator = Validator::make($request->query(), [
            'type' => ['nullable', 'in:all,credit,debit,charge,refund,adjustment'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'One or more query parameters are invalid.',
                    'fields' => $validator->errors(),
                ],
                'meta' => ['request_id' => $request->attributes->get('api_request_id')],
            ], 422);
        }

        $transactions = $project->owner->walletTransactions()
            ->where(function ($query) use ($project): void {
                $query->where('metadata->project_id', $project->id)
                    ->orWhereNull('metadata->project_id');
            })
            ->when(($request->string('type')->toString() ?: 'all') !== 'all', fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->paginate((int) ($request->input('per_page') ?: 25))
            ->withQueryString();

        return WalletTransactionResource::collection($transactions);
    }

    private function canReadWallet(Request $request, Project $project): bool
    {
        $customer = $request->user('sanctum');
        $membership = $this->projects->membership($project, $customer);

        return $membership !== null && $membership->canViewBilling();
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
            'meta' => ['request_id' => request()->attributes->get('api_request_id')],
        ], $status);
    }
}
