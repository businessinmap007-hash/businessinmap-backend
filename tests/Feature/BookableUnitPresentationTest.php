<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\Image;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «اين سيتم اضافة الصور والمعلومات فى حسابى bim فنادق» — المالك، 2026-08-19.
 *
 * لم يكن هناك مكان. الوحدةُ ثمانيةُ حقول — خدمةٌ ونوعٌ وكودٌ وسعةٌ وعدد — فغرفةُ
 * ١٠١ تُعرَّف برقمها ولا تُرى ولا تُوصَف. فقائمةُ غرفِ الفندق لم تكن تشبه
 * المنيو فى شىء: صنفُ الطعام له صورُه ووصفُه منذ زمن، والغرفةُ لا.
 *
 * ── وسعرُها ─────────────────────────────────────────────────────────────
 *
 * «الغرفة الفردى ٦٠٠ بدون إفطار، وإذا أضفنا إفطارًا يحدَّد سعره منفصلًا
 * فيكون المجموع ٦٥٠» — المالك، 2026-08-19.
 *
 * والفرقُ الذى يحمله هذا الملف: مَن يقرّر. «إطلالة بحرية» صفةُ الغرفة —
 * مكتوبةٌ عليها، محسوبةٌ فى سعرها المعروض، لا يُسأل عنها. و«شامل الإفطار»
 * قرارُ النزيل — تسكن سطرَ السعر وحده، فتُعرض عليه وتُحسَب إن اختارها.
 */
class BookableUnitPresentationTest extends TestCase
{
    use DatabaseTransactions;

    /** فندقٌ حقيقىٌّ التصنيف، مملوكٌ لهذا الاختبار وحده. */
    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';

    /** «غرفة فردية» — ما تبيعه، و«شامل الإفطار» — ما يضيفه النزيل. */
    private const SINGLE_ROOM = 965;
    private const BREAKFAST = 855;
    private const FULL_BOARD = 856;

    private function hotel(): User
    {
        return User::create([
            'name' => 'Zz Hotel ' . uniqid(),
            'email' => 'zz-hotel-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    private function bookingServiceId(): int
    {
        return (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
    }

    private function room(User $hotel, string $code, array $extra = []): BookableItem
    {
        return BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $this->bookingServiceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE_ROOM,
            'code' => $code,
            'quantity' => 1,
            'is_active' => 1,
        ] + $extra);
    }

    /** الدفعةُ كما ترسلها الشاشة. */
    private function postBatch(User $hotel, array $payload)
    {
        return $this->actingAs($hotel)->post(route('business.bookable-items.bulk.store', [], false), $payload + [
            'service_id' => $this->bookingServiceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE_ROOM,
        ]);
    }

    private function priceRowOf(User $hotel): ?BusinessServicePrice
    {
        return BusinessServicePrice::query()
            ->where('business_id', $hotel->id)
            ->where('bookable_item_type', self::ITEM_TYPE)
            ->where('line_option_id', self::SINGLE_ROOM)
            ->first();
    }

    /** ما يدفعه النزيل على ليلةٍ واحدة، باختياراته. */
    private function nightly(BookableItem $room, array $chosen = []): float
    {
        $price = app(\App\Services\BusinessServicePriceResolver::class)->resolveForBookableItem($room);

        $breakdown = app(ServiceExecutionEngine::class)->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: $room,
            quantity: 1,
            pricingDate: now(),
            optionIds: app(ServiceExecutionEngine::class)->withUnitOwnOptions($room, $chosen)
        );

        return (float) $breakdown['final_price'];
    }

    /*
    |--------------------------------------------------------------------------
    | الصورةُ والكلمة
    |--------------------------------------------------------------------------
    */

    /** الشاشةُ تسأل عن الاثنين: ما يقرؤه النزيل وما لا يقرؤه أحدٌ غيرك. */
    public function test_the_unit_form_asks_for_a_public_description_and_a_private_note(): void
    {
        $html = $this->actingAs($this->hotel())
            ->get(route('business.bookable-items.create', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('name="notes"', $html);
    }

    /** والدفعةُ توصَف مرةً واحدة: عشرُ غرفٍ مزدوجة تشترك فى وصفها. */
    public function test_a_batch_is_described_once_for_all_of_it(): void
    {
        $html = $this->actingAs($this->hotel())
            ->get(route('business.bookable-items.bulk', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="description"', $html);
    }

    /** والوصفُ يصل كلَّ وحدةٍ فى المدى، لا الأولى وحدها. */
    public function test_the_batch_description_reaches_every_unit_in_the_range(): void
    {
        $hotel = $this->hotel();

        $this->postBatch($hotel, [
            'from' => 9001, 'to' => 9003,
            'description' => 'غرفة مزدوجة بتكييف وحمام خاص.',
        ])->assertRedirect();

        $rooms = BookableItem::where('business_id', $hotel->id)->get();

        $this->assertCount(3, $rooms);
        $this->assertTrue($rooms->every(fn ($r) => $r->description === 'غرفة مزدوجة بتكييف وحمام خاص.'));
    }

    /**
     * وصورُ الغرفة تموت معها — الصفُّ والملف.
     *
     * وهو السببُ الذى من أجله دخل `HasOwnedImages` هذا النموذج بدل أن تُكتب
     * قاعدةُ التنظيف فى شاشةٍ واحدة: الوحدةُ تُحذف من ثلاثة أبواب.
     */
    public function test_a_unit_owns_its_photos_and_they_die_with_it(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 'Z101');

        $room->images()->create(['image' => 'files/uploads/zz-test-one.png', 'source' => Image::SOURCE_UPLOAD]);
        $room->images()->create(['image' => 'files/uploads/zz-test-two.png', 'source' => Image::SOURCE_UPLOAD]);

        $this->assertSame(2, $room->images()->count());

        $roomId = $room->id;
        $room->delete();

        $this->assertSame(0, Image::query()
            ->where('imageable_type', (new BookableItem)->getMorphClass())
            ->where('imageable_id', $roomId)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | ما يصل النزيل
    |--------------------------------------------------------------------------
    */

    /** قائمةُ الغرف تحمل الوصفَ والصور، كما يحمل المنيو صورةَ الطبق. */
    public function test_the_customer_list_carries_the_description_and_the_photos(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 'Z101', ['description' => 'إطلالة على النيل، الدور السادس.']);
        $room->images()->create(['image' => 'files/uploads/zz-test-one.png', 'source' => Image::SOURCE_UPLOAD]);

        $unit = $this->getJson('/api/v2/discovery/units/' . $hotel->id)
            ->assertOk()->json('data.kinds.0.units.0');

        $this->assertSame('إطلالة على النيل، الدور السادس.', $unit['description']);
        $this->assertCount(1, $unit['images']);
        $this->assertSame('files/uploads/zz-test-one.png', $unit['images'][0]['image']);
    }

    /** والملاحظةُ الداخلية لا تخرج من المحل أبدًا. */
    public function test_the_internal_note_never_leaves_the_shop(): void
    {
        $hotel = $this->hotel();
        $this->room($hotel, 'Z101', ['notes' => 'التكييف يحتاج صيانة']);

        $body = $this->getJson('/api/v2/discovery/units/' . $hotel->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('التكييف يحتاج صيانة', $body);
        $this->assertStringNotContainsString('"notes"', $body);
    }

    /*
    |--------------------------------------------------------------------------
    | السعر
    |--------------------------------------------------------------------------
    */

    /** «٦ غرف فردى سعرها ٦٠٠» — الدفعةُ تكتب سعرها، لا شاشةٌ أخرى بعدها. */
    public function test_the_batch_writes_the_price_of_what_it_creates(): void
    {
        $hotel = $this->hotel();

        $this->assertNull($this->priceRowOf($hotel), 'فندقٌ جديد بلا أسعار');

        $this->postBatch($hotel, ['from' => 9001, 'to' => 9006, 'price' => 600])->assertRedirect();

        $row = $this->priceRowOf($hotel);

        $this->assertNotNull($row, 'الدفعةُ لم تكتب سطرَ سعر');
        $this->assertSame(600.0, (float) $row->price);
        $this->assertSame(6, BookableItem::where('business_id', $hotel->id)->count());
    }

    /** ولا يُنشأ سطرُ سعرٍ بلا سعر: صفرٌ يُقرأ «مجّانًا» لا «لم أسعّر بعد». */
    public function test_a_batch_with_no_price_writes_no_price_row(): void
    {
        $hotel = $this->hotel();

        $this->postBatch($hotel, ['from' => 9001, 'to' => 9002])->assertRedirect();

        $this->assertNull($this->priceRowOf($hotel));
    }

    /*
     * اختباراتُ «ما يختاره النزيل» انتقلت إلى BookingAddOnsTest يوم 2026-08-20.
     *
     * «افطار» و«اقامة كاملة» صارت لها شاشتُها — تُكتب مرّةً وتُعرض على كل نوع
     * — لأنها ليست صفةَ غرفةٍ بعينها. وما بقى هنا هو ما يخصّ الغرفةَ نفسها.
     */

    /** وصفةُ الغرفة عكسُه: مثبَّتةٌ عليها، محسوبةٌ بلا أن يؤشّرها أحد. */
    public function test_a_room_attribute_is_stamped_and_priced_without_being_asked_for(): void
    {
        $hotel = $this->hotel();

        $this->postBatch($hotel, [
            'from' => 9001, 'to' => 9002,
            'price' => 600,
            'option_ids' => [self::FULL_BOARD],
            'option_adjust' => [self::FULL_BOARD => 100],
        ])->assertRedirect();

        $room = BookableItem::where('business_id', $hotel->id)->firstOrFail();

        $this->assertContains(self::FULL_BOARD, $room->modifierOptionIds()->all());
        $this->assertSame(700.0, $this->nightly($room), 'الصفةُ لم تُحسَب بنفسها');
    }

    /** وما أعلنته الغرفةُ عن نفسها لا يُعرض خيارًا: ثمنُه محسوبٌ مرةً. */
    public function test_what_the_room_already_declares_is_not_offered_again(): void
    {
        $hotel = $this->hotel();

        $this->postBatch($hotel, [
            'from' => 9001, 'to' => 9002,
            'price' => 600,
            'option_ids' => [self::FULL_BOARD],
            'option_adjust' => [self::FULL_BOARD => 100],
        ])->assertRedirect();

        $kind = $this->getJson('/api/v2/discovery/units/' . $hotel->id)
            ->assertOk()->json('data.kinds.0');

        $this->assertSame([], $kind['choices']);
        $this->assertSame(700.0, (float) $kind['units'][0]['price'], 'سعرُ الغرفة يشمل صفتها');
    }
}
