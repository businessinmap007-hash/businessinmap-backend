<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusinessClientRelationship;
use Illuminate\Support\Facades\DB;

class BusinessClientRelationshipService
{
    public function recordBookingCreated(Booking $booking): ?BusinessClientRelationship
    {
        return $this->record($booking, 'created');
    }

    public function recordBookingCompleted(Booking $booking): ?BusinessClientRelationship
    {
        return $this->record($booking, 'completed');
    }

    public function recordBookingCancelled(Booking $booking, string $reason = 'cancelled'): ?BusinessClientRelationship
    {
        return $this->record($booking, $reason === 'rejected' ? 'rejected' : 'cancelled');
    }

    public function recordBookingDisputed(Booking $booking): ?BusinessClientRelationship
    {
        return $this->record($booking, 'disputed');
    }

    public function refreshForPair(int $businessId, int $clientId): ?BusinessClientRelationship
    {
        if ($businessId <= 0 || $clientId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($businessId, $clientId) {
            return BusinessClientRelationship::query()->updateOrCreate(
                ['business_id' => $businessId, 'client_id' => $clientId],
                $this->calculateStats($businessId, $clientId)
            );
        });
    }

    protected function record(Booking $booking, string $event): ?BusinessClientRelationship
    {
        $relationship = $this->refreshForPair((int) $booking->business_id, (int) $booking->user_id);

        if (! $relationship) {
            return null;
        }

        $meta = is_array($relationship->meta ?? null) ? $relationship->meta : [];
        $meta['last_event'] = $event;
        $meta['last_event_booking_id'] = (int) $booking->id;
        $meta['last_event_at'] = now()->toDateTimeString();
        $meta['service_fee_note'] = 'Trust exemptions do not waive platform service fees.';
        $relationship->update(['meta' => $meta]);

        return $relationship;
    }

    protected function calculateStats(int $businessId, int $clientId): array
    {
        $base = Booking::withTrashed()
            ->where('business_id', $businessId)
            ->where('user_id', $clientId);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', Booking::STATUS_COMPLETED)->count();
        $cancelled = (clone $base)->where('status', Booking::STATUS_CANCELLED)->count();
        $rejected = (clone $base)->where('status', Booking::STATUS_REJECTED)->count();
        $disputed = (clone $base)->whereHas('disputes')->count();

        return [
            'total_operations' => (int) $total,
            'completed_operations' => (int) $completed,
            'cancelled_operations' => (int) $cancelled,
            'rejected_operations' => (int) $rejected,
            'disputed_operations' => (int) $disputed,
            'total_value' => round((float) (clone $base)->sum('price'), 2),
            'completed_value' => round((float) (clone $base)->where('status', Booking::STATUS_COMPLETED)->sum('price'), 2),
            'client_trust_score_for_business' => $this->score($total, $completed, $cancelled, $rejected, $disputed),
            'business_trust_score_for_client' => $this->score($total, $completed, $cancelled, $rejected, $disputed),
            'last_operation_at' => (clone $base)->latest('id')->value('created_at'),
            'last_completed_at' => (clone $base)->where('status', Booking::STATUS_COMPLETED)->latest('id')->value('updated_at'),
            'last_problem_at' => (clone $base)->where(function ($q) {
                $q->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
                    ->orWhereHas('disputes');
            })->latest('id')->value('updated_at'),
        ];
    }

    protected function score(int $total, int $completed, int $cancelled, int $rejected, int $disputed): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        $score = 50.0 + min($completed * 2.5, 35.0);
        $score -= min($cancelled * 4.0, 20.0);
        $score -= min($rejected * 3.0, 15.0);
        $score -= min($disputed * 12.0, 40.0);

        if ($completed >= 10 && $disputed === 0) {
            $score += 10.0;
        }

        return round(max(min($score, 100.0), 0.0), 2);
    }
}
