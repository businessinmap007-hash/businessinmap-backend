<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the owner decided by hand, so the seeders stop arguing with him.
 *
 *     «انسحب البذرة، اتبع تنظيمي اليدوي» — owner, 2026-08-10
 *     «ثبّت الإضافات اليدوية أيضًا»      — owner, same day
 *
 * The option seeders cannot tell «never granted» from «granted then removed»,
 * nor «declared by me» from «added by him». Left to themselves the add-only ones
 * restore what he unticked and the replace-style ones drop what he ticked. Two
 * failures, one cause: a seeder's file is not the only thing that knows what a
 * child should offer.
 *
 * One row per (child, root, option) plus a kind:
 *
 *   withdrawn   he took it away; no seeder may grant it
 *   pinned      he put it there; no seeder may drop it
 *
 * The two are a TOGGLE, never both: recording either deletes the other, because
 * the last thing he did is the thing he meant. That is also what makes any row
 * here cost one click to reverse rather than a migration.
 *
 * Scoped exactly like `category_child_option`: 0 means every root, an id means
 * that root alone. Read the two tables together and they are one statement.
 */
class ChildOptionDecisions
{
    public const ALL_ROOTS = 0;

    public const TABLE = 'category_child_option_decisions';

    public const WITHDRAWN = 'withdrawn';

    public const PINNED = 'pinned';

    /**
     * Remove from `$optionIds` everything withdrawn for this child under this
     * root. The one call an add-only seeder needs.
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

        $blocked = $this->query($childId, $rootId, self::WITHDRAWN)
            ->whereIn('option_id', $ids)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        return $ids->diff($blocked)->values()->all();
    }

    /**
     * Add back to `$drop` nothing, and take out of it everything pinned. The one
     * call a replace-style seeder needs before deleting.
     *
     * @param  iterable<int>  $optionIds  what the seeder means to drop
     * @return array<int,int>             what it may actually drop
     */
    public function droppable(int $childId, int $rootId, iterable $optionIds): array
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $pinned = $this->query($childId, $rootId, self::PINNED)
            ->whereIn('option_id', $ids)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        return $ids->diff($pinned)->values()->all();
    }

    /**
     * Every decision of one kind, keyed by child, for the seeders that walk the
     * whole taxonomy in one pass.
     *
     * Scope is deliberately ignored: those seeders write SHARED rows, which
     * reach every root, so a decision under any single root speaks for the
     * grant. Asking per child turns one query into hundreds.
     *
     * @return array<int,array<int,bool>>  [child_id => [option_id => true]]
     */
    public function byChild(string $kind): array
    {
        $map = [];

        foreach (DB::table(self::TABLE)->where('kind', $kind)->get(['child_id', 'option_id']) as $row) {
            $map[(int) $row->child_id][(int) $row->option_id] = true;
        }

        return $map;
    }

    /** Shorthand for the add-only seeders, which only ever ask about withdrawals. */
    public function blockedByChild(): array
    {
        return $this->byChild(self::WITHDRAWN);
    }

    /** Shorthand for the replace-style seeders, which only ever ask about pins. */
    public function pinnedByChild(): array
    {
        return $this->byChild(self::PINNED);
    }

    /** Option ids decided one way for this child under this root. */
    public function idsFor(int $childId, int $rootId, string $kind = self::WITHDRAWN): Collection
    {
        return $this->query($childId, $rootId, $kind)
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
        return $this->write($childId, $rootId, $optionIds, self::WITHDRAWN, $source);
    }

    /**
     * Remember that these options were put there on purpose.
     *
     * @param  iterable<int>  $optionIds
     */
    public function pin(int $childId, int $rootId, iterable $optionIds, string $source = 'admin'): int
    {
        return $this->write($childId, $rootId, $optionIds, self::PINNED, $source);
    }

    /**
     * Forget a decision of one kind, because the opposite was just made.
     *
     * Deciding under one root does NOT simply delete an ALL_ROOTS row — that
     * would silently reverse the decision everywhere off the back of a choice
     * made about one root. The blanket row is split instead, re-recorded against
     * each of the child's other roots before it goes. This is the same move
     * `CategoryChildOptionScope::splitShared()` makes on the option rows, for the
     * same reason, and the two tables stay readable together only if both do it.
     *
     * Deciding under root 0 reaches every root, so there it does clear
     * everything: nothing is left for a narrower row to mean.
     *
     * @param  iterable<int>  $optionIds
     */
    public function clear(int $childId, int $rootId, iterable $optionIds, string $kind = self::WITHDRAWN): int
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        if ($rootId <= 0) {
            return DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->where('kind', $kind)
                ->whereIn('option_id', $ids)
                ->delete();
        }

        return DB::transaction(function () use ($childId, $rootId, $ids, $kind) {
            $blanket = DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->where('kind', $kind)
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
                    $this->write($childId, $other, $blanket, $kind, 'split');
                }
            }

            return DB::table(self::TABLE)
                ->where('child_id', $childId)
                ->where('kind', $kind)
                ->whereIn('category_id', [self::ALL_ROOTS, $rootId])
                ->whereIn('option_id', $ids)
                ->delete();
        });
    }

    /**
     * @param  iterable<int>  $optionIds
     */
    private function write(int $childId, int $rootId, iterable $optionIds, string $kind, string $source): int
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        // Ticking and unticking are one toggle, so the opposite decision cannot
        // survive this one. Without it a child could be both pinned and
        // withdrawn and the two seeders reading this table would disagree
        // forever, each correctly.
        $this->clear($childId, $rootId, $ids, $kind === self::PINNED ? self::WITHDRAWN : self::PINNED);

        $now = now();

        $rows = $ids->map(fn ($id) => [
            'child_id' => $childId,
            'category_id' => max($rootId, self::ALL_ROOTS),
            'option_id' => $id,
            'kind' => $kind,
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

    private function query(int $childId, int $rootId, string $kind): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TABLE)
            ->where('child_id', $childId)
            ->where('kind', $kind)
            ->when($rootId > 0, fn ($q) => $q->whereIn('category_id', [self::ALL_ROOTS, $rootId]));
    }
}
