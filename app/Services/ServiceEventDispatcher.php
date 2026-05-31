<?php

namespace App\Services;

use App\Models\ServiceEvent;
use App\Support\AdminV2\ServiceEvents\ServiceEventKeys;
use App\Services\ServiceEventNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
class ServiceEventDispatcher
{
    public function dispatch(
        string $eventKey,
        ?Model $subject = null,
        ?int $actorId = null,
        array $payload = [],
        ?int $businessId = null,
        ?int $clientId = null,
        string $status = ServiceEvent::STATUS_RECORDED,
        CarbonInterface|string|null $occurredAt = null
    ): ServiceEvent {
        $eventKey = trim($eventKey);

        if (! ServiceEventKeys::isAllowed($eventKey)) {
            throw new InvalidArgumentException("Service event key is not registered: {$eventKey}");
        }

        [$serviceKey, $actionKey] = ServiceEventKeys::split($eventKey);

        $businessId = $businessId ?: $this->resolveIntFromSubject($subject, [
            'business_id',
            'seller_id',
            'provider_id',
        ]);

        $clientId = $clientId ?: $this->resolveIntFromSubject($subject, [
            'user_id',
            'client_id',
            'customer_id',
        ]);

        $occurredAt = $occurredAt
            ? \Carbon\Carbon::parse($occurredAt)
            : now();

        $event = DB::transaction(function () use (
        $eventKey,
        $serviceKey,
        $actionKey,
        $subject,
        $actorId,
        $businessId,
        $clientId,
        $status,
        $payload,
        $occurredAt
    ) {
        return ServiceEvent::create([
            'event_key' => $eventKey,
            'service_key' => $serviceKey,
            'action_key' => $actionKey,

            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject && $subject->exists ? $subject->getKey() : null,

            'actor_id' => $actorId,
            'business_id' => $businessId,
            'client_id' => $clientId,

            'status' => $status,
            'payload' => $this->cleanPayload($payload),
            'occurred_at' => $occurredAt,
        ]);
    });

    try {
        app(ServiceEventNotificationService::class)->handle($event);
    } catch (\Throwable $e) {
        report($e);
    }

    return $event;
    }

    public function bookingRequested(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_REQUESTED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingAccepted(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_ACCEPTED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingRejected(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_REJECTED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingCancelled(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_CANCELLED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingStarted(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_STARTED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingCompleted(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_COMPLETED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingClientConfirmed(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_CLIENT_CONFIRMED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingBusinessConfirmed(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_BUSINESS_CONFIRMED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingDepositFrozen(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_DEPOSIT_FROZEN,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingDepositReleased(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_DEPOSIT_RELEASED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingDepositRefunded(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_DEPOSIT_REFUNDED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingDisputeOpened(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_DISPUTE_OPENED,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    protected function resolveIntFromSubject(?Model $subject, array $keys): ?int
    {
        if (! $subject) {
            return null;
        }

        foreach ($keys as $key) {
            $value = $subject->getAttribute($key);

            if ($value !== null && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    protected function cleanPayload(array $payload): array
    {
        unset(
            $payload['password'],
            $payload['password_confirmation'],
            $payload['remember_token'],
            $payload['_token']
        );

        return $payload;
    }
    public function bookingReminder24h(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_REMINDER_24H,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }

    public function bookingReminder1h(Model $booking, ?int $actorId = null, array $payload = []): ServiceEvent
    {
        return $this->dispatch(
            eventKey: ServiceEventKeys::BOOKING_REMINDER_1H,
            subject: $booking,
            actorId: $actorId,
            payload: $payload
        );
    }
}