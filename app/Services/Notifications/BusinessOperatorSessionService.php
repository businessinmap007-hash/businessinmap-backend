<?php

namespace App\Services\Notifications;

use App\Models\BusinessOperatorSession;
use Illuminate\Support\Carbon;

/**
 * The write side of operator presence. An operator (a business account, or a
 * delegated staff user acting for it) marks itself "online" while watching a
 * service screen; RealtimeNotificationService gates realtime delivery on such a
 * live session (BusinessOperatorSession::online). Sessions self-expire via
 * `expected_until`, and a heartbeat keeps them alive.
 */
class BusinessOperatorSessionService
{
    /** Default life of a session before it must be renewed by a heartbeat. */
    public const DEFAULT_MINUTES = 5;
    public const MAX_MINUTES = 120;

    /**
     * Open (or refresh) an online session for (business, user, service screen).
     * One live row per that triple — re-starting reuses it rather than piling up.
     */
    public function start(
        int $businessId,
        int $userId,
        ?string $serviceType = null,
        ?string $screen = null,
        ?int $expectedMinutes = null,
    ): BusinessOperatorSession {
        $minutes = $this->clampMinutes($expectedMinutes);
        $now = Carbon::now();

        /** @var BusinessOperatorSession $session */
        $session = BusinessOperatorSession::query()->updateOrCreate(
            [
                'business_id' => $businessId,
                'user_id' => $userId,
                'service_type' => $serviceType,
            ],
            [
                'screen' => $screen,
                'status' => BusinessOperatorSession::STATUS_ONLINE,
                'started_at' => $now,
                'expected_until' => $now->copy()->addMinutes($minutes),
                'last_activity_at' => $now,
                'ended_at' => null,
            ]
        );

        return $session;
    }

    /**
     * Keep a live session alive: bump last activity and push the expiry out.
     * Returns null when there is no matching live session to renew.
     */
    public function heartbeat(
        int $businessId,
        int $userId,
        ?string $serviceType = null,
        ?int $expectedMinutes = null,
    ): ?BusinessOperatorSession {
        $session = $this->liveSession($businessId, $userId, $serviceType);
        if (! $session) {
            return null;
        }

        $now = Carbon::now();
        $session->forceFill([
            'last_activity_at' => $now,
            'expected_until' => $now->copy()->addMinutes($this->clampMinutes($expectedMinutes)),
            'status' => BusinessOperatorSession::STATUS_ONLINE,
            'ended_at' => null,
        ])->save();

        return $session;
    }

    /** Close the operator's live session(s) for a service screen. */
    public function end(int $businessId, int $userId, ?string $serviceType = null): int
    {
        return BusinessOperatorSession::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->when(
                func_num_args() >= 3,
                fn ($q) => $q->where('service_type', $serviceType)
            )
            ->whereNull('ended_at')
            ->update([
                'status' => BusinessOperatorSession::STATUS_OFFLINE,
                'ended_at' => Carbon::now(),
            ]);
    }

    /** The caller's currently-live session for a screen, if any. */
    public function current(int $businessId, int $userId, ?string $serviceType = null): ?BusinessOperatorSession
    {
        return $this->liveSession($businessId, $userId, $serviceType);
    }

    private function liveSession(int $businessId, int $userId, ?string $serviceType): ?BusinessOperatorSession
    {
        return BusinessOperatorSession::query()
            ->online()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('service_type', $serviceType)
            ->latest('id')
            ->first();
    }

    private function clampMinutes(?int $minutes): int
    {
        $minutes = (int) ($minutes ?: self::DEFAULT_MINUTES);

        return max(1, min(self::MAX_MINUTES, $minutes));
    }
}
