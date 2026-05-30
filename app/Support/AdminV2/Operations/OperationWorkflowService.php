<?php

namespace App\Support\AdminV2\Operations;

use App\Models\Booking;
use InvalidArgumentException;

final class OperationWorkflowService
{
    public function __construct(
        protected BookingOperationInspector $bookingInspector,
    ) {
    }

    public function inspect(object $operation): OperationWorkflowResult
    {
        if ($operation instanceof Booking) {
            return $this->inspectBooking($operation);
        }

        throw new InvalidArgumentException(
            'Unsupported operation type: ' . get_class($operation)
        );
    }

    public function inspectBooking(Booking $booking): OperationWorkflowResult
    {
        return $this->bookingInspector->inspect($booking);
    }

    public function context(object $operation): OperationContext
    {
        if ($operation instanceof Booking) {
            return OperationContext::fromBooking($operation);
        }

        throw new InvalidArgumentException(
            'Unsupported operation context type: ' . get_class($operation)
        );
    }

    public function reference(object $operation): OperationReference
    {
        if ($operation instanceof Booking) {
            return OperationReference::booking((int) $operation->id);
        }

        throw new InvalidArgumentException(
            'Unsupported operation reference type: ' . get_class($operation)
        );
    }

    public function can(object $operation, string $action): bool
    {
        return $this->inspect($operation)->can($action);
    }

    public function cannot(object $operation, string $action): bool
    {
        return ! $this->can($operation, $action);
    }

    public function nextAction(object $operation): ?string
    {
        return $this->inspect($operation)->nextAction();
    }

    public function availableActions(object $operation): array
    {
        return $this->inspect($operation)->availableActions();
    }

    public function blockedReasons(object $operation): array
    {
        return $this->inspect($operation)->blockedReasons();
    }

    public function toArray(object $operation): array
    {
        return $this->inspect($operation)->toArray();
    }
}