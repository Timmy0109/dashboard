<?php

namespace App\Http\Controllers\Api;

use App\Events\NotificationRead;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/notifications?filter=unread&cursor=… */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->query('filter') === 'unread') {
            $query->whereNull('read_at');
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $paginated = $query->cursorPaginate($perPage);

        $unreadCount = Notification::where('user_id', $user->id)->whereNull('read_at')->count();

        return response()->json([
            'data' => $paginated->items(),
            'next_cursor' => optional($paginated->nextCursor())->encode(),
            'unread_count' => $unreadCount,
        ]);
    }

    /** POST /api/notifications/{notification}/read */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            // 404 not 403: don't leak existence of other users' notifications.
            abort(404);
        }

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
            SafeBroadcast::dispatch(new NotificationRead($notification->user_id, [$notification->id]));
        }

        return response()->json(['ok' => true]);
    }

    /** POST /api/notifications/mark-all-read */
    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $updated = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            SafeBroadcast::dispatch(new NotificationRead($userId, null));
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }
}
