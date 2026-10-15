<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageReactionUpdated;
use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleHasPermission;
use App\Models\TktGroup;
use App\Models\User;
use App\Models\UserMenuAcces;
use App\Notifications\ChatMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $this->ensureChatPermission($request->user());
        $userId = $request->user()->id;

        $query = User::whereKeyNot($userId);

        if ($request->boolean('conversations_only')) {
            $query->whereHas('sentMessages', fn ($q) => $q->where('recipient_id', $userId)->whereNull('group_id'))
                ->orWhereHas('receivedMessages', fn ($q) => $q->where('sender_id', $userId)->whereNull('group_id'));
        }

        $users = $query->orderByRaw('COALESCE(fullname, name, email)')
            ->get(['id', 'name', 'fullname', 'email', 'image', 'role']);

        $partnerIds = ChatMessage::whereNull('group_id')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->get(['sender_id', 'recipient_id'])
            ->map(fn ($msg) => $msg->sender_id === $userId ? $msg->recipient_id : $msg->sender_id)
            ->unique()
            ->values()
            ->all();

        $partnerIdMap = array_flip($partnerIds);

        $users->each(function ($user) use ($partnerIdMap) {
            $user->has_conversation = isset($partnerIdMap[$user->id]);
        });

        return response()->json([
            'data' => $users,
        ]);
    }

    public function teams(Request $request): JsonResponse
    {
        $this->ensureChatPermission($request->user());

        return response()->json(['data' => TktGroup::query()
            ->whereHas('members', fn ($members) => $members->whereKey($request->user()->id))
            ->with([
                'members:id,name,fullname,email,image',
                'approvers:id,name,fullname,email,image',
            ])
            ->orderBy('name')
            ->get(['id', 'name'])]);
    }

    public function messages(Request $request): JsonResponse
    {
        $this->ensureChatPermission($request->user());
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:group_id'], 'group_id' => ['nullable', 'integer', 'exists:tkt_groups,id', 'required_without:user_id']]);
        $limit = min((int) $request->input('limit', 30), 100);
        $beforeId = $request->input('before_id');
        $me = $request->user()->id;

        $query = ChatMessage::with([
            'sender:id,name,fullname,email,image',
            'reactions.user:id,name,fullname,email',
        ]);

        if (! empty($data['group_id'])) {
            $this->ensureTeamMember($me, $data['group_id']);
            $query->where('group_id', $data['group_id']);
        } else {
            $other = $data['user_id'];
            $query->where(function ($q) use ($me, $other) {
                $q->where(fn ($sub) => $sub->where('sender_id', $me)->where('recipient_id', $other))
                    ->orWhere(fn ($sub) => $sub->where('sender_id', $other)->where('recipient_id', $me));
            });
        }

        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }

        // Fetch most recent messages up to $limit + 1 to detect if older messages exist
        $rawMessages = $query->latest('id')->limit($limit + 1)->get();
        $hasMore = $rawMessages->count() > $limit;
        $messages = $rawMessages->slice(0, $limit)->reverse()->values();

        $formatted = $messages->map(function (ChatMessage $msg) {
            $msg->reactions_summary = $this->formatReactionsSummary($msg);

            return $msg;
        });

        return response()->json([
            'data' => $formatted,
            'has_more' => $hasMore,
            'oldest_id' => $messages->first()?->id,
            'latest_id' => $messages->last()?->id,
        ]);
    }

    public function toggleReaction(Request $request, int $id): JsonResponse
    {
        $this->ensureChatPermission($request->user());
        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        $message = ChatMessage::with(['reactions.user:id,name,fullname,email'])->findOrFail($id);
        $user = $request->user();

        // Check permission to react to this message
        if ($message->group_id) {
            $this->ensureTeamMember($user->id, $message->group_id);
        } else {
            abort_unless(
                (int) $message->sender_id === (int) $user->id || (int) $message->recipient_id === (int) $user->id,
                403,
                'You are not part of this conversation.'
            );
        }

        $existing = ChatMessageReaction::where('chat_message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChatMessageReaction::create([
                'chat_message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $data['emoji'],
            ]);
        }

        $message->load('reactions.user:id,name,fullname,email');
        $summary = $this->formatReactionsSummary($message);

        event(new ChatMessageReactionUpdated($message, $summary));

        return response()->json([
            'message_id' => $message->id,
            'reactions' => $summary,
        ]);
    }

    /**
     * @return array<int, array{emoji: string, count: int, user_ids: array<int, int>, users: array<int, array{id: int, name: string}>}>
     */
    private function formatReactionsSummary(ChatMessage $message): array
    {
        return $message->reactions
            ->groupBy('emoji')
            ->map(function ($group, $emoji) {
                return [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'user_ids' => $group->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
                    'users' => $group->map(function ($reaction) {
                        $u = $reaction->user;

                        return [
                            'id' => (int) $reaction->user_id,
                            'name' => $u?->fullname ?: ($u?->name ?: $u?->email ?: 'User'),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function send(Request $request): JsonResponse
    {
        $this->ensureChatPermission($request->user());
        $data = $request->validate([
            'recipient_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:group_id'],
            'group_id' => ['nullable', 'integer', 'exists:tkt_groups,id', 'required_without:recipient_id'],
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachments = [];
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('chat/attachments/'.now()->format('mdY'), 'public');
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        if (! empty($data['group_id'])) {
            $this->ensureTeamMember($request->user()->id, $data['group_id']);
        }
        $message = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $data['recipient_id'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'body' => trim($data['body'] ?? ''),
            'attachments' => $attachments ?: null,
        ]);
        $message->load('sender:id,name,fullname,email,image');
        event(new ChatMessageSent($message));
        if ($message->group_id) {
            TktGroup::with('members')->findOrFail($message->group_id)->members
                ->where('id', '!=', $message->sender_id)
                ->each(fn (User $member) => $member->notify(new ChatMessageNotification($message)));
        } else {
            User::findOrFail($message->recipient_id)->notify(new ChatMessageNotification($message));
        }

        return response()->json(['data' => $message], 201);
    }

    private function ensureTeamMember(int $userId, int $groupId): void
    {
        abort_unless(TktGroup::whereKey($groupId)->whereHas('members', fn ($members) => $members->whereKey($userId))->exists(), 403, 'You are not a member of this team.');
    }

    private function ensureChatPermission(User $user): void
    {
        abort_unless($this->canUserAccessChat($user), 403, 'You do not have permission to access Chat.');
    }

    private function canUserAccessChat(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $menu = Menu::where('url', '/chat')->orWhere('code', 'chat')->first();
        if (! $menu) {
            return true;
        }

        $access = UserMenuAcces::where('user_id', $user->id)
            ->where('menu_id', $menu->id)
            ->first();

        if ($access !== null) {
            return (bool) $access->can_view;
        }

        $role = Role::where('name', $user->role)->first();
        if ($role) {
            return RoleHasPermission::where('role_id', $role->role_id)
                ->where('menu_id', $menu->id)
                ->whereHas('permission', function ($q) {
                    $q->whereRaw('LOWER(name) = ?', ['view']);
                })
                ->exists();
        }

        return true;
    }
}
