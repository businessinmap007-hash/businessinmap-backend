<?php

namespace App\Notifications;

use App\Models\ServiceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceEventDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ServiceEvent $event,
        protected string $title,
        protected string $body,
        protected ?string $url = null,
        protected array $extra = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'service_event_id' => (int) $this->event->id,
            'event_key' => (string) $this->event->event_key,
            'service_key' => (string) $this->event->service_key,
            'action_key' => (string) $this->event->action_key,

            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,

            'subject_type' => $this->event->subject_type,
            'subject_id' => $this->event->subject_id,

            'actor_id' => $this->event->actor_id,
            'business_id' => $this->event->business_id,
            'client_id' => $this->event->client_id,

            'payload' => $this->event->payload ?? [],
            'extra' => $this->extra,

            'occurred_at' => optional($this->event->occurred_at)->toDateTimeString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}