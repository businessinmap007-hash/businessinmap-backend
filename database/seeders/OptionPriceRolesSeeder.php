<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sorts every option group by what it does to a PRICE.
 *
 *   php artisan db:seed --class=OptionPriceRolesSeeder
 *
 * Options and priced item types read like two names for one thing — «عظام»
 * beside «كشف», «غرفة نوم» beside a furniture product — so the obvious move was
 * to merge them. That would have been wrong twice over: 41 specialties × 7 item
 * types is 287 combined names for one child, and a specialty has to be
 * searchable BEFORE anyone has priced anything. They are two coordinates of one
 * line, not two vocabularies: «كشف عظام» is the thing with a price.
 *
 * Sorting the 39 live groups by "does the customer pay for this exact thing?"
 * gives three answers — line / modifier / descriptive — and the middle one is
 * why a single is_priceable flag would not have done: nine groups change a
 * price without ever being one.
 *
 * It also splits «موضة وعناية شخصية», which asked two questions at once — WHO
 * it is for (حريمي/رجالي/أطفال) and WHAT is sold (ملابس، أقمشة، فساتين زفاف).
 * The audience qualifies a line; the product IS the line. Same defect as the
 * old commerce grab-bag, same remedy.
 *
 * Idempotent, and it never touches an option's links: splitting a group moves
 * `options.group_id` only, and a link points at an OPTION.
 */
class OptionPriceRolesSeeder extends Seeder
{
    /** The audience rows that leave «موضة وعناية شخصية» for their own group. */
    private const AUDIENCE = ['Female', 'Male', 'Kids'];

    private const AUDIENCE_GROUP = ['ar' => 'الجمهور المستهدف', 'en' => 'Target Audience'];

    private const FASHION_GROUP = 'موضة وعناية شخصية';

    public function run(): void
    {
        DB::transaction(function () {
            $moved = $this->splitAudienceOutOfFashion();
            [$applied, $missing, $unclassified] = $this->applyRoles();

            $this->command?->info('Option price roles:');
            $this->command?->line("  - خيارات نُقلت إلى «الجمهور المستهدف» : {$moved}");
            $this->command?->line('  - مجموعات صُنِّفت : ' . collect($applied)->map(fn ($n, $r) => "{$r}={$n}")->implode(' · '));

            if ($missing) {
                $this->command?->warn('  ! أسماء في الملف بلا مجموعة : ' . implode('، ', $missing));
            }

            if ($unclassified->isNotEmpty()) {
                $this->command?->line('  - بقيت وصفية (غير مذكورة) : ' . $unclassified->implode('، '));
            }
        });
    }

    /**
     * «حريمي / رجالي / أطفال» answer WHO, the rest answer WHAT — one group was
     * being asked both. The audience becomes its own group so it can qualify a
     * priced line («ملابس × حريمي») instead of competing with one.
     */
    private function splitAudienceOutOfFashion(): int
    {
        $fashionId = DB::table('option_groups')->where('name_ar', self::FASHION_GROUP)->value('id');

        if (! $fashionId) {
            return 0;
        }

        $audienceId = DB::table('option_groups')->where('name_ar', self::AUDIENCE_GROUP['ar'])->value('id');

        if (! $audienceId) {
            $audienceId = DB::table('option_groups')->insertGetId([
                'name_ar' => self::AUDIENCE_GROUP['ar'],
                'name_en' => self::AUDIENCE_GROUP['en'],
                'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('options')
            ->where('group_id', $fashionId)
            ->whereIn('name_en', self::AUDIENCE)
            ->update(['group_id' => $audienceId, 'updated_at' => now()]);
    }

    /**
     * @return array{0:array<string,int>,1:array<int,string>,2:\Illuminate\Support\Collection}
     */
    private function applyRoles(): array
    {
        $roles = require database_path('seeders/data/option_price_roles.php');

        $applied = [];
        $missing = [];
        $named = collect();

        foreach ($roles as $role => $names) {
            $applied[$role] = 0;

            foreach ($names as $name) {
                $id = DB::table('option_groups')->where('name_ar', $name)->value('id');

                if (! $id) {
                    $missing[] = $name;

                    continue;
                }

                $named->push($name);

                DB::table('option_groups')->where('id', $id)->update([
                    'price_role' => $role,
                    'updated_at' => now(),
                ]);

                $applied[$role]++;
            }
        }

        // A group nobody classified is descriptive: it stays out of pricing.
        // Stated rather than assumed, so a re-run also CLEARS a stale role.
        DB::table('option_groups')
            ->whereNotIn('name_ar', $named->all())
            ->update(['price_role' => 'descriptive']);

        $unclassified = DB::table('option_groups')
            ->whereNotIn('name_ar', $named->all())
            ->whereExists(fn ($q) => $q->from('options')->whereColumn('options.group_id', 'option_groups.id'))
            ->pluck('name_ar');

        return [$applied, array_unique($missing), $unclassified];
    }
}
