<?php

namespace App\Http\Controllers\Admin;

use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInboxService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin, 403);

        return response()->json($this->notifications->feed(
            $admin,
            route('admin.tickets.index', [], false),
        ));
    }

    public function dismiss(Request $request, string $notification): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin, 403);
        $this->notifications->dismiss($admin, $notification);

        return $this->index($request);
    }

    public function clearRead(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin, 403);
        $this->notifications->clearRead($admin);

        return $this->index($request);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin, 403);

        return response()->json($this->notifications->markAllRead($admin));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin, 403);

        return response()->json($this->notifications->markRead($admin, $notification));
    }
}
