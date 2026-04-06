<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\NotificationFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web routes for the user's Laravel Notifications feed.
 *
 * - GET  /notifications              → full list
 * - POST /notifications/read-all     → mark every notification as read
 * - POST /notifications/{id}/read    → mark a single notification as read
 *                                       and 302 to the linked entity (dropdown click-through)
 * - DELETE /notifications/{id}       → soft-dismiss
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationFormatter $formatter)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        // Format the items so the view doesn't need to know about
        // entity_type → icon mapping; that knowledge lives in the formatter.
        $items = $this->formatter->formatMany($notifications);

        return view('dashboard.notifications.index', [
            'items'        => $items,
            'pagination'   => $notifications,
            'unreadCount'  => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            abort(404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        // Redirect to the linked entity if we can resolve one; otherwise
        // bounce back to the notifications index.
        $formatted = $this->formatter->format($notification);
        $target = $formatted['url'] ?? route('notifications.index');

        return redirect($target);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('notifications.all_marked_read'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->delete();

        return back();
    }
}
