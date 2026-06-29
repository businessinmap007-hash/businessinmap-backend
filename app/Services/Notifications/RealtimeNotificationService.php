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

        return [
            'sent' => false,
            'skipped' => true,
            'reason' => 'realtime_transport_not_configured',
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
}
