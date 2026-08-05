<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Three children could describe themselves with nothing at all.
 *
 *   php artisan db:seed --class=SalonAndPharmacyOptionsSeeder
 *
 * «كوافير حريمى»، «كوافير رجالي» and «صيدلية» carried ZERO option links, so a
 * customer searching them had no filter to narrow by and the businesses had no
 * way to say what they do — while their siblings under الصحة carry 8 to 82.
 * `CategoryChildOptionLinkingTest::test_no_live_child_was_left_without_a_single_option`
 * has been failing on exactly these three.
 *
 * The lists follow the axis rule (see the item-type-vs-option memory and
 * docs/architecture-blueprint.md §3.1): an option DESCRIBES the business and is
 * never priced on its own. A salon treatment sits on the same footing as a
 * medical specialty — «هذا الكوافير يقدّم صبغة وفرد» is the same kind of
 * statement as «هذا المستشفى فيه عظام»; the priced thing is the booking, not
 * the entry in the list.
 *
 * Idempotent: options are found by their globally-unique `name_en` and left in
 * whatever group they are already filed under, links are only added, and a
 * merchant's own answers are never touched.
 */
class SalonAndPharmacyOptionsSeeder extends Seeder
{
    /** group name_ar => [name_ar => name_en] */
    private const GROUPS = [
        'خدمات الكوافير والتجميل' => [
            'قص شعر' => 'Haircut',
            'صبغة وتلوين' => 'Hair colouring',
            'فرد وبروتين وكيراتين' => 'Hair straightening and keratin',
            'تسريحات ومناسبات' => 'Hair styling for occasions',
            'مكياج' => 'Make-up',
            'تجهيز عرائس' => 'Bridal package',
            'عناية بالبشرة' => 'Skin care',
            'حمام مغربي وسبا' => 'Moroccan bath and spa',
            'مانيكير وباديكير' => 'Manicure and pedicure',
            'إزالة شعر' => 'Hair removal',
            'تركيب وإطالة شعر' => 'Hair extensions',
            'حلاقة ذقن وتهذيب' => 'Beard shaving and trimming',
            'حلاقة أطفال' => 'Kids haircut',
            'عناية بالشعر للرجال' => 'Men hair treatment',
        ],
        /*
         * The pharmacy answers TWO questions, so it gets two groups — briefly
         * merged into one on 2026-08-05 and split again on the owner's call.
         *
         * «أقسام الصيدلية» is what the shop STOCKS: أدوية بشرية is a shelf, and
         * nobody buys «a shelf». Descriptive.
         *
         * «خدمات الصيدلية» is what the pharmacist DOES: a blood-pressure check
         * and an injection are charged for by the act. Line. The merge had made
         * all five descriptive, which quietly took away their ability to carry
         * a price at all — the price test is what separates the two lists, and
         * it separates them cleanly.
         */
        'أقسام الصيدلية' => [
            'أدوية بشرية' => 'Human medicines',
            'أدوية بيطرية' => 'Veterinary medicines',
            'مستحضرات تجميل' => 'Cosmetics',
            'مستلزمات طبية' => 'Medical supplies',
            'ألبان وأغذية أطفال' => 'Baby milk and food',
            'أعشاب ومكملات غذائية' => 'Herbs and supplements',
            'أجهزة قياس منزلية' => 'Home measuring devices',
            'العناية بالبشرة والشعر' => 'Skin and hair care products',
        ],
        'خدمات الصيدلية' => [
            'قياس ضغط' => 'Blood pressure measurement',
            'قياس سكر' => 'Blood sugar measurement',
            'حقن' => 'Injections',
            'استشارة دوائية' => 'Pharmacist consultation',
            'صرف روشتة تأمين' => 'Insurance prescription dispensing',
        ],
    ];

    /**
     * Options this seeder OWNS the placement of, rather than merely the
     * existence of.
     *
     * findOrCreate() deliberately leaves a found row in whatever group it
     * already sits in — a seeder says what must exist, not where an admin filed
     * it. That rule is right, and it is also why the five services stayed put
     * in «أقسام الصيدلية» after the split was declared above: they exist, so
     * nothing moved them. Listed here, they are moved, because the split IS the
     * owner's instruction and the group is what carries the price role.
     */
    private const OWNED_PLACEMENT = ['خدمات الصيدلية'];

    /**
     * child name_ar => [group name_ar => option name_ar list, or '*' for all].
     *
     * The salon list is one QUESTION («ما الذي يقدّمه هذا الكوافير؟») and stays
     * one group; each child only sees the slice it can answer — the same
     * per-child scoping the sports pools and child_option_scopes.php use.
     */
    private const CHILDREN = [
        'كوافير حريمى' => [
            'خدمات الكوافير والتجميل' => [
                'قص شعر', 'صبغة وتلوين', 'فرد وبروتين وكيراتين', 'تسريحات ومناسبات',
                'مكياج', 'تجهيز عرائس', 'عناية بالبشرة', 'حمام مغربي وسبا',
                'مانيكير وباديكير', 'إزالة شعر', 'تركيب وإطالة شعر', 'حلاقة أطفال',
            ],
            'الدفع والسداد' => ['كاش', 'دفع مسبق'],
            'ملاءمة المكان' => ['عائلي', 'ممنوع التدخين'],
            'مرافق ومعدات' => ['واي فاي'],
            'نمط تقديم الخدمة' => ['فردي', 'خاص', 'فريق عمل'],
        ],
        'كوافير رجالي' => [
            'خدمات الكوافير والتجميل' => [
                'قص شعر', 'صبغة وتلوين', 'حلاقة ذقن وتهذيب', 'حلاقة أطفال',
                'عناية بالشعر للرجال', 'عناية بالبشرة', 'حمام مغربي وسبا',
                'تركيب وإطالة شعر',
            ],
            'الدفع والسداد' => ['كاش', 'دفع مسبق'],
            'ملاءمة المكان' => ['ممنوع التدخين'],
            'مرافق ومعدات' => ['واي فاي'],
            'نمط تقديم الخدمة' => ['فردي', 'خاص', 'فريق عمل'],
        ],
        'صيدلية' => [
            'أقسام الصيدلية' => '*',
            'خدمات الصيدلية' => '*',
            'التسليم والاستلام' => ['توصيل طلبات', 'توصيل مجانى'],
            'الدفع والسداد' => ['كاش', 'دفع مسبق'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $created = 0;

            $rehomed = 0;

            foreach (self::GROUPS as $groupName => $options) {
                $groupId = $this->groupId($groupName);

                foreach ($options as $ar => $en) {
                    $before = DB::table('options')->count();
                    $this->optionId($ar, $en, $groupId);
                    $created += DB::table('options')->count() - $before;
                }

                $rehomed += $this->rehomeGroupless($groupId, array_keys($options));

                if (in_array($groupName, self::OWNED_PLACEMENT, true)) {
                    $rehomed += DB::table('options')
                        ->whereIn('name_ar', array_keys($options))
                        ->where(fn ($q) => $q->where('group_id', '!=', $groupId)->orWhereNull('group_id'))
                        ->update(['group_id' => $groupId]);
                }
            }

            $added = 0;
            $missing = [];

            foreach (self::CHILDREN as $childName => $groups) {
                $childId = DB::table('category_children_master')->where('name_ar', $childName)->value('id');

                if (! $childId) {
                    $missing[] = $childName;

                    continue;
                }

                foreach ($groups as $groupName => $wanted) {
                    $groupId = DB::table('option_groups')->where('name_ar', $groupName)->value('id');

                    if (! $groupId) {
                        $missing[] = "مجموعة «{$groupName}»";

                        continue;
                    }

                    $ids = DB::table('options')
                        ->where('group_id', $groupId)
                        ->when($wanted !== '*', fn ($q) => $q->whereIn('name_ar', (array) $wanted))
                        ->pluck('id');

                    // shared rows (category_id = 0): each of these children sits
                    // under exactly one root, so there is nothing to diverge yet
                    foreach ($ids as $optionId) {
                        $exists = DB::table('category_child_option')
                            ->where('child_id', $childId)
                            ->where('option_id', $optionId)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('category_child_option')->insert([
                            'child_id' => (int) $childId,
                            'category_id' => 0,
                            'option_id' => (int) $optionId,
                            'reorder' => 0,
                        ]);

                        $added++;
                    }
                }
            }

            $this->command?->info('Salon & pharmacy options:');
            $this->command?->line('  - مجموعات جديدة/موجودة : ' . count(self::GROUPS));
            $this->command?->line("  - خيارات أُنشئت : {$created}");
            $this->command?->line("  - خيارات بلا مجموعة أُعيدت : {$rehomed}");
            $this->command?->line("  - روابط أُضيفت : {$added}");

            foreach ($missing as $name) {
                $this->command?->warn("  ! غير موجود : {$name}");
            }
        });
    }

    private function groupId(string $nameAr): int
    {
        $id = DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $this->groupNameEn($nameAr),
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Re-home an option that ended up in no group at all.
     *
     * findOrCreate() deliberately leaves a found row wherever it already sits —
     * a seeder says what must exist, not where an admin filed it. That is right
     * until the row sits NOWHERE: «حقن» lost its group when «خدمات الصيدلية»
     * was merged away, and `options` has no is_active column, so a groupless
     * row is not hidden but unreachable — invisible in every picker and beyond
     * repair from any screen.
     */
    private function rehomeGroupless(int $groupId, array $names): int
    {
        return DB::table('options')
            ->whereNull('group_id')
            ->whereIn('name_ar', $names)
            ->update(['group_id' => $groupId]);
    }

    private function groupNameEn(string $nameAr): string
    {
        return match ($nameAr) {
            'خدمات الكوافير والتجميل' => 'Salon and beauty treatments',
            'أقسام الصيدلية' => 'Pharmacy departments',
            'خدمات الصيدلية' => 'Pharmacy services',
            default => $nameAr,
        };
    }

    /**
     * Find by globally-unique name_en, then by name_ar, and LEAVE a found row in
     * whatever group it already sits in — a seeder says what must exist, not
     * where it must be filed. Re-filing here is how 45 sports nearly got
     * duplicated once.
     */
    private function optionId(string $ar, string $en, int $groupId): int
    {
        $id = DB::table('options')->where('name_en', $en)->value('id')
            ?: DB::table('options')->where('name_ar', $ar)->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
