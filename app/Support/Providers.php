<?php

namespace App\Support;

use App\Models\BusinessStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * «مقدّم الخدمة» — the staff members of ANY business who serve its customers directly (a barber, a trainer, a doctor,
 * a driver…): accepted, active, and marked `is_provider` by the owner. This is the one answer to «who can a piece of
 * work be assigned to» for every business; a physician (see {@see Physicians}) is a provider with a medical role.
 *
 * Reading side only — the assigning itself is {@see \App\Services\Assignments\AssignmentService}.
 */
final class Providers
{
    /** The caller's user id when he is a delegated provider of the acting business — otherwise null (owner, or a plain staff member). */
    public static function actingId(Request $request): ?int
    {
        if (BusinessContext::isOwner($request) || ! $request->user()) {
            return null;
        }

        $userId = (int) $request->user()->id;

        return self::isProviderOf($userId, BusinessContext::id($request)) ? $userId : null;
    }

    public static function isProviderOf(int $userId, int $businessId): bool
    {
        return self::query($businessId)->where('user_id', $userId)->exists();
    }

    /**
     * Everyone who may be assigned work at this business.
     *
     * @return Collection<int,array{id:int,name:string,title:?string,logo:?string,is_physician:bool}>
     */
    public static function of(int $businessId): Collection
    {
        return self::query($businessId)
            ->with('user:id,name,logo')
            ->get()
            ->filter(fn (BusinessStaff $s) => $s->user !== null)
            ->map(fn (BusinessStaff $s) => [
                'id' => (int) $s->user_id,
                'name' => (string) $s->user->name,
                'title' => $s->title,
                'logo' => $s->user->logo ?: null,
                'is_physician' => (bool) $s->is_physician,
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * One provider as a customer reads him (name + his title at this business) — or null. Works for a former
     * provider too, so an old booking still names who served it.
     *
     * @param  array<int,int>  $ids
     * @return array<int,array{id:int,name:string,title:?string,logo:?string}> keyed by user id
     */
    public static function parties(int $businessId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $titles = BusinessStaff::query()->where('business_id', $businessId)->whereIn('user_id', $ids)->pluck('title', 'user_id');

        return User::query()->select('id', 'name', 'logo')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (User $u) => [(int) $u->id => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'title' => $titles->get($u->id),
                'logo' => $u->logo ?: null,
            ]])
            ->all();
    }

    private static function query(int $businessId)
    {
        return BusinessStaff::query()
            ->where('business_id', $businessId)
            ->where(fn ($q) => $q->where('is_provider', true)->orWhere('is_physician', true))
            ->where('is_active', true)
            ->where('status', BusinessStaff::STATUS_ACCEPTED);
    }
}
