<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusinessTrustedClient;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Services\Guarantees\GuaranteeCoverageService;

class BookingProtectionDecisionEngine
{
    public const METHOD_TRUSTED_CLIENT = 'trusted_client';
    public const METHOD_GUARANTEE = 'guarantee';
    public const METHOD_DEPOSIT = 'deposit';
    public const METHOD_PENDING = 'pending_business_approval';
    public const METHOD_REJECTED = 'rejected';

    public function __construct(
        protected GuaranteeCoverageService $coverageService
    ) {
    }

    public function decideForBooking(Booking $booking, array $depositPolicy = []): array
    {
        return $this->decide(
            clientId: (int) $booking->user_id,
            businessId: (int) $booking->business_id,
            amount: (float) ($booking->price ?? 0),
            depositPolicy: $depositPolicy,
            booking: $booking
        );
    }

    public function decide(int $clientId, int $businessId, float $amount, array $depositPolicy = [], ?Booking $booking = null): array
    {
        $amount = round(max($amount, 0), 2);

        $base = [
            'required' => true,
            'method' => null,
            'status' => null,
            'deposit_required' => false,
            'guarantee_required' => false,
            'platform_fees_required' => true,
            'service_fees_note' => 'Platform service fees still apply even when deposit or guarantee is skipped.',
            'amount' => $amount,
        ];

        $allow = $this->allowlist($businessId, $clientId);

        if ($allow && $allow->isBlocked()) {
            return array_merge($base, [
                'method' => self::METHOD_REJECTED,
                'status' => 'blocked_by_business',
                'reason' => 'client_blocked_by_business',
                'allowlist' => $this->allowlistPayload($allow),
            ]);
        }

        if ($allow && (bool) $allow->is_active && (bool) $allow->skip_deposit && (bool) $allow->skip_guarantee) {
            $limitCheck = $this->allowlistLimitsOk($allow, $amount, $booking);

            if ($limitCheck['ok']) {
                return array_merge($base, [
                    'required' => false,
                    'method' => self::METHOD_TRUSTED_CLIENT,
                    'status' => 'approved',
                    'deposit_required' => false,
                    'guarantee_required' => false,
                    'reason' => 'trusted_client_allowlist',
                    'allowlist' => $this->allowlistPayload($allow),
                ]);
            }
        }

        $client = $clientId > 0 ? User::query()->find($clientId) : null;
        $guarantee = $client ? $this->coverageService->payload($client, GuaranteeLevel::TARGET_CLIENT) : null;
        $available = (float) data_get($guarantee, 'available_coverage', 0);
        $hasGuarantee = (bool) data_get($guarantee, 'enabled', false);

        if ($hasGuarantee && $available >= $amount) {
            return array_merge($base, [
                'method' => self::METHOD_GUARANTEE,
                'status' => 'covered',
                'deposit_required' => false,
                'guarantee_required' => true,
                'reserved_coverage' => $amount,
                'available_coverage' => $available,
                'available_after_reservation' => round($available - $amount, 2),
                'client_guarantee' => $guarantee,
            ]);
        }

        if ($hasGuarantee && $available > 0) {
            return array_merge($base, [
                'method' => self::METHOD_PENDING,
                'status' => 'guarantee_below_required',
                'deposit_required' => false,
                'guarantee_required' => true,
                'reserved_coverage' => $available,
                'missing_coverage' => round($amount - $available, 2),
                'available_coverage' => $available,
                'client_guarantee' => $guarantee,
                'business_can_accept_lower' => true,
            ]);
        }

        if ((bool) data_get($depositPolicy, 'required', false)) {
            return array_merge($base, [
                'method' => self::METHOD_DEPOSIT,
                'status' => 'deposit_required',
                'deposit_required' => true,
                'guarantee_required' => false,
                'deposit_policy' => $depositPolicy,
                'client_guarantee' => $guarantee,
            ]);
        }

        return array_merge($base, [
            'method' => self::METHOD_REJECTED,
            'status' => 'no_protection_available',
            'reason' => 'no_guarantee_no_deposit_no_trust',
            'client_guarantee' => $guarantee,
        ]);
    }

    protected function allowlist(int $businessId, int $clientId): ?BusinessTrustedClient
    {
        if ($businessId <= 0 || $clientId <= 0) {
            return null;
        }

        return BusinessTrustedClient::query()
            ->where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('is_active', 1)
            ->first();
    }

    protected function allowlistPayload(BusinessTrustedClient $allow): array
    {
        return [
            'id' => (int) $allow->id,
            'list_type' => (string) $allow->list_type,
            'skip_deposit' => (bool) $allow->skip_deposit,
            'skip_guarantee' => (bool) $allow->skip_guarantee,
            'max_active_bookings' => $allow->max_active_bookings ? (int) $allow->max_active_bookings : null,
            'max_booking_value' => $allow->max_booking_value !== null ? (float) $allow->max_booking_value : null,
        ];
    }

    protected function allowlistLimitsOk(BusinessTrustedClient $allow, float $amount, ?Booking $booking = null): array
    {
        if ($allow->max_booking_value !== null && $amount > (float) $allow->max_booking_value) {
            return ['ok' => false, 'reason' => 'max_booking_value_exceeded'];
        }

        if ($allow->max_active_bookings !== null) {
            $active = Booking::query()
                ->where('business_id', (int) $allow->business_id)
                ->where('user_id', (int) $allow->client_id)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
                ->when($booking, fn ($q) => $q->where('id', '!=', (int) $booking->id))
                ->count();

            if ($active >= (int) $allow->max_active_bookings) {
                return ['ok' => false, 'reason' => 'max_active_bookings_exceeded'];
            }
        }

        return ['ok' => true, 'reason' => null];
    }
}
