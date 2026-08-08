<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «بيع أم إيجار» is the same question about a flat and about a car.
 *
 * Real estate already had the axis — group «نوع التعامل العقاري», a MODIFIER,
 * because شقة بيع and شقة إيجار are two prices for one property. A car showroom
 * asks exactly that and had no way to say it: its whole vocabulary was نوع
 * المركبة (line) × ماركة × حالة, so a showroom that also rents could price the
 * sale and nothing else. «تأجير سيارات» exists nowhere in the taxonomy either,
 * which is why renting had no home at all.
 *
 * So the group stops being about property. It is renamed «نوع التعامل» and the
 * three vehicle showrooms are given it — one axis, two verticals, rather than a
 * second group repeating بيع/إيجار in different words (owner call 2026-08-08).
 *
 * **«تبديل» is vehicles-only.** A group is shared but a CHILD's view of it is
 * not: `category_child_option` is the gate, so the four real-estate children
 * keep the pair they were given and never see the trade-in.
 *
 * What this does NOT do: renting a NAMED car on given dates. A modifier makes
 * the rental priceable and listable; reserving car #7 for Thursday still needs
 * `requires_bookable_item` and registered units, the way a hotel names room 101
 * — see BookableItem::displayLabel() and the unit-discovery endpoint.
 *
 * ⚠ The group is resolved BY NAME everywhere (option_groups has no key column),
 * so the rename had to land in `option_group_splits.php` and
 * `option_price_roles.php` in the same commit. Leaving either behind would have
 * had the next run create «نوع التعامل العقاري» a second time and move بيع
 * وشراء / إيجار into it, splitting the axis in two.
 *
 * Idempotent, and additive only: it never unlinks an option from a child.
 */
class VehicleDealTypeSeeder extends Seeder
{
    private const NAME_AR = 'نوع التعامل';

    private const NAME_EN = 'Deal Type';

    /** What it used to be called, so a re-run finds it either way. */
    private const FORMER_NAME_AR = 'نوع التعامل العقاري';

    /** بيع وشراء، إيجار — the pair real estate already answers. */
    private const SHARED_OPTIONS = [53, 302];

    /** The showrooms that sell a vehicle, not the shops that service one. */
    private const VEHICLE_CHILDREN = [
        188, // معرض سيارات
        53,  // سيارات
        189, // معرض موتوسيكلات
    ];

    public function run(): void
    {
        $groupId = (int) DB::table('option_groups')
            ->whereIn('name_ar', [self::NAME_AR, self::FORMER_NAME_AR])
            ->value('id');

        if ($groupId <= 0) {
            $this->command?->warn('The deal-type group is missing — nothing to do.');

            return;
        }

        DB::transaction(function () use ($groupId) {
            DB::table('option_groups')->where('id', $groupId)->update([
                'name_ar' => self::NAME_AR,
                'name_en' => self::NAME_EN,
                'price_role' => 'modifier',
                'updated_at' => now(),
            ]);

            $tradeIn = $this->tradeInOption($groupId);

            $linked = 0;

            foreach (self::VEHICLE_CHILDREN as $childId) {
                foreach (array_merge(self::SHARED_OPTIONS, [$tradeIn]) as $optionId) {
                    /*
                     * category_id = 0 means «under every root this child sits
                     * under». A showroom answers بيع/إيجار the same way whatever
                     * root it is reached through, so there is nothing to split.
                     */
                    $exists = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('option_id', $optionId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('category_child_option')->insert([
                        'child_id' => $childId,
                        'category_id' => 0,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);

                    $linked++;
                }
            }

            $this->command?->info(
                'Deal type: group renamed to «' . self::NAME_AR . "», تبديل #{$tradeIn}, {$linked} new child link(s)."
            );
        });
    }

    /** Created once, found forever after — matched inside its own group. */
    private function tradeInOption(int $groupId): int
    {
        $id = (int) DB::table('options')
            ->where('group_id', $groupId)
            ->where('name_ar', 'تبديل')
            ->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => 'تبديل',
            'name_en' => 'Trade-in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
