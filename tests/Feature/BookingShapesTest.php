<?php

namespace Tests\Feature;

use App\Models\BookingShape;
use App\Models\User;
use App\Services\BookingShapes;
use Database\Seeders\BookingShapesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «اضف للباك اند اشكال الحجز ونقوم بترتيب كل اعداداته ثم ننتقل للكود» — المالك، 2026-10-07.
 *
 * Rolls back.
 */
class BookingShapesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BookingShapesSeeder::class);
    }

    public function test_every_booking_trade_stands_on_a_shape_and_a_hotel_on_the_hotel_one(): void
    {
        $this->assertSame(['hotel_rooms', 'furnished_units', 'hourly_venue', 'table', 'appointment', 'course', 'clinic', 'hospital', 'radiology', 'lab'], BookingShape::query()->orderBy('sort_order')->pluck('code')->all());

        $hotel = app(BookingShapes::class)->forChild(536);
        $this->assertSame('hotel_rooms', $hotel->code);
        $this->assertSame('stay', $hotel->pattern);

        // a flat or a chalet is a stay, but not rooms by the night
        $this->assertSame('furnished_units', app(BookingShapes::class)->forChild(537)->code);

        // the medical trades each have the shape the canvas drew for them
        $this->assertSame('clinic', app(BookingShapes::class)->forChild(514)->code);
        $this->assertSame('hospital', app(BookingShapes::class)->forChild(513)->code);
        $this->assertSame('radiology', app(BookingShapes::class)->forChild(252)->code);
        $this->assertSame('lab', app(BookingShapes::class)->forChild(163)->code);

        // a trade nobody booked has none — the app draws what it always drew
        $this->assertNull(app(BookingShapes::class)->forChild(999999));
    }

    public function test_the_hotel_shape_says_what_the_owner_asked_for(): void
    {
        $s = BookingShape::where('code', 'hotel_rooms')->firstOrFail()->resolvedSettings();

        $this->assertSame('sections', $s['layout'], 'a room kind is a section with its rooms under it');
        $this->assertTrue($s['show_photos']);
        $this->assertTrue($s['price_includes_features'], '206 shows 950, not 800');
        $this->assertSame('unit_then_dates', $s['pick_order'], 'the room, then the meal plan, then the days');
        $this->assertTrue($s['offer_day_use']);
        $this->assertTrue($s['in_stay_requests']);
    }

    public function test_seeding_again_changes_nothing_the_admin_curated(): void
    {
        $shape = BookingShape::where('code', 'hotel_rooms')->firstOrFail();
        $shape->update(['settings' => BookingShape::sanitize(['layout' => 'flat'])]);
        DB::table('booking_shape_children')->where('child_id', 536)->update(['booking_shape_id' => BookingShape::where('code', 'furnished_units')->value('id')]);

        $this->seed(BookingShapesSeeder::class);

        $this->assertSame('flat', $shape->fresh()->resolvedSettings()['layout']);
        $this->assertSame('furnished_units', app(BookingShapes::class)->forChild(536)->code, 'a trade already on a shape is never moved');
    }

    public function test_the_medical_shapes_say_what_the_canvas_drew(): void
    {
        $lab = BookingShape::where('code', 'lab')->firstOrFail()->resolvedSettings();
        $this->assertTrue($lab['multi_select']);
        $this->assertTrue($lab['ask_attachment']);
        $this->assertTrue($lab['offer_home_service']);

        $hospital = BookingShape::where('code', 'hospital')->firstOrFail()->resolvedSettings();
        $this->assertSame('sections', $hospital['layout'], 'departments, then the doctors under each');
        $this->assertTrue($hospital['pick_provider']);

        $this->assertFalse(BookingShape::where('code', 'hotel_rooms')->firstOrFail()->resolvedSettings()['multi_select']);
    }

    public function test_settings_are_a_closed_list(): void
    {
        $clean = BookingShape::sanitize(['layout' => 'nonsense', 'show_photos' => '0', 'pick_order' => 'dates_then_unit', 'evil' => 'x', 'offer_day_use' => 'on']);

        $this->assertSame(BookingShape::LAYOUT_SECTIONS, $clean['layout'], 'an unknown value falls back to the default');
        $this->assertFalse($clean['show_photos']);
        $this->assertTrue($clean['offer_day_use']);
        $this->assertSame('dates_then_unit', $clean['pick_order']);
        $this->assertArrayNotHasKey('evil', $clean);
        $this->assertSame(array_keys(BookingShape::SETTINGS), array_keys($clean));
    }

    public function test_the_admin_page_opens_saves_a_shape_and_moves_a_trade(): void
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN ?? 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('Needs an admin account.');
        }

        $this->actingAs($admin);
        $shape = BookingShape::where('code', 'hotel_rooms')->firstOrFail();

        $this->get(route('admin.booking-shapes.index', ['shape' => 'hotel_rooms'], false))->assertOk()->assertSee('أشكال الحجز');

        $this->post(route('admin.booking-shapes.settings', $shape, false), [
            'name_ar' => 'فندق — غرف', 'settings' => ['layout' => 'flat', 'show_photos' => '1'],
        ])->assertRedirect();

        $fresh = $shape->fresh();
        $this->assertSame('flat', $fresh->resolvedSettings()['layout']);
        $this->assertTrue($fresh->resolvedSettings()['show_photos']);
        $this->assertFalse($fresh->resolvedSettings()['show_price'], 'an unticked box is off');

        $flats = BookingShape::where('code', 'furnished_units')->firstOrFail();
        $this->post(route('admin.booking-shapes.assign', $flats, false), ['child_ids' => [536, 999999]])->assertRedirect();
        $this->assertSame('furnished_units', app(BookingShapes::class)->forChild(536)->code);
        $this->assertNull(app(BookingShapes::class)->forChild(999999), 'an unknown trade is ignored');

        $this->delete(route('admin.booking-shapes.unassign', 536, false))->assertRedirect();
        $this->assertNull(app(BookingShapes::class)->forChild(536));
    }

    public function test_discovery_tells_the_app_how_the_page_is_drawn(): void
    {
        $hotel = User::query()->where('type', User::TYPE_BUSINESS)->where('category_child_id', 536)->first();
        $guest = User::query()->where('type', User::TYPE_CLIENT)->first();

        if (! $hotel || ! $guest) {
            $this->markTestSkipped('Needs a hotel and a client.');
        }

        $data = $this->actingAs($guest, 'sanctum')->getJson("/api/v2/discovery/units/{$hotel->id}")->assertOk()->json('data');

        $this->assertSame('hotel_rooms', $data['shape']['code']);
        $this->assertSame('sections', $data['shape']['settings']['layout']);
        $this->assertTrue($data['shape']['settings']['price_includes_features']);
    }
}
