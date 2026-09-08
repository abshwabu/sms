<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasApiResponse;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationCenterController extends Controller
{
    use HasApiResponse;

    /**
     * Get in-app notification center feed and unread count for current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = InAppNotification::where('user_id', $user->id)
            ->latest('id')
            ->take(50)
            ->get();

        $unreadCount = InAppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->respondWithSuccess([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ], 'Notifications retrieved.');
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(InAppNotification $notification, Request $request): JsonResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized access to this notification.');
        }

        $notification->markAsRead();

        return $this->respondWithSuccess($notification, 'Notification marked as read.');
    }

    /**
     * Mark all notifications for the current user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = InAppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->respondWithSuccess([
            'marked_count' => $updated,
        ], 'All notifications marked as read.');
    }

    /**
     * Delete an in-app notification.
     */
    public function destroy(InAppNotification $notification, Request $request): JsonResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized access to this notification.');
        }

        $notification->delete();

        return $this->respondWithSuccess(null, 'Notification dismissed.');
    }
}
