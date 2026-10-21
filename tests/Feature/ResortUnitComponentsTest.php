<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What a resort like «تيوليب الجلالة» sells and the hotel vocabulary could not say: the views of its mountain and its side sea,
 * the number of bedrooms of a chalet, the bed set-up, a kitchen, a balcony, a private pool, a beach, a kids pool. They are scoped
 * per child, priced where they change a price, and they survive the two seeders that curate this root.
 */
class ResortUnitComponentsTest extends TestCase
{
    use DatabaseTransactions;

    private function childOptions(string $child): array
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', $child)->value('id');

        return DB::table('category_child_option as l')->join('options as o', 'o.id', '=', 'l.option_id')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', $childId)->get(['g.name_ar as g', 'o.name_ar as o'])->groupBy('g')->map(fn ($rows) => $rows->pluck('o')->all())->all();
    }

    public function test_the_resort_can_say_what_its_units_are(): void
    {
        $resort = $this->childOptions('منتجع');

        $this->assertContains('إطلالة جبلية', $resort['إطلالة الوحدة']);
        $this->assertContains('إطلالة جانبية على البحر', $resort['إطلالة الوحدة']);
        $this->assertContains('ثلاث غرف نوم', $resort['عدد غرف النوم']);
        $this->assertContains('سرير كينج', $resort['تجهيز الأسرّة']);

        foreach (['مطبخ مجهز', 'شرفة أو تراس', 'حمام سباحة خاص', 'شاطئ خاص', 'مسبح للأطفال', 'نادي أطفال'] as $feature) {
            $this->assertContains($feature, $resort['مكونات الوحدة والمنتجع']);
        }

        // and still sells the kinds the unit is: a chalet, a villa, a suite, a deluxe room, an apartment
        foreach (['شاليه', 'جناح', 'غرفة ديلوكس', 'غرفة قياسية'] as $kind) {
            $this->assertContains($kind, $resort['الغرف']);
        }
    }

    public function test_a_hostel_gets_none_of_it_and_a_hotel_gets_only_what_a_hotel_has(): void
    {
        $hostel = $this->childOptions('نُزل / هوستل');
        $this->assertArrayNotHasKey('عدد غرف النوم', $hostel);
        $this->assertArrayNotHasKey('مكونات الوحدة والمنتجع', $hostel);
        $this->assertNotContains('إطلالة جبلية', $hostel['إطلالة الوحدة'] ?? []);

        $hotel = $this->childOptions('فندق');
        $this->assertContains('سريران منفصلان', $hotel['تجهيز الأسرّة']);
        $this->assertNotContains('مطبخ مجهز', $hotel['مكونات الوحدة والمنتجع'], 'a kitchen is a chalet\'s, not a hotel room\'s');
        $this->assertArrayNotHasKey('عدد غرف النوم', $hotel);
    }

    public function test_the_bedroom_count_and_the_bed_set_up_price_like_modifiers_and_survive_the_roles_seeder(): void
    {
        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $role = fn (string $g) => (string) DB::table('option_groups')->where('name_ar', $g)->value('price_role');

            $this->assertSame('modifier', $role('عدد غرف النوم'));
            $this->assertSame('modifier', $role('تجهيز الأسرّة'));
            $this->assertSame('descriptive', $role('مكونات الوحدة والمنتجع'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_room_kind_seeder_does_not_take_a_bedroom_count_for_a_room_kind(): void
    {
        $before = $this->childOptions('منتجع')['عدد غرف النوم'] ?? [];

        DB::beginTransaction();

        try {
            (new \Database\Seeders\HotelRoomKindOptionsSeeder)->run();

            $this->assertSame($before, $this->childOptions('منتجع')['عدد غرف النوم'] ?? []);
        } finally {
            DB::rollBack();
        }
    }
}
