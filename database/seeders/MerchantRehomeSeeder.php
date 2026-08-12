<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Moves one account from the child it was filed under to the one it belongs to.
 *
 *     php artisan db:seed --class=MerchantRehomeSeeder
 *
 * See data/merchant_rehomes.php for the list and the reasoning. This is the
 * account-level twin of ChildRootDetachSeeder's `reassign_to`, for the case
 * where NOTHING about the taxonomy is wrong — both children are right, and one
 * business is simply standing in the wrong one. Only reading the business tells
 * you that, so it can never be inferred.
 *
 * Two guards:
 *
 *   - the destination must stand under the account's OWN root, or the merchant
 *     vanishes from every screen and no later step notices;
 *   - `tick_option` is written BEFORE the move, so the word the old child was
 *     saying about the business survives as the merchant's own answer. A
 *     business that arrives mute has been demoted, not moved.
 *
 * Idempotent: an account already sitting on its destination is skipped.
 */
class MerchantRehomeSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/merchant_rehomes.php';

        $this->command?->info('Merchant rehomes:');

        DB::transaction(function () use ($data) {
            foreach ($data as $entry) {
                $this->apply($entry);
            }
        });
    }

    /** @param array<string,mixed> $entry */
    private function apply(array $entry): void
    {
        $userId = (int) $entry['user_id'];
        $user = DB::table('users')->where('id', $userId)->first(['id', 'category_id', 'category_child_id']);

        if ($user === null) {
            $this->command?->warn("  ! الحساب #{$userId} غير موجود — يُتخطّى.");

            return;
        }

        $from = $this->childId((string) $entry['from_child_ar']);
        $to = $this->childId((string) $entry['to_child_ar']);

        if ($to <= 0) {
            $this->command?->warn("  ! «{$entry['to_child_ar']}» غير موجود — لم يُنقل #{$userId}.");

            return;
        }

        if ((int) $user->category_child_id === $to) {
            $this->command?->line("  - #{$userId} على «{$entry['to_child_ar']}» بالفعل.");

            return;
        }

        if ((int) $user->category_child_id !== $from) {
            // Somebody moved it since the entry was written. Say so rather than
            // move it anyway: the reasoning was about where it WAS.
            $this->command?->warn("  ! #{$userId} لم يعد تحت «{$entry['from_child_ar']}» — لم يُنقل.");

            return;
        }

        $rootId = (int) $user->category_id;

        $stands = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', $to)->exists();

        if (! $stands) {
            $this->command?->warn("  ! «{$entry['to_child_ar']}» لا يقف تحت جذر الحساب ({$rootId})"
                . " — لم يُنقل #{$userId} حتى لا يختفي من كل الشاشات.");

            return;
        }

        $optionId = $this->tickable($entry, $to, $rootId);

        if ($optionId === null) {
            return; // refused; tickable() has already said why
        }

        if ($optionId > 0) {
            DB::table('option_user')->updateOrInsert(
                ['user_id' => $userId, 'option_id' => $optionId],
                []
            );
        }

        DB::table('users')->where('id', $userId)
            ->update(['category_child_id' => $to, 'updated_at' => now()]);

        $this->command?->line("  - #{$userId} : «{$entry['from_child_ar']}» → «{$entry['to_child_ar']}»");
        $this->command?->line("      السبب : {$entry['why']}");
    }

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master as c')
            ->join('category_parent_child as p', 'p.child_id', '=', 'c.id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->orderBy('c.id')
            ->value('c.id');
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return int|null 0 when none was asked for, null when the entry is refused
     */
    private function tickable(array $entry, int $targetId, int $rootId): ?int
    {
        $wanted = $entry['tick_option'] ?? null;

        if ($wanted === null) {
            return 0;
        }

        $optionId = (int) DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $targetId)
            ->whereIn('cco.category_id', [0, $rootId])
            ->where('o.name_ar', $wanted)
            ->value('o.id');

        if ($optionId <= 0) {
            $this->command?->warn("  ! «{$entry['to_child_ar']}» لا يحمل «{$wanted}» تحت هذا الجذر"
                . " — لم يُنقل #{$entry['user_id']}.");

            return null;
        }

        return $optionId;
    }
}
