<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('tenant');

        return ApiResponse::success(
            $user->notifications()->paginate(20),
            'Notifications retrieved.'
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'count' => $request->user('tenant')->unreadNotifications()->count(),
        ], 'Unread count retrieved.');
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user('tenant')->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::message('Notification marked as read.');
    }

    public function markUnread(Request $request, string $id): JsonResponse
    {
        $notification = $request->user('tenant')->notifications()->findOrFail($id);
        $notification->update(['read_at' => null]);

        return ApiResponse::message('Notification marked as unread');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user('tenant')->unreadNotifications->markAsRead();

        return ApiResponse::message('All notifications marked read.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user('tenant')->notifications()->findOrFail($id);
        $notification->delete($id);

        return ApiResponse::message('Notification deleted');
    }
}