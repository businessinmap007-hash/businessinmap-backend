<?php

namespace App\Support;

use App\Models\BusinessStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * «الطبيب الموظف» — the physicians who work for a hospital / medical centre / clinic account: staff members the owner
 * marked `is_physician` (accepted and active). A physician acting for the business is recorded on the visits,
 * prescriptions and investigation orders he handles, and sees only his own appointments.
 */
final class Physicians
{
    /** The caller's user id when he is a delegated physician of the acting business — otherwise null. */
    public static function actingId(Request $request): ?int
    {
        if (BusinessContext::isOwner($request) || ! $request->user()) {
            return null;
        }

        $businessId = BusinessContext::id($request);
        $userId = (int) $request->user()->id;

        return self::isPhysicianOf($userId, $businessId) ? $userId : null;
    }

    public static function isPhysicianOf(int $userId, int $businessId): bool
    {
        return BusinessStaff::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('is_physician', true)
            ->where('is_active', true)
            ->where('status', BusinessStaff::STATUS_ACCEPTED)
            ->exists();
    }

    /**
     * Everyone a patient may choose at this business.
     *
     * @return Collection<int,array{id:int,name:string,title:?string,logo:?string}>
     */
    public static function of(int $businessId): Collection
    {
        return BusinessStaff::query()
            ->where('business_id', $businessId)
            ->where('is_physician', true)
            ->where('is_active', true)
            ->where('status', BusinessStaff::STATUS_ACCEPTED)
            ->with('user:id,name,logo')
            ->get()
            ->filter(fn (BusinessStaff $s) => $s->user !== null)
            ->map(fn (BusinessStaff $s) => [
                'id' => (int) $s->user_id,
                'name' => (string) $s->user->name,
                'title' => $s->title,
                'logo' => $s->user->logo ?: null,
            ])
            ->sortBy('name')
            ->values();
    }

    /** One physician as the patient reads him (name + his title at this business) — or null. */
    public static function party(?int $userId, int $businessId): ?array
    {
        if (! $userId) {
            return null;
        }

        $row = BusinessStaff::query()->where('business_id', $businessId)->where('user_id', $userId)->with('user:id,name,logo')->first();
        $user = $row?->user ?? User::query()->select('id', 'name', 'logo')->find($userId);

        return $user ? ['id' => (int) $user->id, 'name' => (string) $user->name, 'title' => $row?->title, 'logo' => $user->logo ?: null] : null;
    }
}
