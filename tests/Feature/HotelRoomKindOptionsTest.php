<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «فندق» carried no line option at all: the room kind lived in the item type
 * (`single_room`, `suite`, `villa` …). Folding those into «حجز فندق» put six
 * priced rows on one key, and the unique key allows one row per line option —
 * so five collided and were left stranded rather than merged, because merging
 * destroys five prices.
 *
 * @see \Database\Seeders\HotelRoomKindOptionsSeeder
 */
class HotelRoomKindOptionsTest extends TestCase
{
    private const RETIRED = ['single_room', 'double_room', 'suite', 'family_room', 'villa', 'apartment'];

    private function childId(string $name): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');
    }

    /** @return array<int,string> */
    private function lineOptionsOf(int $childId): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->pluck('o.name_ar')
            ->all();
    }

    /**
     * A customer books a جناح and pays the جناح's price.
     *
     * The kinds lived in their own «فئات الغرف» until the owner merged them
     * into «الغرف» (2026-08-05), which already held استوديو/غرفة/غرفتين for
     * property listings — a hotel's جناح and a flat's ثلاث غرف answer the same
     * question. What this guards is unchanged by the move: whatever group holds
     * them must be a LINE, because it is the thing being paid for.
     */
    public function test_the_room_kinds_are_a_line_group(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'الغرف')->first();

        $this->assertNotNull($group, 'the «الغرف» group is missing');
        $this->assertSame(OptionGroup::ROLE_LINE, (string) $group->price_role);
        $this->assertSame(1, (int) $group->is_active);
    }

    /**
     * The full room vocabulary, restored 2026-08-05.
     *
     * The hotel branch originally held fifteen types and the collapse left six
     * reachable, so a hotel could not say «جناح ملكي» at all. What matters as
     * much as their presence is that they are SCOPED: handing every child the
     * whole list is the habit that once offered a gym a football pitch.
     */
    public function test_each_child_is_offered_only_the_rooms_it_lets(): void
    {
        $hotel = $this->lineOptionsOf($this->childId('فندق'));
        $hostel = $this->lineOptionsOf($this->childId('نُزل / هوستل'));
        $resort = $this->lineOptionsOf($this->childId('منتجع'));

        foreach (['جناح ملكي', 'جناح تنفيذي', 'جناح رئاسي', 'بنتهاوس', 'غرفة ديلوكس'] as $room) {
            $this->assertContains($room, $hotel, "a hotel must be able to sell «{$room}»");
        }

        // A hostel sells a bed, which it could not say before at all.
        $this->assertContains('سرير في غرفة مشتركة', $hostel);
        $this->assertNotContains('جناح ملكي', $hostel, 'a hostel has no royal suite');
        $this->assertNotContains('بنتهاوس', $hostel);

        // A resort's own stock; a plain hotel has neither.
        $this->assertContains('شاليه', $resort);
        $this->assertContains('بنجلو', $resort);
        $this->assertContains('ڤيلا', $resort, 'a resort without a villa is not a resort');
        $this->assertNotContains('بنجلو', $hotel, 'a city hotel does not let bungalows');

        // The twin private room is the commonest thing a hostel sells after
        // the bed; what the owner removed in August was its SUITE.
        $this->assertContains('غرفة توأم', $hostel);
        $this->assertNotContains('جناح', $hostel, 'a hostel has no suite');
    }

    /** Every child that lets a room by the night can now name which room. */
    public function test_every_hotel_child_can_name_a_room(): void
    {
        // «غرفة مزدوجة» is the one every one of them lets, so it is the shared
        // assertion. «جناح» is NOT — a hostel sells a bed or a plain private
        // room, and the owner curated the suite away from it on 2026-08-05.
        // Asserting a suite everywhere is the universal-list habit this file
        // exists to argue against.
        // «شقق فندقية» is deliberately absent: it sells units, not beds.
        foreach (['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة'] as $name) {
            $id = $this->childId($name);

            if (! $id) {
                continue;
            }

            $this->assertContains('غرفة مزدوجة', $this->lineOptionsOf($id), "«{$name}» cannot name a room at all");
        }

        foreach (['فندق', 'شقق فندقية', 'منتجع', 'بيت ضيافة'] as $name) {
            $id = $this->childId($name);

            if ($id) {
                $this->assertContains('جناح', $this->lineOptionsOf($id), "«{$name}» cannot say it sells a suite");
            }
        }
    }

    /**
     * «شقة» and «ڤيلا» already existed as line options under «عقارات وممتلكات».
     * A resort letting a whole villa sells the same thing an estate agent
     * lists, and one word for one thing is the point of the vocabulary.
     */
    public function test_the_shared_words_were_not_duplicated(): void
    {
        foreach (['شقة', 'ڤيلا'] as $name) {
            $this->assertSame(
                1,
                DB::table('options')->where('name_ar', $name)->count(),
                "«{$name}» was duplicated instead of reused"
            );
        }

        $this->assertContains('ڤيلا', $this->lineOptionsOf($this->childId('منتجع')));
        $this->assertContains('شقة', $this->lineOptionsOf($this->childId('شقق فندقية')));
    }

    /**
     * The international room-type standard: a hotel sells rooms and suites.
     * A whole apartment is the aparthotel classification and a whole villa is
     * the resort one — which is exactly why «شقق فندقية» and «منتجع» exist as
     * separate children rather than as notes on «فندق».
     */
    public function test_a_city_hotel_does_not_let_whole_homes(): void
    {
        $hotel = $this->lineOptionsOf($this->childId('فندق'));

        foreach (['شاليه', 'بنجلو'] as $name) {
            $this->assertNotContains($name, $hotel, "a city hotel does not let «{$name}»");
        }

        // شقة/ڤيلا are held on «فندق» only where a live business still prices
        // them — the seeder refuses to withdraw a word a merchant has sold in.
        foreach (['شقة', 'ڤيلا'] as $name) {
            if (! in_array($name, $hotel, true)) {
                continue;
            }

            $optionId = (int) DB::table('options')->where('name_ar', $name)->value('id');

            $this->assertTrue(
                DB::table('business_service_prices as p')
                    ->join('users as u', 'u.id', '=', 'p.business_id')
                    ->where('u.category_child_id', $this->childId('فندق'))
                    ->where('p.line_option_id', $optionId)
                    ->exists(),
                "«{$name}» is on «فندق» without a priced row to justify it"
            );
        }
    }

    /**
     * A serviced apartment is sold by UNIT SIZE, not by bed count. «شقق
     * فندقية» carried four bed-count entries — «غرفة فردية» among them — which
     * is the wrong axis for the whole classification.
     */
    public function test_the_aparthotel_sells_by_unit_size(): void
    {
        $apart = $this->lineOptionsOf($this->childId('شقق فندقية'));

        foreach (['استوديو', 'شقة', 'غرفة', 'غرفتين', 'ثلاث غرف'] as $name) {
            $this->assertContains($name, $apart, "an aparthotel must be able to sell «{$name}»");
        }

        foreach (['غرفة فردية', 'غرفة مزدوجة', 'غرفة توأم'] as $name) {
            $this->assertNotContains($name, $apart, "«{$name}» is a bed count, not a unit size");
        }
    }

    /** The repair's whole purpose: no price is left pointing at a dead key. */
    public function test_no_price_is_stranded_on_a_retired_room_key(): void
    {
        $stranded = DB::table('business_service_prices')
            ->whereIn('bookable_item_type', self::RETIRED)
            ->get(['id', 'business_id', 'bookable_item_type']);

        $this->assertCount(
            0,
            $stranded,
            'stranded: ' . $stranded->map(fn ($r) => "#{$r->id}/{$r->bookable_item_type}")->implode(', ')
        );
    }

    /**
     * The reason the seeder skips rather than merges. Six rows share one item
     * type now, so the line option is the only thing keeping them apart — and a
     * price is the one thing a merchant notices missing.
     */
    public function test_the_hotel_kept_every_price_and_they_are_told_apart(): void
    {
        $rows = BusinessServicePrice::query()
            ->where('business_id', 212)
            ->where('bookable_item_type', 'booking_stay')
            ->get();

        $this->assertGreaterThanOrEqual(6, $rows->count(), 'the hotel lost priced rows');

        $priced = $rows->where('line_option_id', '>', 0);

        $this->assertGreaterThanOrEqual(5, $priced->count(), 'the repaired rows lost their line option');

        $this->assertSame(
            $priced->count(),
            $priced->pluck('line_option_id')->unique()->count(),
            'two stay rows share a line option — the unique key would have rejected one'
        );

        foreach ($priced as $row) {
            $this->assertGreaterThan(0, (float) $row->price, 'a price was zeroed');
            $this->assertNotSame('', $row->offeringLabel(''), 'a repaired row cannot say which room it is');
        }
    }

    /** line_option_id is a mirror; only syncOfferingOptions may write it. */
    public function test_the_mirror_matches_the_offering_options(): void
    {
        foreach (
            BusinessServicePrice::query()->where('line_option_id', '>', 0)->get() as $row
        ) {
            $this->assertSame(
                (int) $row->line_option_id,
                (int) optional($row->lineOption())->id,
                "price #{$row->id}: line_option_id does not match its offering_options row"
            );
        }
    }

    /**
     * The landmine that has now bitten three times: a group absent from
     * option_price_roles.php is pushed back to descriptive on the next run.
     */
    public function test_the_group_survives_the_price_roles_seeder(): void
    {
        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $this->assertSame(
                OptionGroup::ROLE_LINE,
                (string) DB::table('option_groups')->where('name_ar', 'الغرف')->value('price_role')
            );
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running must not duplicate an option, a link or a repair. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('offering_options')->count(),
        ];

        $before = $count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\HotelRoomKindOptionsSeeder)->run();

            $this->assertSame($before, $count());
        } finally {
            DB::rollBack();
        }
    }
}
