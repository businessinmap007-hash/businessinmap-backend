<?php

namespace App\Services\Business;

use App\Models\BusinessStaff;
use App\Models\User;
use App\Support\BusinessCapability;

/**
 * Resolves who may act for a business — the owner (the account itself, with
 * every capability) or an active delegated staff member (with the capabilities
 * they were granted) — and manages the staff roster.
 */
class BusinessAccessService
{
    public const NO_ACCESS = 'no_access';
    public const AMBIGUOUS = 'ambiguous';

    /**
     * Work out the business context for a caller.
     *
     * @return array{business:User,is_owner:bool,capabilities:array<string>}|string
     *   the context, or self::NO_ACCESS / self::AMBIGUOUS when it can't resolve.
     */
    public function resolveContext(User $caller, ?int $requestedBusinessId): array|string
    {
        // Owner acting for their own account (explicitly or by default).
        if ($caller->isBusiness() && ($requestedBusinessId === null || $requestedBusinessId === (int) $caller->id)) {
            return ['business' => $caller, 'is_owner' => true, 'capabilities' => BusinessCapability::keys()];
        }

        $memberships = BusinessStaff::query()
            ->where('user_id', (int) $caller->id)
            ->where('is_active', true)
            ->when($requestedBusinessId !== null, fn ($q) => $q->where('business_id', $requestedBusinessId))
            ->get();

        if ($memberships->isEmpty()) {
            return self::NO_ACCESS;
        }

        if ($requestedBusinessId === null && $memberships->count() > 1) {
            return self::AMBIGUOUS; // caller must name which business they act for
        }

        $staff = $memberships->first();
        $business = User::query()->find((int) $staff->business_id);

        if (! $business) {
            return self::NO_ACCESS;
        }

        return [
            'business' => $business,
            'is_owner' => false,
            'capabilities' => BusinessCapability::sanitize((array) $staff->capabilities),
        ];
    }

    /* --------------------------------------------------------- roster CRUD */

    /** @return \Illuminate\Support\Collection<int,BusinessStaff> */
    public function roster(int $businessId)
    {
        return BusinessStaff::query()
            ->where('business_id', $businessId)
            ->with('user:id,name,phone,logo')
            ->latest('id')
            ->get();
    }

    /**
     * Add or update a staff member's grant. Capabilities are sanitised to the
     * known registry, so an unknown key can never be stored.
     */
    public function upsert(int $businessId, int $userId, ?string $title, array $capabilities, bool $isActive = true): BusinessStaff
    {
        $staff = BusinessStaff::query()->firstOrNew([
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        $staff->title = $title;
        $staff->capabilities = BusinessCapability::sanitize($capabilities);
        $staff->is_active = $isActive;
        $staff->save();

        return $staff;
    }

    public function remove(int $businessId, int $userId): void
    {
        BusinessStaff::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->delete();
    }

    /** The businesses a user may act for as staff, with their capabilities. */
    public function membershipsFor(int $userId)
    {
        return BusinessStaff::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with('business:id,name,logo')
            ->get();
    }
}
