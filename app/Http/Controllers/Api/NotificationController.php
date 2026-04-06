<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = auth()->user()->notifications()->paginate(20);
        return $this->success($notifications);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->find($id);

        if (!$notification) {
            return $this->notFound();
        }

        $notification->markAsRead();
        return $this->success(null, 'Notification marked as read');
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->find($id);

        if (!$notification) {
            return $this->notFound();
        }

        $notification->delete();
        return $this->success(null, 'Notification deleted');
    }

    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return $this->success(null, 'All notifications marked as read');
    }

    public function unreadCount(): JsonResponse
    {
        $count = auth()->user()->unreadNotifications()->count();
        return $this->success(['count' => $count]);
    }

    public function destroyAll(): JsonResponse
    {
        auth()->user()->notifications()->delete();
        return $this->success(null, 'All notifications deleted');
    }
}
