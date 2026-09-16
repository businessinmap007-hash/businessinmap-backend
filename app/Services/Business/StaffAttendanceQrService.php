<?php

namespace App\Services\Business;

use App\Models\StaffAttendanceQrToken;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The GPS + rotating-QR half of attendance verification (opt in via
 * User::attendance_verification_enabled). A physical display at the
 * business shows currentFor()'s token; scanning it calls redeem(), which
 * marks it used immediately so a photo of the screen can't be scanned again
 * later - expiresAfterMinutes bounds exposure even if nobody ever scans it.
 */
class StaffAttendanceQrService
{
    /** How long an unscanned code stays valid before it must rotate anyway. */
    public const TTL_MINUTES = 10;

    /** How close (in meters) a checking-in phone's GPS must be to the business. */
    public const RADIUS_METERS = 200;

    /** The business's current live code, generating one if none is active. */
    public function currentFor(int $businessId): StaffAttendanceQrToken
    {
        $active = StaffAttendanceQrToken::query()
            ->where('business_id', $businessId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($active) {
            return $active;
        }

        return StaffAttendanceQrToken::create([
            'business_id' => $businessId,
            'token' => Str::random(32),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    /**
     * Marks a scanned code used, once. Throws if it belongs to another
     * business, was already scanned, or has expired - the same message for
     * all three so a stale code gives away nothing about why it failed.
     */
    public function redeem(int $businessId, string $token, int $usedByUserId): void
    {
        $row = StaffAttendanceQrToken::query()
            ->where('business_id', $businessId)
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'qr_token' => [__('كود الحضور غير صالح أو انتهت صلاحيته، امسح الكود المعروض حاليًا.')],
            ]);
        }

        $row->update(['used_at' => now(), 'used_by_user_id' => $usedByUserId]);
    }

    /**
     * No-ops when the business hasn't set its own location yet (still
     * possible today per the location module - see the User model) rather
     * than blocking every check-in a business never configured for this.
     */
    public function assertWithinRange(User $business, float $lat, float $lng): void
    {
        if ($business->latitude === null || $business->longitude === null) {
            return;
        }

        $meters = getDistanceBetweenPointsNew(
            (float) $business->latitude,
            (float) $business->longitude,
            $lat,
            $lng,
        ) * 1000;

        if ($meters > self::RADIUS_METERS) {
            throw ValidationException::withMessages([
                'location' => [__('موقعك بعيد عن مقر النشاط، لازم تكون في المكان لتسجيل الحضور.')],
            ]);
        }
    }
}
