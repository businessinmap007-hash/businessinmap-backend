<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingShapeResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «قمت بعمل حجز 10 ايام على الغرفة الثلاثية 1300 جنية اليوم، الاجمالى اصبح
 * 135000» — المالك، 2026-08-20.
 *
 * عشرُ ليالٍ بألفٍ وثلاثمئة هى ثلاثةَ عشرَ ألفًا. والزيادةُ لم تكن فى المحرّك
 * بل فى الشاشة: سطرٌ فى الجافاسكربت ينسخ عددَ الليالى المحسوبَ إلى خانة
 * «الكمية»، فيصل الرقمُ إلى الخادم مرّتين — مرّةً مدّةً ومرّةً عددَ وحدات —
 * فيُضرب فى نفسه: عشرُ ليالٍ × عشرِ غرفٍ لم يطلبها أحد.
 *
 * وهذا الملفُّ يحرس الحسابَ من جهة الخادم — نقطةَ المعاينة نفسَها التى تنادى
 * عليها الشاشة — حتى لا يعود الرقمُ من بابٍ آخر.
 *
 * ── واعداداتُ الحجز ────────────────────────────────────────────────────────
 *
 * «اعدادات الحجز غير مربوطه بالحجز الفعلى». وكان محقًّا: هذه الشاشةُ لم تكن
 * تقرأ `BookingShapeResolver` أصلًا، فما يضبطه صاحبُ النشاط لا يبلغها.
 */
class BookingTotalHonestyTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';
    private const TRIPLE = 973;

    private User $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = User::create([
            'name' => 'Zz Total ' . uniqid(),
            'email' => 'zz-total-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    private function serviceId(): int
    {
        return (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
    }

    private function tripleRoom(float $price = 1300): BookableItem
    {
        BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::TRIPLE,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        return BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::TRIPLE,
            'code' => 'T301',
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    /** نقطةُ المعاينة كما تناديها الشاشة. */
    private function preview(BookableItem $room, int $nights, array $extra = []): array
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->first();

        if (! $admin) {
            $this->markTestSkipped('لا حساب إدارة.');
        }

        $start = Carbon::today()->addDay()->setTime(14, 0);

        return $this->actingAs($admin)->getJson(
            route('admin.bookings.pricingPreview', [], false) . '?' . http_build_query($extra + [
                'business_id' => $this->hotel->id,
                'service_id' => $this->serviceId(),
                'bookable_id' => $room->id,
                'quantity' => 1,
                'starts_at' => $start->toDateTimeString(),
                'ends_at' => $start->copy()->addDays($nights)->toDateTimeString(),
            ])
        )->json();
    }

    /** عشرُ ليالٍ بألفٍ وثلاثمئة = ثلاثةَ عشرَ ألفًا. */
    public function test_ten_nights_at_1300_is_thirteen_thousand(): void
    {
        $room = $this->tripleRoom(1300);

        $data = $this->preview($room, 10);

        $this->assertTrue((bool) ($data['ok'] ?? false), 'المعاينةُ فشلت: ' . ($data['message'] ?? ''));
        $this->assertSame(13000.0, (float) data_get($data, 'pricing.final_price'));
        $this->assertSame(10, (int) data_get($data, 'pricing.periods_count'));
        $this->assertSame(1, (int) data_get($data, 'pricing.units'));
    }

    /** والغرفتان ضِعفُ الواحدة، لا مربَّعُها. */
    public function test_two_rooms_double_it_and_nothing_more(): void
    {
        $room = $this->tripleRoom(1300);

        $data = $this->preview($room, 10, ['quantity' => 2]);

        $this->assertSame(26000.0, (float) data_get($data, 'pricing.final_price'));
        $this->assertSame(2, (int) data_get($data, 'pricing.units'));
    }

    /** والإضافةُ لكل فردٍ تُضرب فى النزلاء لا فى الليالى ولا فى الغرف. */
    public function test_a_per_person_add_on_scales_with_the_guests_only(): void
    {
        $room = $this->tripleRoom(1300);
        $breakfast = 855;

        $this->actingAs($this->hotel)->put(route('business.booking-add-ons.update', [], false), [
            'option_ids' => [$breakfast],
            'adjust' => [$breakfast => 50],
            'per_person' => [$breakfast => 1],
        ])->assertRedirect();

        // ثلاثةُ نزلاء، عشرُ ليالٍ: ١٠ × (١٣٠٠ + ٣×٥٠) = ١٤٥٠٠.
        $data = $this->preview($room, 10, [
            'party_size' => 3,
            'option_ids' => [$breakfast],
        ]);

        $this->assertSame(14500.0, (float) data_get($data, 'pricing.final_price'));
    }

    /**
     * وإعداداتُ الحجز تبلغ الشاشة.
     *
     * `BookingShapeResolver` هى الجهةُ التى تدمج ما فتحه التصنيفُ بما اختاره
     * النشاط، وهذه الشاشةُ لم تكن تنادى عليها — فحمولةُ الشكل الآن تحمل النمط.
     */
    public function test_the_screen_is_told_the_shape_the_business_chose(): void
    {
        $room = $this->tripleRoom(1300);
        $admin = User::query()->where('type', User::TYPE_ADMIN)->first();

        if (! $admin) {
            $this->markTestSkipped('لا حساب إدارة.');
        }

        $shape = app(BookingShapeResolver::class)->forBusiness((int) $this->hotel->id);

        if (! $shape) {
            $this->markTestSkipped('لا شكلَ حجزٍ لهذا التصنيف.');
        }

        $config = $this->actingAs($admin)->getJson(
            route('admin.bookings.bookableItemsLookup', [], false)
            . '?business_id=' . $this->hotel->id . '&service_id=' . $this->serviceId()
        )->assertOk()->json('service_config');

        $this->assertSame($shape['pattern'], $config['pattern'] ?? null);
        $this->assertSame($shape['requires'], $config['required_fields'] ?? null);
    }
}
