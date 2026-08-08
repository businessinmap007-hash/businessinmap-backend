<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «فى السيارات المعرض لديه بيع وشراء وايجار — فهل عند فتح خدمات المعرض استطيع
 * اختيار SUV — BMW — واختار منهم للايجار او الشراء» — owner, 2026-08-08.
 *
 * A hotel could already do this: /discovery/units/{business} lists the rooms by
 * kind and the customer taps one. A showroom could not. Selling a car is a menu
 * listing and renting one is a booking row, so opening the showroom meant
 * meeting its SERVICES, and the cars never appeared as a list to choose from.
 *
 * The fix is not another service or another item type — «تبسيط استخدام الخدمات
 * بناء على الخيارات وليس تفاصيل للخدمات اكتر». Both surfaces already say what
 * they sell through `offering_options`, so one endpoint reads them together and
 * returns the axes they actually use.
 */
class BusinessOfferingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $bookingId;

    private array $opt = [];

    private User $showroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        if ($this->bookingId <= 0) {
            $this->markTestSkipped('The booking service is not active.');
        }

        $this->opt = [
            'suv' => $this->option('نوع المركبة', 'SUV'),
            'sedan' => $this->option('نوع المركبة', 'سيدان'),
            'bmw' => $this->option('ماركات السيارات', 'BMW'),
            'mercedes' => $this->option('ماركات السيارات', 'مرسيدس'),
            'rent' => $this->option('نوع التعامل', 'إيجار'),
            'sale' => $this->option('نوع التعامل', 'بيع وشراء'),
        ];

        foreach ($this->opt as $name => $id) {
            if ($id <= 0) {
                $this->markTestSkipped("The «{$name}» option is missing from the taxonomy.");
            }
        }

        $this->showroom = $this->makeShowroom();
    }

    /** The owner's question, answered end to end. */
    public function test_a_showroom_is_browsed_by_its_own_option_axes(): void
    {
        $body = $this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar")
            ->assertOk()
            ->json('data');

        $axes = collect($body['axes'])->keyBy('name');

        $this->assertTrue($axes->has('نوع المركبة'), 'the vehicle kind is not offered as an axis');
        $this->assertTrue($axes->has('ماركات السيارات'), 'the make is not offered as an axis');
        $this->assertTrue($axes->has('نوع التعامل'), 'buying and renting are not offered as an axis');

        // The axis the customer buys ALONG comes first; what qualifies it follows.
        $this->assertSame('line', $body['axes'][0]['role']);

        $deal = collect($axes['نوع التعامل']['options'])->keyBy('name');
        $this->assertSame(1, $deal['إيجار']['offerings']);
        $this->assertSame(2, $deal['بيع وشراء']['offerings']);
    }

    /** SUV → BMW → إيجار, three taps, and the row that comes back is the rental. */
    public function test_choosing_along_the_axes_reaches_one_offering(): void
    {
        $body = $this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar&" . http_build_query([
            'option_ids' => [$this->opt['suv'], $this->opt['bmw'], $this->opt['rent']],
        ]))->assertOk()->json('data');

        $rows = $body['offerings']['data'];

        $this->assertCount(1, $rows);
        $this->assertEquals(900, $rows[0]['price']);
        $this->assertStringContainsString('SUV', $rows[0]['label']);
        $this->assertStringContainsString('إيجار', $rows[0]['label']);
    }

    /**
     * Selling and renting arrive in ONE list. They live on different services
     * and different tables, and the customer should never learn that.
     */
    public function test_the_sale_and_the_rental_are_one_list(): void
    {
        $rows = collect($this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar")
            ->assertOk()->json('data.offerings.data'));

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(['menu', 'price', 'price'], $rows->pluck('source')->all());

        // And each says what to do with it, because a modifier routes nothing:
        // «إيجار» is a word on a row and the SERVICE is what picks the screen.
        $rental = $rows->firstWhere('price', 900);
        $this->assertSame('book', $rental['action']);
        $this->assertSame('order', $rows->firstWhere('source', 'menu')['action']);
    }

    /** A rented car is a NAMED car — the engine refuses without one. */
    public function test_a_bookable_row_carries_the_units_to_tap(): void
    {
        $rental = collect($this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar")
            ->assertOk()->json('data.offerings.data'))->firstWhere('action', 'book');

        $this->assertNotNull($rental);
        $this->assertCount(1, $rental['units']);
        $this->assertSame('TEST-SUV-1', $rental['units'][0]['code']);
    }

    /**
     * Within one axis the options are alternatives. Requiring every id outright
     * answered «BMW or مرسيدس» with nothing, so two makes could never be
     * compared — and comparing is the whole point of a filter row.
     */
    public function test_two_choices_on_one_axis_widen_and_across_axes_narrow(): void
    {
        $both = $this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar&" . http_build_query([
            'option_ids' => [$this->opt['bmw'], $this->opt['mercedes']],
        ]))->assertOk()->json('data.offerings.data');

        $this->assertCount(3, $both, 'two makes on one axis must not exclude each other');

        $narrowed = $this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar&" . http_build_query([
            'option_ids' => [$this->opt['bmw'], $this->opt['mercedes'], $this->opt['rent']],
        ]))->assertOk()->json('data.offerings.data');

        $this->assertCount(1, $narrowed, 'a second axis must still narrow');
    }

    /**
     * A customer who tapped BMW must still see مرسيدس beside it with a real
     * count, or switching make means clearing the filter first.
     */
    public function test_an_axis_is_counted_without_its_own_choice(): void
    {
        $axes = collect($this->getJson("/api/v2/discovery/offerings/{$this->showroom->id}?lang=ar&" . http_build_query([
            'option_ids' => [$this->opt['bmw']],
        ]))->assertOk()->json('data.axes'))->keyBy('name');

        $makes = collect($axes['ماركات السيارات']['options'])->keyBy('name');

        $this->assertTrue($makes['BMW']['selected']);
        $this->assertFalse($makes['مرسيدس']['selected']);
        $this->assertGreaterThan(0, $makes['مرسيدس']['offerings'], 'the unchosen make reads as a dead end');

        // The OTHER axis is counted under the choice, though — that is what the
        // customer asked to be told about BMW.
        $deal = collect($axes['نوع التعامل']['options'])->keyBy('name');
        $this->assertSame(1, $deal['إيجار']['offerings']);
        $this->assertSame(1, $deal['بيع وشراء']['offerings']);
    }

    /** A shop with nothing priced answers cleanly instead of erroring. */
    public function test_a_business_with_no_offerings_returns_empty_axes(): void
    {
        $bare = $this->makeBusiness('معرض بلا تسعير');

        $body = $this->getJson("/api/v2/discovery/offerings/{$bare->id}?lang=ar")->assertOk()->json('data');

        $this->assertSame([], $body['axes']);
        $this->assertSame([], $body['offerings']['data']);
    }

    public function test_an_unknown_business_is_a_404(): void
    {
        $this->getJson('/api/v2/discovery/offerings/99999999')->assertStatus(404);
    }

    /**
     * A showroom that rents one SUV and sells two cars — built rather than
     * found, because no showroom on the platform carries a priced row yet.
     */
    private function makeShowroom(): User
    {
        $biz = $this->makeBusiness('معرض الاختبار');

        // Renting: a booking row, priced per stay, against a named car.
        $rental = BusinessServicePrice::create([
            'business_id' => $biz->id,
            'child_id' => 188,
            'service_id' => $this->bookingId,
            'bookable_item_type' => 'booking_stay',
            'price' => 900,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
        $rental->syncOfferingOptions($this->opt['suv'], [$this->opt['bmw'], $this->opt['rent']]);

        BookableItem::create([
            'business_id' => $biz->id,
            'service_id' => $this->bookingId,
            'item_type' => 'booking_stay',
            'line_option_id' => $this->opt['suv'],
            'title' => 'SUV للإيجار',
            'code' => 'TEST-SUV-1',
            'capacity' => 5,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        // Selling, surface one: another priced row.
        $sale = BusinessServicePrice::create([
            'business_id' => $biz->id,
            'child_id' => 188,
            'service_id' => $this->bookingId,
            'bookable_item_type' => 'booking_appointment',
            'price' => 750000,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
        $sale->syncOfferingOptions($this->opt['suv'], [$this->opt['mercedes'], $this->opt['sale']]);

        // Selling, surface two: a menu listing, which is where a showroom's
        // «معروض للبيع» actually lives (menu_vehicles).
        $listing = MenuItem::create([
            'business_id' => $biz->id,
            'item_type' => 'menu_vehicles',
            'name_ar' => 'سيدان للبيع',
            'base_price' => 600000,
            'is_active' => 1,
        ]);
        $listing->syncOfferingOptions($this->opt['sedan'], [$this->opt['bmw'], $this->opt['sale']]);

        return $biz;
    }

    private function makeBusiness(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => 'showroom' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 17,
            'category_child_id' => 188,
            'api_token' => \Illuminate\Support\Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)
            ->where('o.name_ar', $name)
            ->value('o.id');
    }
}
