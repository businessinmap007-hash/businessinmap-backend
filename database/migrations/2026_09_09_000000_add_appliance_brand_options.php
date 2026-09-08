<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "أنواع الأجهزة الكهربائية" (line — «ثلاجات», «تلفزيونات وشاشات»...) had no
 * closed vocabulary of its own for the brand a merchant carries a branch
 * in — the item form's brand field was free text on every menu item
 * regardless of specialty. This is the modifier half of that same catalog:
 * one option per real appliance brand sold in Egypt, on child 88 «أجهزة
 * كهربائية» (root 0 — shared across every root, same as «ماركات الموبيلات»,
 * group 593, which this mirrors).
 *
 * Idempotent: re-running finds the group/options/links already there and
 * changes nothing.
 */
return new class extends Migration
{
    private const GROUP_NAME_AR = 'ماركات الأجهزة الكهربائية';

    private const GROUP_NAME_EN = 'Appliance Brands';

    private const CHILD_ID = 88;

    /**
     * @var array<int,array{ar:string,en:string}>
     *
     * "Samsung" and "LG" are deliberately absent — both already exist as
     * options under «ماركات الموبيلات» (group 593), and `options.name_en`
     * is unique platform-wide (one option row, reusable across children via
     * category_child_option — not a name a second group may claim).
     * Reusing those rows here would file them under a "Mobile Brands"
     * heading for an appliance merchant; simpler to leave both out than to
     * rename an established group for one more child.
     */
    private const BRANDS = [
        ['ar' => 'كريازي', 'en' => 'Kiriazi'],
        ['ar' => 'فريش', 'en' => 'Fresh'],
        ['ar' => 'توشيبا', 'en' => 'Toshiba'],
        ['ar' => 'شارب', 'en' => 'Sharp'],
        ['ar' => 'بيكو', 'en' => 'Beko'],
        ['ar' => 'يونيفرسال', 'en' => 'Universal'],
        ['ar' => 'تورنيدو', 'en' => 'Tornado'],
        ['ar' => 'وايت ويل', 'en' => 'White Whale'],
        ['ar' => 'زانوسي', 'en' => 'Zanussi'],
        ['ar' => 'هاير', 'en' => 'Haier'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $groupId = DB::table('option_groups')->where('name_ar', self::GROUP_NAME_AR)->value('id');

            if (! $groupId) {
                $groupId = DB::table('option_groups')->insertGetId([
                    'name_ar' => self::GROUP_NAME_AR,
                    'name_en' => self::GROUP_NAME_EN,
                    'reorder' => 0,
                    'is_active' => 1,
                    'price_role' => 'modifier',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (self::BRANDS as $brand) {
                $optionId = DB::table('options')
                    ->where('group_id', $groupId)
                    ->where('name_ar', $brand['ar'])
                    ->value('id');

                if (! $optionId) {
                    $optionId = DB::table('options')->insertGetId([
                        'group_id' => $groupId,
                        'name_ar' => $brand['ar'],
                        'name_en' => $brand['en'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $linked = DB::table('category_child_option')
                    ->where('child_id', self::CHILD_ID)
                    ->where('category_id', 0)
                    ->where('option_id', $optionId)
                    ->exists();

                if (! $linked) {
                    DB::table('category_child_option')->insert([
                        'child_id' => self::CHILD_ID,
                        'category_id' => 0,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        $groupId = DB::table('option_groups')->where('name_ar', self::GROUP_NAME_AR)->value('id');

        if (! $groupId) {
            return;
        }

        $optionIds = DB::table('options')->where('group_id', $groupId)->pluck('id');

        DB::table('category_child_option')->where('child_id', self::CHILD_ID)->whereIn('option_id', $optionIds)->delete();
        DB::table('options')->whereIn('id', $optionIds)->delete();
        DB::table('option_groups')->where('id', $groupId)->delete();
    }
};
