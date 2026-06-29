<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public AppNotification $notification)
    {
        $this->notification->loadMissing(['actor:id,name,logo']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.' . (int) $this->notification->user_id . '.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => (int) $this->notification->id,
            'user_id' => (int) $this->notification->user_id,
            'actor' => $this->notification->actor ? [
                'id' => (int) $this->notification->actor->id,
                'name' => $this->notification->actor->name,
                'logo' => $this->notification->actor->logo ?? null,
            ] : null,
            'type' => $this->notification->type,
            'channel' => $this->notification->channel,
            'priority' => $this->notification->priority,
            'title_ar' => $this->notification->title_ar,
            'title_en' => $this->notification->title_en,
            'body_ar' => $this->notification->body_ar,
            'body_en' => $this->notification->body_en,
            'action_type' => $this->notification->action_type,
            'action_url' => $this->notification->action_url,
            'notifiable_type' => $this->notification->notifiable_type,
            'notifiable_id' => $this->notification->notifiable_id ? (int) $this->notification->notifiable_id : null,
            'source_type' => $this->notification->source_type,
            'source_id' => $this->notification->source_id ? (int) $this->notification->source_id : null,
            'status' => $this->notification->status,
            'delivered_at' => optional($this->notification->delivered_at)->toISOString(),
            'expires_at' => optional($this->notification->expires_at)->toISOString(),
            'created_at' => optional($this->notification->created_at)->toISOString(),
            'meta' => $this->notification->meta,
        ];
    }
}
