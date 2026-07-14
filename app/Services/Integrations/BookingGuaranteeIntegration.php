<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use App\Models\GuaranteeLevel;
use App\Models\RatingOutcomeEvent;
use App\Services\Guarantees\GuaranteeCoverageService;
use App\Models\User;
use App\Services\Guarantees\GuaranteeScoreService;
use App\Services\Ratings\RatingService;

class BookingGuaranteeIntegration
{
    public function __construct(
        protected GuaranteeCoverageService $guaranteeCoverageService,
        protected GuaranteeScoreService $guaranteeScoreService,
        protected RatingService $ratingService
    ) {
    }

    public function payloadForBooking(Booking $booking): array
    {
        $booking->loadMissing([
            'user:id,name,type,guarantee_enabled,rating_enabled,commercial_operations_enabled',
            'business:id,name,type,guarantee_enabled,rating_enabled,commercial_operations_enabled',
        ]);

        return [
            'client' => $booking->user
                ? $this->guaranteeCoverageService->payload($booking->user, GuaranteeLevel::TARGET_CLIENT)
                : $this->emptyPayload(),

            'business' => $booking->business
                ? $this->guaranteeCoverageService->payload($booking->business, GuaranteeLevel::TARGET_BUSINESS)
                : $this->emptyPayload(),
        ];
    }

    public function clientCovered(Booking $booking, float $amount): bool
    {
        if (! $booking->user) {
            return false;
        }

        return $this->guaranteeCoverageService->covers(
            $booking->user,
            $amount,
            GuaranteeLevel::TARGET_CLIENT
        );
    }

    public function businessCovered(Booking $booking, float $amount): bool
    {
        if (! $booking->business) {
            return false;
        }

        return $this->guaranteeCoverageService->covers(
            $booking->business,
            $amount,
            GuaranteeLevel::TARGET_BUSINESS
        );
    }

    public function ensureFreeBalanceForPlatformFees(
        Booking $booking,
        float $clientFee = 0,
        float $businessFee = 0
    ): void {
        if ($clientFee > 0 && $booking->user) {
            $this->guaranteeCoverageService->ensureFreeBalanceForFee($booking->user, $clientFee);
        }

        if ($businessFee > 0 && $booking->business) {
            $this->guaranteeCoverageService->ensureFreeBalanceForFee($booking->business, $businessFee);
        }
    }

    protected function emptyPayload(): array
    {
        return [
            'enabled' => false,
            'status' => null,
            'available_coverage' => 0.0,
        ];
    }

    protected function recordForBothSides(Booking $booking, string $result): void
    {
        $booking->loadMissing([
            'user:id,name,type',
            'business:id,name,type',
        ]);

        if ($booking->user instanceof User) {
            $guarantee = $this->guaranteeCoverageService->activeGuarantee(
                $booking->user,
                GuaranteeLevel::TARGET_CLIENT
            );

            if ($guarantee) {
                $this->guaranteeScoreService->record($guarantee, $result);
            }
        }

        if ($booking->business instanceof User) {
            $guarantee = $this->guaranteeCoverageService->activeGuarantee(
                $booking->business,
                GuaranteeLevel::TARGET_BUSINESS
            );

            if ($guarantee) {
                $this->guaranteeScoreService->record($guarantee, $result);
            }
        }
    }
    public function recordCompleted(Booking $booking): void
    {
        $this->recordOnce($booking, 'completed');
    }

    public function recordCancelled(Booking $booking, bool $late = false): void
    {
        $this->recordOnce($booking, $late ? 'late_cancelled' : 'cancelled');
    }

    public function recordDisputeOpened(Booking $booking): void
    {
        $this->recordOnce($booking, 'dispute_opened');
    }

    protected function recordOnce(Booking $booking, string $result): void
    {
        $booking->refresh();

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

        $key = 'guarantee_' . $result . '_recorded_at';

        if (! empty($meta['_guarantee_stats'][$key])) {
            return;
        }

        $this->recordForBothSides($booking, $result);

        // Universal operation rating — for BOTH parties, guarantee or not. Its own
        // ledger keeps it idempotent independent of this meta guard.
        $this->recordOperationRating($booking, $result);

        $meta['_guarantee_stats'][$key] = now()->toDateTimeString();

        $booking->meta = $meta;
        $booking->save();
    }

    /** Map a guarantee-score result to a rating outcome and record it for both parties. */
    protected function recordOperationRating(Booking $booking, string $result): void
    {
        $outcome = match ($result) {
            'completed' => RatingOutcomeEvent::OUTCOME_SUCCESS,
            'cancelled', 'late_cancelled' => RatingOutcomeEvent::OUTCOME_CANCELLED,
            'dispute_opened' => RatingOutcomeEvent::OUTCOME_DISPUTED,
            default => null,
        };

        if ($outcome === null) {
            return;
        }

        $this->ratingService->recordForBothParties(
            businessUserId: (int) $booking->business_id,
            clientUserId: (int) $booking->user_id,
            outcome: $outcome,
            operationType: RatingOutcomeEvent::OP_BOOKING,
            operationId: (int) $booking->id,
        );
    }

    public function recordDisputeLostForClient(Booking $booking): void
    {
        $this->recordOnceForSide($booking, 'client', 'dispute_lost');
    }

    public function recordDisputeLostForBusiness(Booking $booking): void
    {
        $this->recordOnceForSide($booking, 'business', 'dispute_lost');
    }

    protected function recordOnceForSide(Booking $booking, string $side, string $result): void
    {
        $booking->refresh();

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

        $key = 'guarantee_' . $side . '_' . $result . '_recorded_at';

        if (! empty($meta['_guarantee_stats'][$key])) {
            return;
        }

        $booking->loadMissing([
            'user:id,name,type',
            'business:id,name,type',
        ]);

        if ($side === 'client' && $booking->user instanceof User) {
            $guarantee = $this->guaranteeCoverageService->activeGuarantee(
                $booking->user,
                GuaranteeLevel::TARGET_CLIENT
            );

            if ($guarantee) {
                $this->guaranteeScoreService->record($guarantee, $result);
            }
        }

        if ($side === 'business' && $booking->business instanceof User) {
            $guarantee = $this->guaranteeCoverageService->activeGuarantee(
                $booking->business,
                GuaranteeLevel::TARGET_BUSINESS
            );

            if ($guarantee) {
                $this->guaranteeScoreService->record($guarantee, $result);
            }
        }

        $meta['_guarantee_stats'][$key] = now()->toDateTimeString();

        $booking->meta = $meta;
        $booking->save();
    }
}