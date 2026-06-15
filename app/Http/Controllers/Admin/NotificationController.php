<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function markAllRead(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        if (! $admin) {
            abort(403);
        }

        $count = $admin->unreadNotifications()->count();

        if ($count > 0) {
            $admin->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json([
            'unread_count' => 0,
            'marked_count' => $count,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $admin = $request->user('admin');

        if (! $admin) {
            abort(403);
        }

        $adminNotification = $admin->notifications()->whereKey($notification)->firstOrFail();

        if (! $adminNotification->read_at) {
            $adminNotification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'unread_count' => $admin->unreadNotifications()->count(),
            'notification_id' => $adminNotification->id,
            'read_at' => optional($adminNotification->read_at)->toISOString(),
        ]);
    }
}
