<?php

namespace App\Services\Business;

use App\Models\BusinessStaff;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Services\DeliveryDispatchService;
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

    public function __construct(private readonly DeliveryDispatchService $delivery)
    {
    }

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
        $sanitized = BusinessCapability::sanitize($capabilities);

        if ($isActive && in_array(BusinessCapability::DRIVERS, $sanitized, true)) {
            $this->linkDriver($businessId, $userId);
        } else {
            $this->setDriverActive($businessId, $userId, false);
        }

        $staff = BusinessStaff::query()->firstOrNew([
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        $staff->title = $title;
        $staff->capabilities = $sanitized;
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

        $this->setDriverActive($businessId, $userId, false);
    }

    /**
     * The `drivers` capability is the Staff & Permissions door onto the same
     * private-fleet linking DeliveryDriverController's own "موصّليّ" screen
     * uses (DeliveryDispatchService::linkBusinessDriver) — same find-by-phone
     * rule, including the refusal to poach a driver already privately linked
     * to a DIFFERENT business, which is why this runs BEFORE the staff row is
     * saved: a poach attempt must fail the whole grant, not silently skip the
     * one capability.
     */
    private function linkDriver(int $businessId, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->phone) {
            return;
        }

        $this->delivery->linkBusinessDriver($businessId, (string) $user->phone);
    }

    /**
     * Losing the capability (unchecked, staff deactivated, or removed
     * entirely) only ever takes the driver off duty — never clears
     * `business_id` — matching the dedicated roster screen's own rule that a
     * driver row is never hard-detached, so its counters stay attributable.
     */
    private function setDriverActive(int $businessId, int $userId, bool $active): void
    {
        DeliveryDriver::query()
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->update(['is_active' => $active]);
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
