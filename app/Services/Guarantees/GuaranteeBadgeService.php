<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;

/**
 * The badge a guarantee level wears beside an account's name («عملة فضية مكتوب عليها BIM») — so the other side of a
 * deal can see at a glance what kind of customer or business it is dealing with, and how much its guarantee covers.
 *
 * What is public is deliberately small: the level (code + both names) and the total coverage it stands for. Never the
 * amount frozen right now, never who the friends behind an operation are.
 */
final class GuaranteeBadgeService
{
    /** At most this many accounts per call. */
    public const MAX = 100;

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{code:string,name_ar:string,name_en:string,priority:int,covers_up_to:float,target_type:string}|null>
     *         one entry per requested id; null = the account has no usable guarantee (no badge)
     */
    public function forUsers(array $userIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $i) => $i > 0))), 0, self::MAX);

        if ($ids === []) {
            return [];
        }

        $out = array_fill_keys($ids, null);

        $types = User::query()->whereIn('id', $ids)->pluck('type', 'id');

        $guarantees = UserGuarantee::query()
            ->whereIn('user_id', $ids)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->orderBy('id')
            ->get();

        $levels = GuaranteeLevel::query()
            ->whereIn('id', $guarantees->flatMap(fn (UserGuarantee $g) => [$g->effective_level_id, $g->purchased_level_id])->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($guarantees as $g) {
            $userId = (int) $g->user_id;
            $isBusiness = (string) ($types[$userId] ?? '') === User::TYPE_BUSINESS;
            $wanted = $isBusiness ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT;

            // an account shows the guarantee of its own kind; the latest one wins
            if ((string) $g->target_type !== $wanted) {
                continue;
            }

            $level = $levels->get((int) ($g->effective_level_id ?: $g->purchased_level_id));

            if (! $level) {
                continue;
            }

            $out[$userId] = [
                'code' => (string) $level->code,
                'name_ar' => (string) ($level->name_ar ?: $level->code),
                'name_en' => (string) ($level->name_en ?: $level->code),
                'priority' => (int) $level->priority,
                'covers_up_to' => round((float) $g->current_coverage_amount, 2),
                'target_type' => $wanted,
            ];
        }

        return $out;
    }
}
