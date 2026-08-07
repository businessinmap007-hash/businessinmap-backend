<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Order WITHIN a price-role tier.
 *
 * The tiers themselves are not stored — `OptionGroup::ROLE_RANK` sorts them,
 * so a customer always meets what is bought, then what changes its price, then
 * what only describes it. Inside a tier the sort falls to `option_groups
 * .reorder`, and those numbers were assigned when the groups were created, in
 * no particular order: «مرافق الإقامة» sat at 31 and «تصنيف الإقامة» at 32, so
 * the hotel's filter list offered ten facilities before it offered the star
 * rating.
 *
 * Only the groups NAMED here are touched, and they are renumbered into the
 * consecutive slots those same groups already occupy — so this can never push
 * an unlisted group out of place, and re-running it changes nothing.
 */
class OptionGroupDisplayOrderSeeder extends Seeder
{
    /**
     * Groups that must appear in this order relative to each other. Every list
     * must sit inside ONE role tier; ordering across tiers is the role's job
     * and cannot be overridden from here.
     *
     * @var array<int,array<int,string>>
     */
    private const ORDERED = [
        // A hotel customer narrows by class first, then by view, then by what
        // the place happens to have. «إطلالة الوحدة» is a modifier and sorts
        // above both by rank, so only these two are ordered here.
        ['تصنيف الإقامة', 'مرافق الإقامة'],
    ];

    public function run(): void
    {
        $moved = 0;

        foreach (self::ORDERED as $names) {
            $groups = OptionGroup::query()
                ->whereIn('name_ar', $names)
                ->get(['id', 'name_ar', 'price_role', 'reorder'])
                ->keyBy('name_ar');

            $present = collect($names)->filter(fn ($name) => $groups->has($name))->values();

            if ($present->count() < 2) {
                continue;
            }

            $roles = $present->map(fn ($name) => (string) $groups[$name]->price_role)->unique();

            if ($roles->count() > 1) {
                $this->command?->warn(
                    '  - تخطّي (أدوار مختلفة): ' . $present->implode('، ')
                    . ' — الترتيب بين الأدوار من اختصاص price_role لا reorder.'
                );

                continue;
            }

            // The slots these groups already hold, reused in order. Taking a
            // fresh range instead would step on whatever else lives there.
            $slots = $present->map(fn ($name) => (int) $groups[$name]->reorder)->sort()->values();

            foreach ($present as $i => $name) {
                $wanted = $slots[$i];

                if ((int) $groups[$name]->reorder === $wanted) {
                    continue;
                }

                DB::table('option_groups')
                    ->where('id', $groups[$name]->id)
                    ->update(['reorder' => $wanted, 'updated_at' => now()]);

                $moved++;
            }
        }

        $this->command?->info('Option group display order:');
        $this->command?->line('  - مجموعات أُعيد ترتيبها : ' . $moved);
    }
}
