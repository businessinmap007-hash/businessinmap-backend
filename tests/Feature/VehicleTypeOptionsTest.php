<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A showroom's heading was «مركبة معروضة» — the platform item type, mirrored
 * into an option because the child had nothing better. It says only that the
 * thing is a vehicle. «سيدان — BMW» says what a customer came looking for.
 *
 * @see \Database\Seeders\VehicleTypeOptionsSeeder
 */
class VehicleTypeOptionsTest extends TestCase
{
    private const APPROVED = ['سيدان', 'SUV', 'بيك أب'];

    private function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', 'نوع المركبة')->value('id');
    }

    private function childId(string $name): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');
    }

    /** @return array<int,string> */
    private function optionsOf(int $childId, string $group): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.name_ar', $group)
            ->pluck('o.name_ar')
            ->all();
    }

    /** A body type is the thing bought, so the group carries the price. */
    public function test_the_vehicle_type_is_a_line_group(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'نوع المركبة')->first();

        $this->assertNotNull($group, 'the «نوع المركبة» group is missing');
        $this->assertSame(OptionGroup::ROLE_LINE, (string) $group->price_role);
        $this->assertSame(1, (int) $group->is_active);
    }

    /** Exactly the three the owner approved — no more, no fewer. */
    public function test_only_the_approved_types_exist(): void
    {
        $options = DB::table('options')->where('group_id', $this->groupId())->pluck('name_ar')->all();

        $this->assertEqualsCanonicalizing(self::APPROVED, $options);
    }

    /** Every child that sells the vehicle itself can name which one. */
    public function test_the_vehicle_sellers_carry_them(): void
    {
        foreach (['سيارات', 'معرض سيارات', 'خدمة ليموزين', 'نقل ركاب'] as $name) {
            $id = $this->childId($name);

            $this->assertGreaterThan(0, $id, "«{$name}» is missing");
            $this->assertEqualsCanonicalizing(self::APPROVED, $this->optionsOf($id, 'نوع المركبة'));
        }
    }

    /**
     * The hole this closed: «مركبات النقل والركاب» reaches both transport
     * children with big vehicles only — كوتش، ميكروباص، ميني ڤان، باص ٥٠ — so
     * a customer asking for a sedan with a driver, the commonest request
     * either of them gets, could not be answered at all.
     */
    public function test_a_transport_service_can_now_offer_a_car(): void
    {
        foreach (['خدمة ليموزين', 'نقل ركاب'] as $name) {
            $id = $this->childId($name);

            if (! $id) {
                continue;
            }

            $fleet = $this->optionsOf($id, 'مركبات النقل والركاب');

            $this->assertNotEmpty($fleet, "the fleet group no longer reaches «{$name}»");
            $this->assertNotContains('سيدان', $fleet, 'the fleet group was not the place for a body type');

            // Complementary, not competing: the fleet keeps the big vehicles,
            // this group adds the cars, and both are lines he may price.
            $this->assertContains('سيدان', $this->optionsOf($id, 'نوع المركبة'), "«{$name}» still cannot offer a sedan");

            // And the modifier it already had turns that into a whole offering.
            $this->assertContains('سيارة بسائق', $this->optionsOf($id, 'نمط تقديم الخدمة'));
        }
    }

    /** A workshop fits and services vehicles; it never sells one. */
    public function test_a_workshop_is_not_offered_a_body_type(): void
    {
        foreach (['كهربائي سيارات', 'مغسلة سيارات', 'قطع غيار سيارات'] as $name) {
            $id = $this->childId($name);

            if (! $id) {
                continue;
            }

            $this->assertEmpty(
                $this->optionsOf($id, 'نوع المركبة'),
                "«{$name}» was given a body type it has nothing to do with"
            );
        }
    }

    /**
     * The showroom must not end up with two headings, one of them saying less.
     * MenuLineOptionsSeeder drops its mirrored bands once a child has a
     * vocabulary of its own.
     */
    public function test_the_flat_item_type_band_is_gone(): void
    {
        foreach (['سيارات', 'معرض سيارات'] as $name) {
            $this->assertEmpty(
                $this->optionsOf($this->childId($name), 'بنود المنيو'),
                "«{$name}» still carries the mirrored item-type band beside its own types"
            );
        }
    }

    /** Re-running the menu seeder must not put the flat band back. */
    public function test_the_menu_seeder_does_not_restore_the_flat_band(): void
    {
        DB::beginTransaction();

        try {
            (new \Database\Seeders\MenuLineOptionsSeeder)->run();

            foreach (['سيارات', 'معرض سيارات'] as $name) {
                $this->assertEmpty(
                    $this->optionsOf($this->childId($name), 'بنود المنيو'),
                    "the menu seeder gave «{$name}» its flat band back"
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    /** A body type is not a motorcycle's heading — its brands are. */
    public function test_a_motorcycle_showroom_is_not_offered_a_body_type(): void
    {
        $id = $this->childId('معرض موتوسيكلات');

        if (! $id) {
            $this->markTestSkipped('No «معرض موتوسيكلات» child.');
        }

        $this->assertEmpty($this->optionsOf($id, 'نوع المركبة'));
    }

    /** The brand qualifies the type; it is never the thing bought on its own. */
    public function test_the_brands_stay_modifiers(): void
    {
        $this->assertSame(
            OptionGroup::ROLE_MODIFIER,
            (string) DB::table('option_groups')->where('name_ar', 'ماركات السيارات')->value('price_role')
        );
    }

    /**
     * The landmine that has now bitten twice: a group absent from
     * option_price_roles.php is pushed back to descriptive on the next run, and
     * every heading built on it silently stops being one.
     */
    public function test_the_group_survives_the_price_roles_seeder(): void
    {
        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $this->assertSame(
                OptionGroup::ROLE_LINE,
                (string) DB::table('option_groups')->where('name_ar', 'نوع المركبة')->value('price_role'),
                'the price-roles seeder demoted the vehicle types'
            );
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running must not duplicate an option or a link. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
        ];

        $before = $count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\VehicleTypeOptionsSeeder)->run();

            $this->assertSame($before, $count());
        } finally {
            DB::rollBack();
        }
    }
}
