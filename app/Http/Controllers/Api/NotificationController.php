<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\ChatMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $latest = $user->notifications()->latest()->limit(20)->get();
        $latestChat = $user->notifications()
            ->where('type', ChatMessageNotification::class)
            ->latest()
            ->limit(20)
            ->get();
        $notifications = $latest->merge($latestChat)->unique('id')->sortByDesc('created_at')->values();

        $unreadChat = $user->unreadNotifications()
            ->where('type', ChatMessageNotification::class)
            ->get();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
            'unread_chat_count' => $unreadChat->count(),
            'chat_unread' => [
                'direct' => $unreadChat->whereNull('data.group_id')->countBy('data.sender_id'),
                'team' => $unreadChat->whereNotNull('data.group_id')->countBy('data.group_id'),
            ],
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function readChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'required_without:group_id'],
            'group_id' => ['nullable', 'integer', 'required_without:user_id'],
        ]);

        $notifications = $request->user()->unreadNotifications()
            ->where('type', ChatMessageNotification::class)
            ->get()
            ->filter(function ($notification) use ($data) {
                $payload = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
                if (! empty($data['group_id'])) {
                    return (int) ($payload['group_id'] ?? 0) === (int) $data['group_id'];
                }

                return empty($payload['group_id'])
                    && (int) ($payload['sender_id'] ?? 0) === (int) $data['user_id'];
            });

        $notifications->each(function ($notification) {
            $notification->markAsRead();
        });

        return response()->json([
            'message' => 'Chat notifications marked as read.',
            'marked' => $notifications->count(),
        ]);
    }
}
