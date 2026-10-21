<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * What a resort like «تيوليب الجلالة» sells that the hotel vocabulary could not say.
 *
 *     php artisan db:seed --class=ResortUnitComponentsSeeder
 *
 * Owner, 2026-10-21: «راجع فندق تيوليب الجلالة وأضف ما ينقصنا من مكونات الغرف والأجنحة والشقق والشاليهات». The resort lists (from
 * its booking pages): a standard / classic room (twin or king), a deluxe room, a suite with a bedroom and a living room with a
 * sofa bed, a three-bedroom chalet with a fully equipped kitchen, villas, apartments — on the sea or on the mountain, some of
 * them for Egyptians and residents only, five outdoor pools and a children's pool.
 *
 * The KINDS were all there (قياسية، ديلوكس، جناح، شاليه، ڤيلا، شقة…). What was missing is what describes a unit:
 *
 *   إطلالة الوحدة (existing, modifier)  + إطلالة جبلية, إطلالة جانبية على البحر
 *   عدد غرف النوم (new, modifier)       a three-bedroom chalet costs more than a two-bedroom one, and says so
 *   تجهيز الأسرّة (new, modifier)       «١ كينج أو ٢ مفردين» — the guest's choice, at no charge unless the hotel names one
 *   مكونات الوحدة والمنتجع (new, desc.) مطبخ مجهز، شرفة أو تراس، حمام سباحة خاص، شاطئ خاص، مسبح للأطفال، نادي أطفال
 *
 * Scoped per child, the way the room list is: a hostel gets no kitchen and no private pool.
 *
 * ⚠ The two priced groups are registered in `data/option_price_roles.php`; a group missing there is reset to `descriptive` on
 * the next run of the roles seeder. The base-group restore (`hospitality_option_restore.php`) no longer grants «إطلالة الوحدة»
 * whole — it names the two views every sibling has — so a view added here is not handed to the guest house and the boat.
 *
 * Idempotent; nothing is deleted. Ends by renumbering the display order (DisplayOrderSeeder) so the new groups sit alphabetically.
 */
class ResortUnitComponentsSeeder extends Seeder
{
    private const VIEW_GROUP = 'إطلالة الوحدة';

    private const VIEWS = ['إطلالة جبلية' => 'Mountain View', 'إطلالة جانبية على البحر' => 'Side Sea View'];

    /*
     * English names deliberately NOT «One Bedroom / Two Bedrooms…»: HotelRoomKindOptionsSeeder resolves the property sizes
     * (غرفة، غرفتين، ثلاث غرف…) by name_en first, so a bedroom-count option carrying the same English name was taken for a room
     * KIND and pruned from the resort on the next run.
     */
    private const BEDROOMS = [
        'غرفة نوم واحدة' => 'Unit With 1 Bedroom', 'غرفتا نوم' => 'Unit With 2 Bedrooms', 'ثلاث غرف نوم' => 'Unit With 3 Bedrooms',
        'أربع غرف نوم' => 'Unit With 4 Bedrooms', 'خمس غرف نوم فأكثر' => 'Unit With 5+ Bedrooms',
    ];

    private const BEDS = ['سرير كينج' => 'King Bed', 'سريران منفصلان' => 'Twin Beds', 'أريكة سرير في الصالة' => 'Living Room Sofa Bed'];

    private const COMPONENTS = [
        'مطبخ مجهز' => 'Equipped Kitchen',
        'شرفة أو تراس' => 'Balcony or Terrace',
        'حمام سباحة خاص' => 'Private Pool',
        'شاطئ خاص' => 'Private Beach',
        'مسبح للأطفال' => 'Kids Pool',
        'نادي أطفال' => 'Kids Club',
    ];

    /** Which child carries what. Children by name; the root is «فنادق سياحية», so every link is shared (category_id 0). */
    private const SCOPE = [
        'فندق' => [
            self::VIEW_GROUP => ['إطلالة جبلية', 'إطلالة جانبية على البحر'],
            'تجهيز الأسرّة' => ['سرير كينج', 'سريران منفصلان', 'أريكة سرير في الصالة'],
            'مكونات الوحدة والمنتجع' => ['شرفة أو تراس', 'شاطئ خاص', 'مسبح للأطفال', 'نادي أطفال'],
        ],
        'شقق فندقية' => [
            self::VIEW_GROUP => ['إطلالة جبلية', 'إطلالة جانبية على البحر'],
            'عدد غرف النوم' => ['غرفة نوم واحدة', 'غرفتا نوم', 'ثلاث غرف نوم', 'أربع غرف نوم', 'خمس غرف نوم فأكثر'],
            'تجهيز الأسرّة' => ['سرير كينج', 'سريران منفصلان', 'أريكة سرير في الصالة'],
            'مكونات الوحدة والمنتجع' => ['مطبخ مجهز', 'شرفة أو تراس'],
        ],
        'منتجع' => [
            self::VIEW_GROUP => ['إطلالة جبلية', 'إطلالة جانبية على البحر'],
            'عدد غرف النوم' => ['غرفة نوم واحدة', 'غرفتا نوم', 'ثلاث غرف نوم', 'أربع غرف نوم', 'خمس غرف نوم فأكثر'],
            'تجهيز الأسرّة' => ['سرير كينج', 'سريران منفصلان', 'أريكة سرير في الصالة'],
            'مكونات الوحدة والمنتجع' => ['مطبخ مجهز', 'شرفة أو تراس', 'حمام سباحة خاص', 'شاطئ خاص', 'مسبح للأطفال', 'نادي أطفال'],
        ],
        'بيت ضيافة' => [
            self::VIEW_GROUP => ['إطلالة جبلية'],
            'تجهيز الأسرّة' => ['سرير كينج', 'سريران منفصلان'],
            'مكونات الوحدة والمنتجع' => ['شرفة أو تراس'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->group(self::VIEW_GROUP, 'Unit View', null, self::VIEWS);
            $this->group('عدد غرف النوم', 'Bedrooms', 'modifier', self::BEDROOMS);
            $this->group('تجهيز الأسرّة', 'Bed Setup', 'modifier', self::BEDS);
            $this->group('مكونات الوحدة والمنتجع', 'Unit & Resort Features', 'descriptive', self::COMPONENTS);

            $linked = 0;

            foreach (self::SCOPE as $childName => $groups) {
                $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');

                if ($childId <= 0) {
                    continue;
                }

                foreach ($groups as $groupName => $names) {
                    foreach ($names as $name) {
                        $linked += $this->link($childId, $groupName, $name);
                    }
                }
            }

            $this->command?->info("Resort unit components: {$linked} links written.");
        });

        // the groups were appended; the screens (and the display-order guard) read them alphabetically inside their tier
        (new DisplayOrderSeeder)->run();
    }

    /** @param array<string,string> $options name_ar => name_en */
    private function group(string $nameAr, string $nameEn, ?string $role, array $options): void
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $nameAr, 'name_en' => $nameEn, 'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // written here so a standalone run gives a working screen, and declared in option_price_roles.php so the next run of the
        // roles seeder does not reset it
        if ($role !== null) {
            DB::table('option_groups')->where('id', $groupId)->update(['price_role' => $role, 'updated_at' => now()]);
        }

        foreach ($options as $ar => $en) {
            if (DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->exists()) {
                continue;
            }

            // `options.name_en` collides across groups; keep it unique rather than reuse another group's row
            if (DB::table('options')->where('name_en', $en)->exists()) {
                $en .= ' (Stay)';
            }

            DB::table('options')->insert(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function link(int $childId, string $groupName, string $optionName): int
    {
        $optionId = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)->where('o.name_ar', $optionName)->value('o.id');

        if ($optionId <= 0 || DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $optionId)->exists()) {
            return 0;
        }

        DB::table('category_child_option')->insert(['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0]);

        return 1;
    }
}
