<?php

namespace Tests\Feature;

use Database\Seeders\LegacyOptionGapsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards LegacyOptionGapsSeeder — the fix for the owner's correction that
 * بترول/كهرباء/غزل ونسيج are ENGINEERING CONSULTING FIELDS (options on the
 * «هندسية» specialty), not separately-priced products. A field describes
 * which kind of consultation an engineering office offers; it never gets its
 * own price — the item type «استشارة هندسية» does, exactly like «استشارة
 * قانونية» already does for a law office. Rolls back.
 */
class LegacyOptionGapsTest extends TestCase
{
    use DatabaseTransactions;

    private const ENGINEERING_CHILD_ID = 123;

    public function test_engineering_fields_are_options_linked_to_the_engineering_child(): void
    {
        foreach (['كهرباء', 'بترول', 'غزل ونسيج'] as $field) {
            $option = DB::table('options')->where('name_ar', $field)->first();
            $this->assertNotNull($option, "«{$field}» must exist as an option");

            $this->assertDatabaseHas('category_child_option', [
                'child_id' => self::ENGINEERING_CHILD_ID,
                'option_id' => $option->id,
            ]);
        }

        // بترول and غزل ونسيج have no pre-existing homonym — for these two
        // the price test bites for real: a field a merchant never prices
        // alone must not ALSO exist as a booking item type. («كهرباء» is
        // exempt on purpose — id 152 is a home-electrician REPAIR VISIT, a
        // different, legitimately-priced service that predates this fix and
        // happens to share the word.)
        foreach (['بترول', 'غزل ونسيج'] as $field) {
            $this->assertDatabaseMissing('platform_service_item_types', [
                'name_ar' => $field,
                'platform_service_id' => 1,
            ]);
        }
    }

    public function test_engineering_office_can_reach_booking_with_a_consultation_item_type(): void
    {
        $this->assertDatabaseHas('category_platform_services', [
            'child_id' => self::ENGINEERING_CHILD_ID,
            'platform_service_id' => 1,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('platform_service_item_types', [
            'key' => 'engineering_consultation',
            'platform_service_id' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_home_services_child_can_also_reach_booking(): void
    {
        $this->assertDatabaseHas('category_platform_services', [
            'child_id' => 144,
            'platform_service_id' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_new_tourism_item_types_sit_alongside_their_peers(): void
    {
        $groupId = DB::table('platform_service_item_groups')->where('name_ar', 'سياحة ورحلات')->value('id');
        $this->assertNotNull($groupId);

        foreach (['flight_booking', 'hotel_booking_service', 'domestic_tourism'] as $key) {
            $typeId = DB::table('platform_service_item_types')->where('key', $key)->value('id');
            $this->assertNotNull($typeId, "{$key} must exist");

            $this->assertDatabaseHas('platform_service_item_group_type', [
                'group_id' => $groupId,
                'item_type_id' => $typeId,
            ]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        (new LegacyOptionGapsSeeder)->run();

        $groups = DB::table('option_groups')->where('name_ar', 'تخصصات استشارية')->count();
        $options = DB::table('options')->where('group_id', function ($q) {
            $q->select('id')->from('option_groups')->where('name_ar', 'تخصصات استشارية');
        })->count();
        $types = DB::table('platform_service_item_types')->whereIn('key', [
            'engineering_consultation', 'flight_booking', 'hotel_booking_service', 'domestic_tourism', 'home_helper',
        ])->count();

        $before = [$groups, $options, $types];

        (new LegacyOptionGapsSeeder)->run();

        $groupsAfter = DB::table('option_groups')->where('name_ar', 'تخصصات استشارية')->count();
        $optionsAfter = DB::table('options')->where('group_id', function ($q) {
            $q->select('id')->from('option_groups')->where('name_ar', 'تخصصات استشارية');
        })->count();
        $typesAfter = DB::table('platform_service_item_types')->whereIn('key', [
            'engineering_consultation', 'flight_booking', 'hotel_booking_service', 'domestic_tourism', 'home_helper',
        ])->count();

        $this->assertSame($before, [$groupsAfter, $optionsAfter, $typesAfter], 're-running must never duplicate a group/option/item type');
    }
}
