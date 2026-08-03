<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Narrows a group to the slice each child can actually answer.
 *
 *   php artisan db:seed --class=ChildOptionScopeSeeder
 *
 * A group is attached to a child as a whole, which is fine when every member of
 * the list applies and wrong when it does not: «نجف و تحف» was offered غرفة نوم
 * and سفرة, a wedding hall was offered مؤتمرات وندوات, a car wash was offered
 * معدات ثقيلة, a pyjama shop was offered فساتين زفاف.
 *
 * That is the same complaint the sports pools answered, and the same answer:
 * scope per CHILD, keep the group one list. The map is in
 * data/child_option_scopes.php.
 *
 * Two guards, as everywhere else in this taxonomy:
 *   - an option a merchant has already ticked is never withdrawn;
 *   - a child absent from a group's map keeps the whole list, so silence in the
 *     data file means "no narrowing", never "narrow to nothing".
 *
 * Idempotent.
 */
class ChildOptionScopeSeeder extends Seeder
{
    public function run(): void
    {
        $scopes = require database_path('seeders/data/child_option_scopes.php');

        DB::transaction(function () use ($scopes) {
            $added = $removed = $kept = 0;

            foreach ($scopes as $groupName => $children) {
                $groupOptions = DB::table('options as o')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('g.name_ar', $groupName)
                    ->pluck('o.id');

                if ($groupOptions->isEmpty()) {
                    $this->command?->warn("  ! مجموعة «{$groupName}» فارغة أو غير موجودة — تُركت.");

                    continue;
                }

                foreach ($children as $childId => $allowed) {
                    $allowed = $groupOptions->intersect($allowed);

                    $existing = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->whereIn('option_id', $groupOptions)
                        ->pluck('option_id');

                    $toDrop = $existing->diff($allowed);

                    if ($toDrop->isNotEmpty()) {
                        $chosen = DB::table('option_user as ou')
                            ->join('users as u', 'u.id', '=', 'ou.user_id')
                            ->where('u.category_child_id', $childId)
                            ->whereIn('ou.option_id', $toDrop)
                            ->pluck('ou.option_id')
                            ->unique();

                        $kept += $chosen->count();
                        $toDrop = $toDrop->diff($chosen);
                    }

                    if ($toDrop->isNotEmpty()) {
                        $removed += DB::table('category_child_option')
                            ->where('child_id', $childId)
                            ->whereIn('option_id', $toDrop)
                            ->delete();
                    }

                    $toAdd = $allowed->diff($existing);

                    foreach ($toAdd->chunk(200) as $chunk) {
                        DB::table('category_child_option')->insert(
                            $chunk->map(fn ($id) => ['child_id' => (int) $childId, 'option_id' => (int) $id])
                                ->values()->all()
                        );
                    }

                    $added += $toAdd->count();
                }
            }

            $this->command?->info('Child option scopes:');
            $this->command?->line('  - مجموعات محدَّدة : ' . count($scopes));
            $this->command?->line("  - روابط أُزيلت خارج النطاق : {$removed}");
            $this->command?->line("  - روابط ناقصة أُضيفت : {$added}");
            $this->command?->line("  - أُبقيت لأن تاجرًا اختارها : {$kept}");
        });
    }
}
