<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fixes, then sweeps, the options left with no group at all (`group_id` NULL).
 *
 *   php artisan db:seed --class=OrphanOptionsCleanupSeeder
 *
 * 1. «Kitchen draw» (#219) is a REAL option — five children link it (نجار
 *    موبيليا، مطابخ و دريسنج، مفروشات، نجف، نجف و تحف) — but it carries an
 *    English string in name_ar and belongs to no group, so no picker can show
 *    it. Renamed to «أدراج ووحدات مطبخ» and filed under أثاث وتشطيب منزلي.
 *
 * 2. «محافظات» (#81, "Cities") is a geographic concept, not a descriptor of a
 *    business. Location is handled by the platform's own location system, so
 *    its single link (to سيارات under معارض) is removed.
 *
 * 3. Everything still groupless is then DELETED (owner call 2026-08-04). The
 *    earlier pass parked such rows rather than removing them, on the reasoning
 *    that a groupless option is already invisible. It is — but it is also
 *    unreachable: `options` has no is_active column, so the group is the only
 *    retirement boundary the schema offers, and a row outside every group can
 *    never be shown, edited or restored through any screen. That is a deletion
 *    in all but name, minus the freed name_en (which is UNIQUE platform-wide,
 *    so a dead row silently costs a real one its English name).
 *
 * The sweep REFUSES any row a child, a business or a priced offering still
 * points at, and reports it instead — an option with links is a group that went
 * missing, not junk. It ran against 11 rows, all with zero references:
 * سيارة، اكسسوارات سيارات، قطع غيار سيارات، سيارات، محافظات، آ11، بيع، كهرباء
 * (2020-2023 imports) and جيولوجيا، علوم متكاملة، حاسب آلي — the three that
 * `RetireDuplicateOptionsSeeder` had parked in «خيارات متقاعدة» before that
 * group was deleted from the admin panel, which set their group_id NULL.
 *
 * ⚠ Deleting a non-empty group from the admin panel does the same thing to its
 * options (`options.group_id` is ON DELETE SET NULL). So run this deliberately,
 * read the refusals, and do not wire it into DatabaseSeeder — otherwise a group
 * deleted by mistake takes its options with it on the next seed.
 *
 * Idempotent.
 */
class OrphanOptionsCleanupSeeder extends Seeder
{
    private const FURNITURE_GROUP = 'أثاث وتشطيب منزلي';

    public function run(): void
    {
        DB::transaction(function () {
            $fixed = $this->rehomeKitchenOption();
            $unlinked = $this->retireGeographyOption();
            [$deleted, $kept] = $this->purgeGrouplessOptions();

            $stillOrphan = DB::table('options as o')
                ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereNull('g.id')
                ->count();

            $this->command?->info('Orphan options cleanup:');
            $this->command?->line("  - «Kitchen draw» re-homed : {$fixed}");
            $this->command?->line("  - «محافظات» links removed : {$unlinked}");
            $this->command?->line('  - groupless rows deleted  : ' . count($deleted)
                . (count($deleted) ? ' → ' . implode('، ', $deleted) : ''));
            $this->command?->line("  - groupless rows remaining : {$stillOrphan}");

            if ($kept !== []) {
                $this->command?->warn(
                    '  ! خيارات بلا مجموعة ما زالت مستخدمة — أعِدها إلى مجموعة بدل حذفها: '
                    . implode('، ', $kept)
                );
            }
        });
    }

    /**
     * Deletes every option left outside all groups, unless something still
     * points at it.
     *
     * The reference check is derived from the DECLARED FOREIGN KEYS rather than
     * a hand-written list of tables: three of the four pointers CASCADE
     * (category_child_option, option_user, offering_options) so a delete would
     * take a merchant's choice with it silently. The fourth,
     * business_service_prices.line_option_id, has no foreign key at all — it
     * would simply dangle — so it is named explicitly below.
     *
     * Matching on the COLUMN NAME instead was tried and is wrong: the paused
     * taxonomy-lab clone has a `category_child_option_new.option_id` whose ids
     * belong to `options_new`, a different 151-row table. It collides
     * numerically with live option ids and made the sweep refuse seven rows it
     * should have deleted. Follow the reference, never the name.
     *
     * @return array{0: list<string>, 1: list<string>} [deleted names, refused names]
     */
    private function purgeGrouplessOptions(): array
    {
        $pointers = DB::select(
            "SELECT k.TABLE_NAME, k.COLUMN_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE k
               ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
              AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.REFERENCED_TABLE_NAME = 'options'"
        );

        // The one pointer with no foreign key to declare itself.
        $pointers[] = (object) ['TABLE_NAME' => 'business_service_prices', 'COLUMN_NAME' => 'line_option_id'];

        $orphans = DB::table('options as o')
            ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereNull('g.id')
            ->get(['o.id', 'o.name_ar']);

        $deleted = [];
        $kept = [];

        foreach ($orphans as $option) {
            $used = [];

            foreach ($pointers as $p) {
                $n = DB::table($p->TABLE_NAME)->where($p->COLUMN_NAME, $option->id)->count();

                if ($n > 0) {
                    $used[] = "{$p->TABLE_NAME}×{$n}";
                }
            }

            if ($used !== []) {
                $kept[] = "«{$option->name_ar}» (" . implode('، ', $used) . ')';

                continue;
            }

            DB::table('options')->where('id', $option->id)->delete();
            $deleted[] = (string) $option->name_ar;
        }

        return [$deleted, $kept];
    }

    /** A real furniture attribute that lost its Arabic name and its group. */
    private function rehomeKitchenOption(): int
    {
        $option = DB::table('options')->where('id', 219)->first(['id', 'name_ar', 'group_id']);

        if (! $option || $option->group_id) {
            return 0; // absent, or already re-homed
        }

        $groupId = (int) DB::table('option_groups')->where('name_ar', self::FURNITURE_GROUP)->value('id');

        if (! $groupId) {
            $this->command?->warn('  ! مجموعة الأثاث غير موجودة — تُرك #219.');

            return 0;
        }

        DB::table('options')->where('id', 219)->update([
            'group_id' => $groupId,
            'name_ar' => 'أدراج ووحدات مطبخ',
            'name_en' => 'Kitchen Drawers & Units',
        ]);

        return 1;
    }

    /** Geography is not a business descriptor; the location system owns it. */
    private function retireGeographyOption(): int
    {
        $option = DB::table('options')->where('id', 81)->first(['id', 'group_id']);

        if (! $option) {
            return 0;
        }

        return DB::table('category_child_option')->where('option_id', 81)->delete();
    }
}
