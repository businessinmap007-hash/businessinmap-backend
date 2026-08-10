<?php

namespace App\Services;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes a category child's options PER ROOT.
 *
 * A child row is shared across roots — «آثاث» sits under مصانع، معارض، ورش،
 * شركات — and `category_child_option` used to be keyed by the child alone, so
 * all four asked the same questions. A furniture factory answers about
 * materials and output; a furniture showroom answers about instalments and
 * delivery. `category_child_option.category_id` is what lets them differ:
 *
 *   0        the option is granted under EVERY root the child sits under
 *   <id>     the option is granted under that root alone
 *
 * Shared is the normal state and what every keyword seeder writes. A per-root
 * row is only created when a root actually disagrees, so the table grows by the
 * number of disagreements, not by roots × options.
 *
 * Every writer of this table that knows its root goes through here, so the
 * splitting rule is stated once.
 */
class CategoryChildOptionScope
{
    public const ALL_ROOTS = 0;

    /**
     * Every write here is a HAND write — this is the admin's door, and no seeder
     * comes through it. So both directions are decisions worth remembering: a
     * removal is WITHDRAWN so no add-only seeder grants it again, and a grant is
     * PINNED so no replace-style seeder drops it.
     *
     * «انسحب البذرة، اتبع تنظيمي اليدوي» · «ثبّت الإضافات اليدوية أيضًا»
     */
    public function __construct(private readonly ChildOptionDecisions $decisions = new ChildOptionDecisions) {}

    /** Option ids this child carries under this root: shared rows plus its own. */
    public function idsFor(int $childId, int $rootId): Collection
    {
        return DB::table('category_child_option')
            ->where('child_id', $childId)
            ->when($rootId > 0, fn ($q) => $q->whereIn('category_id', [self::ALL_ROOTS, $rootId]))
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** Of those, the ones granted to every root at once. */
    public function sharedIds(int $childId): Collection
    {
        return DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', self::ALL_ROOTS)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Make the child's option set under one root exactly `$wanted`, leaving
     * every other root as it was.
     *
     * Passing root 0 means "no root in hand": the whole child is replaced with
     * shared rows, which is what a screen with no root selected has always
     * done.
     *
     * @param  array<int,int>  $wanted    option ids, in the order they should sit
     * @param  array<int,int>  $keep      option ids that may not be withdrawn
     * @return array{added:int,removed:int,split:int}
     */
    public function syncFor(int $childId, int $rootId, array $wanted, array $keep = [], bool $updateOrder = false): array
    {
        $wanted = collect($wanted)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $keep = collect($keep)->map(fn ($id) => (int) $id)->unique();

        if ($rootId <= 0) {
            return $this->replaceWholeChild($childId, $wanted);
        }

        $rows = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->whereIn('category_id', [self::ALL_ROOTS, $rootId])
            ->get(['option_id', 'category_id']);

        $shared = $rows->where('category_id', self::ALL_ROOTS)->pluck('option_id')->map(fn ($id) => (int) $id)->unique();
        $mine = $rows->where('category_id', $rootId)->pluck('option_id')->map(fn ($id) => (int) $id)->unique();
        $existing = $shared->merge($mine)->unique();

        $drop = $existing->diff($wanted)->diff($keep);
        $add = $wanted->diff($existing);

        $result = ['added' => 0, 'removed' => 0, 'split' => 0];

        DB::transaction(function () use ($childId, $rootId, $wanted, $shared, $mine, $drop, $add, $updateOrder, &$result) {
            $result['removed'] += $this->revokeMine($childId, $rootId, $drop->intersect($mine));
            $result['split'] += $this->splitShared($childId, $rootId, $drop->intersect($shared));
            $result['removed'] += $result['split'];
            $result['added'] += $this->grantFor($childId, $rootId, $add, $wanted);

            if ($updateOrder) {
                $this->applyOrder($childId, $rootId, $wanted);
            }
        });

        return $result;
    }

    /**
     * Grant options under one root. Rows already granted — whether shared or to
     * this root — are left alone.
     *
     * @param  iterable<int>  $optionIds
     * @param  \Illuminate\Support\Collection<int,int>|null  $order  positions to store as `reorder`
     */
    public function grantFor(int $childId, int $rootId, iterable $optionIds, ?Collection $order = null): int
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $rows = $ids->map(fn ($id) => [
            'child_id' => $childId,
            'category_id' => max($rootId, self::ALL_ROOTS),
            'option_id' => $id,
            'reorder' => $order ? max((int) $order->search($id), 0) : 0,
        ])->values()->all();

        $added = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $added += DB::table('category_child_option')->insertOrIgnore($chunk);
        }

        // «ثبّت الإضافات اليدوية أيضًا». Ticking an option on is a decision in
        // its own right, not just the absence of a removal: the replace-style
        // seeders drop whatever their file does not declare, so without a pin
        // an option he adds by hand survives exactly until the next run.
        //
        // `pin()` clears the opposite decision itself — ticking and unticking
        // are one toggle and the last one wins.
        $this->decisions->pin($childId, $rootId, $ids);

        return $added;
    }

    /**
     * Withdraw options under one root only.
     *
     * A row that belongs to this root is simply deleted. A SHARED row cannot
     * be: deleting it would strip the option from every other root as a side
     * effect — the exact bug per-root options exist to end. It is split
     * instead, re-granted explicitly to each of the child's other roots before
     * the shared row goes.
     *
     * @param  iterable<int>  $optionIds
     * @return array{removed:int,split:int}
     */
    public function revokeFor(int $childId, int $rootId, iterable $optionIds, array $keep = []): array
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->unique()
            ->diff(collect($keep)->map(fn ($id) => (int) $id));

        if ($ids->isEmpty()) {
            return ['removed' => 0, 'split' => 0];
        }

        if ($rootId <= 0) {
            $removed = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $ids)
                ->delete();

            // No root in hand means the removal was meant for all of them.
            $this->decisions->record($childId, self::ALL_ROOTS, $ids);

            return ['removed' => $removed, 'split' => 0];
        }

        return DB::transaction(function () use ($childId, $rootId, $ids) {
            $shared = $this->sharedIds($childId)->intersect($ids);

            $removed = $this->revokeMine($childId, $rootId, $ids->diff($shared));
            $split = $this->splitShared($childId, $rootId, $shared);

            return ['removed' => $removed + $split, 'split' => $split];
        });
    }

    /** Rows that belong to this root alone — nothing else sees them. */
    private function revokeMine(int $childId, int $rootId, Collection $optionIds): int
    {
        if ($optionIds->isEmpty()) {
            return 0;
        }

        $this->decisions->record($childId, $rootId, $optionIds);

        return DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', $rootId)
            ->whereIn('option_id', $optionIds)
            ->delete();
    }

    /** Hand a shared option to the child's other roots, then drop the shared row. */
    private function splitShared(int $childId, int $rootId, Collection $optionIds): int
    {
        if ($optionIds->isEmpty()) {
            return 0;
        }

        $otherRoots = DB::table('category_parent_child')
            ->where('child_id', $childId)
            ->where('parent_id', '!=', $rootId)
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id);

        $keep = [];

        foreach ($optionIds as $optionId) {
            foreach ($otherRoots as $other) {
                $keep[] = [
                    'child_id' => $childId,
                    'category_id' => $other,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ];
            }
        }

        foreach (array_chunk($keep, 200) as $chunk) {
            DB::table('category_child_option')->insertOrIgnore($chunk);
        }

        DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', self::ALL_ROOTS)
            ->whereIn('option_id', $optionIds)
            ->delete();

        // Only THIS root said no. The others were just handed the option
        // explicitly, so the withdrawal is theirs to escape too.
        $this->decisions->record($childId, $rootId, $optionIds);

        return $optionIds->count();
    }

    /** @return array{added:int,removed:int,split:int} */
    private function replaceWholeChild(int $childId, Collection $wanted): array
    {
        return DB::transaction(function () use ($childId, $wanted) {
            // What is about to be deleted and is NOT coming back is a removal,
            // and has to be read before the delete. Everything in `$wanted`
            // survives and its withdrawals are cleared by the grant below.
            $dropped = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->diff($wanted);

            $removed = DB::table('category_child_option')->where('child_id', $childId)->delete();

            $this->decisions->record($childId, self::ALL_ROOTS, $dropped);

            $added = $this->grantFor($childId, self::ALL_ROOTS, $wanted, $wanted);

            return ['added' => $added, 'removed' => $removed, 'split' => 0];
        });
    }

    private function applyOrder(int $childId, int $rootId, Collection $wanted): void
    {
        foreach ($wanted as $position => $optionId) {
            DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('category_id', [self::ALL_ROOTS, $rootId])
                ->where('option_id', $optionId)
                ->update(['reorder' => $position]);
        }
    }
}
