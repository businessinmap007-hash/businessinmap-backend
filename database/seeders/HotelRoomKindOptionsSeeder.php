<?php

namespace Database\Seeders;

use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * What a hotel actually sells, and the repair that needed it.
 *
 *   php artisan db:seed --class=HotelRoomKindOptionsSeeder
 *
 * «فندق» carried NO line option at all — the room kind lived in the item type
 * (`single_room`, `suite`, `villa` …). When ServiceKindsCollapseSeeder folded
 * those into «حجز فندق», six priced rows landed on one key and
 * `bsp_business_child_service_item_line_unique` allows one row per
 * (business, child, service, kind, LINE OPTION). Five collided. That seeder
 * refused to merge them — merging destroys five prices — and left them on
 * their retired keys for this.
 *
 * The room kind is a LINE by the price test: a customer books a جناح, and the
 * price is the جناح's. «إطلالة بحرية» stays a modifier beside it.
 *
 * «شقة» and «ڤيلا» are NOT created here. They already exist as line options in
 * «عقارات وممتلكات», and a hotel letting a whole flat is selling the same thing
 * an estate agent lists — one word for one thing is the point of the whole
 * vocabulary. The merchant simply sees them under their own group.
 *
 * Owner-approved list, exactly six. Idempotent, and the repair only touches
 * rows still sitting on a retired room key.
 */
class HotelRoomKindOptionsSeeder extends Seeder
{
    /*
     * Was «فئات الغرف» until the owner merged the room kinds into the existing
     * «الغرف» (2026-08-05), which already held استوديو/غرفة/غرفتين for property
     * listings. Renaming it here was not cosmetic: this seeder resolves the
     * group by name and CREATES it when absent, so on the next run it would
     * have rebuilt «فئات الغرف» and pulled the six kinds back out of the merged
     * list — undoing the merge and splitting one vocabulary in two again.
     *
     * The merged group is the better home anyway, and for the reason the
     * docblock above already gives about «شقة» and «ڤيلا»: a hotel's جناح and a
     * flat's ثلاث غرف are the same kind of answer to the same question, and one
     * word for one thing is the point.
     */
    private const GROUP_AR = 'الغرف';

    private const GROUP_EN = 'Rooms';

    /** retired item-type key => [name_ar, name_en] */
    private const KINDS = [
        'single_room' => ['غرفة فردية', 'Single Room'],
        'double_room' => ['غرفة مزدوجة', 'Double Room'],
        'suite' => ['جناح', 'Suite'],
        'family_room' => ['غرفة عائلية', 'Family Room'],
        'villa' => ['ڤيلا', 'Villa'],
        'apartment' => ['شقة', 'Apartment'],
    ];

    /** The children that let a room by the night. */
    private const CHILDREN = ['فندق', 'شقق فندقية', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي'];

    public function run(): void
    {
        DB::transaction(function () {
            $children = DB::table('category_children_master')
                ->whereIn('name_ar', self::CHILDREN)
                ->pluck('name_ar', 'id');

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن فندقي — لم يُربط شيء.');

                return;
            }

            $groupId = $this->group();
            $created = $linked = 0;
            $optionOf = [];

            foreach (array_values(self::KINDS) as $i => [$ar, $en]) {
                $optionId = $this->option($ar, $en, $groupId, $created);
                $optionOf[array_keys(self::KINDS)[$i]] = $optionId;
                $linked += $this->link($optionId, $children, $i);
            }

            [$repaired, $stuck] = $this->repairStrandedPrices($optionOf);

            $this->command?->info('Hotel room kinds:');
            $this->command?->line("  - خيارات جديدة : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line("  - أسعار أُصلحت : {$repaired}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));

            if ($stuck !== []) {
                $this->command?->warn('  ! أسعار مكرّرة لنفس الفئة بقيت كما هي: ' . implode('، ', $stuck));
            }
        });
    }

    /**
     * Give every price still on a retired room key the option that says which
     * room it is, then move it onto «حجز فندق». The line is what makes the
     * unique key admit six rows where it admitted one.
     *
     * @param  array<string,int>  $optionOf
     * @return array{0:int,1:array<int,string>}
     */
    private function repairStrandedPrices(array $optionOf): array
    {
        $stayKey = 'booking_stay';
        $repaired = 0;
        $stuck = [];

        $rows = BusinessServicePrice::query()
            ->whereIn('bookable_item_type', array_keys(self::KINDS))
            ->get();

        foreach ($rows as $row) {
            $optionId = $optionOf[(string) $row->bookable_item_type] ?? null;

            if (! $optionId) {
                continue;
            }

            $taken = BusinessServicePrice::query()
                ->where('business_id', $row->business_id)
                ->where('child_id', $row->child_id)
                ->where('service_id', $row->service_id)
                ->where('bookable_item_type', $stayKey)
                ->where('line_option_id', $optionId)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($taken) {
                $stuck[] = (string) $row->bookable_item_type;
                continue;
            }

            // syncOfferingOptions writes offering_options AND mirrors
            // line_option_id — it is the only thing allowed to set that column.
            $row->syncOfferingOptions((int) $optionId);
            $row->forceFill(['bookable_item_type' => $stayKey])->save();

            $repaired++;
        }

        return [$repaired, array_unique($stuck)];
    }

    private function group(): int
    {
        $id = DB::table('option_groups')->where('name_ar', self::GROUP_AR)->value('id');

        if ($id) {
            DB::table('option_groups')->where('id', $id)
                ->update(['price_role' => OptionGroup::ROLE_LINE, 'is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => self::GROUP_AR,
            'name_en' => self::GROUP_EN,
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'price_role' => OptionGroup::ROLE_LINE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Matched on name_en then name_ar; a found option keeps its group. This is
     * how «شقة» resolves to the existing option in «عقارات وممتلكات» (whose
     * name_en is 'Flat', not 'Apartment') instead of being duplicated.
     */
    private function option(string $ar, string $en, int $groupId, int &$created): int
    {
        $id = DB::table('options')->where('name_en', $en)->value('id')
            ?: DB::table('options')->where('name_ar', $ar)->value('id');

        if ($id) {
            return (int) $id;
        }

        $created++;

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function link(int $optionId, $children, int $order): int
    {
        $rows = [];

        foreach ($children->keys() as $childId) {
            $rows[] = [
                'child_id' => (int) $childId,
                'category_id' => 0,   // shared: follows the child under every root
                'option_id' => $optionId,
                'reorder' => $order,
            ];
        }

        return DB::table('category_child_option')->insertOrIgnore($rows);
    }
}
