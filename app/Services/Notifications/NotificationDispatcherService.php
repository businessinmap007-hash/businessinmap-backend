<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\NotificationChannelRule;
use App\Models\NotificationDeliveryLog;

final class NotificationDispatcherService
{
    /**
     * $data['skip_realtime'] (bool) forces this one call past the
     * realtime/operator-session check even when the event key's rule has
     * it enabled. Needed because several event keys (menu_order_completed,
     * menu_order_cancelled, ...) are dispatched to BOTH a business AND a
     * customer/driver depending on the call site, and RealtimeNotificationService::
     * hasActiveSession() matches the recipient id against ANY online
     * business_operator_sessions row (business_id OR user_id) with no
     * business scoping -- a customer who is unrelatedly also a business
     * owner with their OWN dashboard open would otherwise have this order's
     * push silently swapped for a "realtime" delivery that only the
     * business/AdminV2 panel ever polls, never bim_app's customer side.
     * Callers that only ever notify a customer or a driver (who never poll
     * that feed either) should always pass this; callers that notify the
     * business/operator should not.
     */
    public function dispatch(string $eventKey, int $userId, array $data = []): array
    {
        NotificationChannelRule::ensureDefault($eventKey);

        $rule = NotificationChannelRule::query()->where('event_key', $eventKey)->first();
        if (! $rule || ! $rule->is_active) {
            return ['created' => false, 'reason' => 'rule_disabled_or_missing', 'event_key' => $eventKey];
        }

        $notification = app(InAppNotificationService::class)->create([
            'user_id' => $userId,
            'actor_id' => $data['actor_id'] ?? null,
            'type' => $data['type'] ?? $rule->type,
            'priority' => $data['priority'] ?? $rule->priority,
            'title_ar' => $data['title_ar'] ?? $rule->name_ar,
            'title_en' => $data['title_en'] ?? $rule->name_en,
            'body_ar' => $data['body_ar'] ?? null,
            'body_en' => $data['body_en'] ?? null,
            'action_type' => $data['action_type'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'notifiable_type' => $data['notifiable_type'] ?? null,
            'notifiable_id' => $data['notifiable_id'] ?? null,
            'source_type' => $data['source_type'] ?? $eventKey,
            'source_id' => $data['source_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'meta' => array_merge($data['meta'] ?? [], [
                'event_key' => $eventKey,
                'sound_key' => $rule->sound_key,
                'critical' => (bool) $rule->critical,
                'in_app_visible' => (bool) $rule->in_app_enabled,
            ]),
        ]);

        if ($rule->in_app_enabled) {
            $this->log($notification, $eventKey, $userId, NotificationDeliveryLog::CHANNEL_IN_APP, NotificationDeliveryLog::STATUS_CREATED, null, ['rule_id' => $rule->id]);
        }

        $realtimeResult = null;
        $firebaseResult = null;
        $realtimeSent = false;

        if ($rule->realtime_enabled && ! ($data['skip_realtime'] ?? false)) {
            $realtimeResult = app(RealtimeNotificationService::class)->sendToUser($userId, $notification, [
                'event_key' => $eventKey,
                'service_type' => $data['service_type'] ?? null,
                'sound_key' => $rule->sound_key,
            ]);
            $realtimeSent = (bool) ($realtimeResult['sent'] ?? false);
            $this->log($notification, $eventKey, $userId, NotificationDeliveryLog::CHANNEL_REALTIME, $realtimeSent ? NotificationDeliveryLog::STATUS_SENT : NotificationDeliveryLog::STATUS_SKIPPED, $realtimeSent ? null : ($realtimeResult['reason'] ?? 'not_sent'), $realtimeResult);
        }

        $shouldFirebase = (bool) $rule->firebase_enabled;
        if ($rule->fallback_to_firebase && $rule->realtime_enabled && $realtimeSent) {
            $shouldFirebase = false;
        }

        if ($shouldFirebase) {
            $firebaseResult = app(FirebasePushService::class)->sendToUser($userId, $notification, [
                'event_key' => $eventKey,
                'sound_key' => $rule->sound_key,
                'android_channel_id' => $data['android_channel_id'] ?? 'bim_orders',
            ]);
            $status = ($firebaseResult['sent'] ?? 0) > 0 ? NotificationDeliveryLog::STATUS_SENT : (($firebaseResult['skipped'] ?? false) ? NotificationDeliveryLog::STATUS_SKIPPED : NotificationDeliveryLog::STATUS_FAILED);
            $this->log($notification, $eventKey, $userId, NotificationDeliveryLog::CHANNEL_FIREBASE, $status, $status === NotificationDeliveryLog::STATUS_SENT ? null : ($firebaseResult['reason'] ?? 'not_delivered'), $firebaseResult);
        }

        return [
            'created' => true,
            'event_key' => $eventKey,
            'notification_id' => $notification->id,
            'rule_id' => $rule->id,
            'realtime' => $realtimeResult,
            'firebase' => $firebaseResult,
        ];
    }

    private function log(AppNotification $notification, string $eventKey, int $userId, string $channel, string $status, ?string $reason = null, array $meta = []): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'status' => $status,
            'attempted_at' => now(),
            'delivered_at' => $status === NotificationDeliveryLog::STATUS_SENT ? now() : null,
            'failed_reason' => $reason,
            'provider_message_id' => $meta['provider_message_id'] ?? null,
            'meta' => $meta,
        ]);
    }
}
