<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the owner took away, so a seeder can stop handing it back.
 *
 *     «انسحب البذرة، اتبع تنظيمي اليدوي» — owner, 2026-08-10
 *
 * The option seeders are add-only and always will be: an add-only seeder cannot
 * destroy curation. What it also cannot do is tell «never granted» from «granted
 * and then removed», so every run restored what he had just unticked — twenty-two
 * carpenters got the whole furniture list back, a café got factory-gate freight
 * terms, a supermarket got sandwiches.
 *
 * This class is the missing half. `record()` remembers a removal, `filter()` is
 * what a seeder calls before granting, and `clear()` forgets a removal the
 * moment he ticks the option again — an explicit grant is him overruling his own
 * earlier decision, and it must win as loudly as the removal did.
 *
 * Scoped exactly like `category_child_option`: 0 means every root, an id means
 * that root alone. Read the two together and they are one statement.
 */
class ChildOptionWithdrawals
{
    public const ALL_ROOTS = 0;

    public const TABLE = 'category_child_option_withdrawals';

    /**
     * Remove from `$optionIds` everything withdrawn for this child under this
     * root. The one call a seeder needs.
     *
     * A withdrawal at ALL_ROOTS blocks every root; a withdrawal at a root blocks
     * that root only. Asking about root 0 — «grant this to every root» — is
     * blocked by a withdrawal under ANY root, because a grant that reaches every
     * root would step on the one that said no.
     *
     * @param  iterable<int>  $optionIds
     * @return array<int,int>
     */
    public function filter(int $childId, int $rootId, iterable $optionIds): array
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $blocked = DB::table(self::TABLE)
            ->where('child_id', $childId)
            ->whereIn('option_id', $ids)
            ->when($rootId > 0, fn ($q) => $q->whereIn('category_id', [self::ALL_ROOTS, $rootId]))
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        return $ids->diff($blocked)->values()->all();
    }

    /**
     * Every withdrawn option, keyed by child, for the seeders that grant SHARED
     * rows (`category_id = 0`) across the whole taxonomy in one pass.
     *
     * Scope is deliberately ignored: a shared grant reaches every root, so a
     * withdrawal under any single root is enough to block it. Those seeders walk
     * hundreds of children, and asking per child turns one query into hundreds.
     *
     * @return array<int,array<int,bool>>  [child_id => [option_id => true]]
     */
    public function blockedByChild(): array
    {
        $map = [];

        foreach (DB::table(self::TABLE)->get(['child_id', 'option_id']) as $row) {
            $map[(int) $row->child_id][(int) $row->option_id] = true;
        }

        return $map;
    }

    /** Option ids withdrawn for this child under this root. */
    public function idsFor(int $childId, int $rootId): Collection
    {
        return DB::table(self::TABLE)
            ->where('child_id', $childId)
            ->when($rootId > 0, fn ($q) => $q->whereIn('category_id', [self::ALL_ROOTS, $rootId]))
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Remember that these options were taken away.
     *
     * @param  iterable<int>  $optionIds
     */
    public function record(int $childId, int $rootId, iterable $optionIds, string $source = 'admin'): int
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $now = now();

        $rows = $ids->map(fn ($id) => [
            'child_id' => $childId,
            'category_id' => max($rootId, self::ALL_ROOTS),
            'option_id' => $id,
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        $written = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $written += DB::table(self::TABLE)->insertOrIgnore($chunk);
        }

        return $written;
    }

    /**
     * Forget a removal, because the option was granted again.
     *
     * Granting under one root does NOT simply delete an ALL_ROOTS withdrawal —
     * that would silently un-withdraw the option everywhere off the back of a
     * decision made about one root. The blanket row is split instead, re-recorded
     * against each of the child's other roots before it goes. This is the same
     * move `CategoryChildOptionScope::splitShared()` makes on the granting side,
     * for the same reason, and the two tables stay readable together only if
     * both do it.
     *
     * Granting under root 0 reaches every root, so there it does clear
     * everything: nothing is left for a narrower row to mean.
     *
     * @param  iterable<int>  $optionIds
     */
    public function clear(int $childId, int $rootId, iterable $optionIds): int
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        if ($rootId <= 0) {
            return DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->whereIn('option_id', $ids)
                ->delete();
        }

        return DB::transaction(function () use ($childId, $rootId, $ids) {
            $blanket = DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->where('category_id', self::ALL_ROOTS)
                ->whereIn('option_id', $ids)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id);

            if ($blanket->isNotEmpty()) {
                $otherRoots = DB::table('category_parent_child')
                    ->where('child_id', $childId)
                    ->where('parent_id', '!=', $rootId)
                    ->pluck('parent_id')
                    ->map(fn ($id) => (int) $id);

                foreach ($otherRoots as $other) {
                    $this->record($childId, $other, $blanket, 'split');
                }
            }

            return DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->whereIn('category_id', [self::ALL_ROOTS, $rootId])
                ->whereIn('option_id', $ids)
                ->delete();
        });
    }
}
