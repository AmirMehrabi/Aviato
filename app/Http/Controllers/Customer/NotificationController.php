<?php

namespace App\Http\Controllers\Customer;

use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInboxService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        abort_unless($customer, 403);

        return response()->json($this->notifications->feed(
            $customer,
            route('customer.tickets.index', [], false),
        ));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        abort_unless($customer, 403);

        return response()->json($this->notifications->markAllRead($customer));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $customer = $request->user('customer');

        abort_unless($customer, 403);

        return response()->json($this->notifications->markRead($customer, $notification));
    }
}
