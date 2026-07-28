<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\BusinessOperatorSession;

final class RealtimeNotificationService
{
    public function hasActiveSession(int $userId, ?string $serviceType = null): bool
    {
        return BusinessOperatorSession::query()
            ->online()
            ->where(function ($q) use ($userId) {
                $q->where('business_id', $userId)->orWhere('user_id', $userId);
            })
            ->when($serviceType, fn ($q) => $q->where('service_type', $serviceType))
            ->exists();
    }

    public function sendToUser(int $userId, AppNotification $notification, array $payload = []): array
    {
        if (! $this->hasActiveSession($userId, $payload['service_type'] ?? null)) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'no_active_operator_session'];
        }

        // Transport = the pull feed: the notification is already committed to
        // app_notifications, which the operator's client streams via
        // GET /operator/realtime/poll. So an online operator IS reached in real
        // time — we report `sent` so the dispatcher's fallback_to_firebase
        // short-circuit suppresses a redundant push while they're watching. The
        // payload is kept for a future broadcast driver (websocket) to emit.
        return [
            'sent' => true,
            'reason' => 'delivered_via_poll',
            'payload' => [
                'event' => 'notification.created',
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'title' => $notification->displayTitle(),
                'body' => $notification->displayBody(),
                'action_type' => $notification->action_type,
                'action_url' => $notification->action_url,
                'sound_key' => $payload['sound_key'] ?? null,
                'meta' => $notification->meta ?? [],
            ],
        ];
    }

    /**
     * The operator's live delta feed: notifications newer than a cursor, oldest
     * first. Short-poll — the client calls this on an interval and advances the
     * returned cursor. Scoped to the recipient (user_id); a realtime-enabled rule
     * put the row here in the first place.
     *
     * @return array{events: array<int,array<string,mixed>>, cursor: int}
     */
    public function feedSince(int $userId, int $afterId, int $limit = 50): array
    {
        $rows = AppNotification::query()
            ->where('user_id', $userId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $events = $rows->map(fn (AppNotification $n) => [
            'event' => 'notification.created',
            'notification_id' => (int) $n->id,
            'type' => (string) $n->type,
            'priority' => (string) $n->priority,
            'title' => $n->displayTitle(),
            'body' => $n->displayBody(),
            'action_type' => $n->action_type,
            'action_url' => $n->action_url,
            'meta' => $n->meta ?? [],
            'created_at' => optional($n->created_at)->toIso8601String(),
        ])->all();

        return [
            'events' => $events,
            'cursor' => $rows->isNotEmpty() ? (int) $rows->last()->id : $afterId,
        ];
    }
}
