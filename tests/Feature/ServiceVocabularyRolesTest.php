<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingVocabularyRoles;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «هل يمكن فى صفحة اعدادات الحجز ان نظهر مجموعات الخيارات المختارة من البزنس
 * لنفسه ويقوم بتحديد مجموعات كاملة زى الغرف مثلا انها الاساسية لتسعير الخدمة»
 * — المالك، 2026-08-20.
 *
 * الحاجزُ بين «السطر» و«المُوصِّف» فُتح عمدًا حين طُلب فتحُه: كلُّ كلمةٍ يقولها
 * التاجرُ عن نفسه تصلح للخانتين. لكنّ فتحَه ترك السؤالَ بلا جواب — فمفرداتُ
 * الفندق الثلاث تظهر فى كل قائمةٍ من قوائم اللوحة، ويُترك له أن يستنتج الدورَ
 * من الشاشة التى يقف فيها. وهو ما لم يستنتجه، بحقّ: خلط الإطلالةَ بالإفطار
 * لأن الشاشتين تعرضان الكلماتِ نفسها.
 *
 * فيُعلَن مرّةً واحدة، ثم تقرؤه الشاشاتُ الثلاث. والمثالُ الذى ساقه المالك هو
 * ما يحرسه هذا الملفّ حرفيًّا:
 *
 *     مزدوجة ٩٠٠  +  إطلالة D117 ‎٢٠٠  =  ١١٠٠
 *     +  إفطار ٥٠ × ٣ أفراد            =  ١٢٥٠ لليلة
 *     ×  ١٠ ليالٍ                       =  ١٢٥٠٠
 */
class ServiceVocabularyRolesTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';

    private const DOUBLE = 966;
    private const SEA_VIEW = 856;
    private const BREAKFAST = 855;

    private User $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = User::create([
            'name' => 'Zz Roles ' . uniqid(),
            'email' => 'zz-roles-' . uniqid() . '@test.local',
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

    private function groupIdOf(int $optionId): int
    {
        return (int) DB::table('options')->where('id', $optionId)->value('group_id');
    }

    private function roles(): BookingVocabularyRoles
    {
        return app(BookingVocabularyRoles::class);
    }

    /*
    |--------------------------------------------------------------------------
    | الإعلان
    |--------------------------------------------------------------------------
    */

    /** الشاشةُ تعرض مجموعاتِ التاجر وتسأل عن دور كلٍّ منها. */
    public function test_the_settings_screen_asks_for_each_groups_role(): void
    {
        $html = $this->actingAs($this->hotel)
            ->get(route('business.booking-settings.edit', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('إعدادات الخدمات', $html, 'الاسمُ لم يتغيّر');
        $this->assertStringContainsString('group_roles[', $html);
        $this->assertStringContainsString('أساس السعر', $html);
        $this->assertStringContainsString('إضافة بسعر منفصل', $html);
    }

    /** ويُحفظ ما يعلنه. */
    public function test_it_saves_the_declared_roles(): void
    {
        $rooms = $this->groupIdOf(self::DOUBLE);
        $meals = $this->groupIdOf(self::BREAKFAST);

        $this->roles()->save((int) $this->hotel->id, [
            $rooms => BookingVocabularyRoles::ROLE_LINE,
            $meals => BookingVocabularyRoles::ROLE_ADDON,
        ]);

        $declared = $this->roles()->for((int) $this->hotel->id);

        $this->assertSame(BookingVocabularyRoles::ROLE_LINE, $declared[$rooms]);
        $this->assertSame(BookingVocabularyRoles::ROLE_ADDON, $declared[$meals]);
    }

    /** و«بلا تحديد» تمحو الإعلان بدل أن تكتب دورًا رابعًا. */
    public function test_clearing_a_role_removes_the_declaration(): void
    {
        $rooms = $this->groupIdOf(self::DOUBLE);

        $this->roles()->save((int) $this->hotel->id, [$rooms => BookingVocabularyRoles::ROLE_LINE]);
        $this->roles()->save((int) $this->hotel->id, [$rooms => '']);

        $this->assertArrayNotHasKey($rooms, $this->roles()->for((int) $this->hotel->id));
    }

    /**
     * ومجموعةٌ لم تُعلَن تظهر فى الجميع.
     *
     * الإعلانُ يضيّق ولا يُشترط، فلا ينكسر تاجرٌ لم يفتح هذه الشاشة قطّ — وهم
     * كلُّ تجّار المنصّة يوم كُتبت.
     */
    public function test_a_business_that_declared_nothing_sees_everything(): void
    {
        $vocabulary = app(\App\Services\MerchantOfferingVocabulary::class)
            ->for((int) $this->hotel->id, self::CHILD, self::ROOT)['modifiers'];

        foreach (BookingVocabularyRoles::ROLES as $role) {
            $this->assertSame(
                $vocabulary->count(),
                $this->roles()->only($vocabulary, (int) $this->hotel->id, $role)->count(),
                "الدور «{$role}» ضيّق قائمةً بلا إعلان"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ما تقرؤه الشاشات
    |--------------------------------------------------------------------------
    */

    /** شاشةُ الإضافات تعرض ما أُعلن `addon` وحده. */
    public function test_the_add_ons_screen_shows_only_the_add_on_groups(): void
    {
        $rooms = $this->groupIdOf(self::DOUBLE);
        $meals = $this->groupIdOf(self::BREAKFAST);

        if ($rooms === $meals) {
            $this->markTestSkipped('«الغرف» و«نظام الوجبات» فى مجموعةٍ واحدة عند هذا التصنيف.');
        }

        $this->roles()->save((int) $this->hotel->id, [
            $rooms => BookingVocabularyRoles::ROLE_LINE,
            $meals => BookingVocabularyRoles::ROLE_ADDON,
        ]);

        $html = $this->actingAs($this->hotel)
            ->get(route('business.booking-add-ons.index', [], false))
            ->assertOk()->getContent();

        $roomsName = (string) OptionGroup::query()->find($rooms)?->name_ar;

        $this->assertStringContainsString('شامل الإفطار', $html);
        $this->assertStringNotContainsString('>' . $roomsName . '<', $html, 'مجموعةُ الغرف تُعرض كإضافة');
    }

    /*
    |--------------------------------------------------------------------------
    | المثال كاملًا
    |--------------------------------------------------------------------------
    */

    /**
     * «مزدوجة ٩٠٠، وD117 مطلة على البحر ‎+٢٠٠ فتصير ١١٠٠، وإفطار ٥٠ لثلاثة
     * أفراد، وعشرُ ليالٍ» — الحسابُ كلُّه فى اختبارٍ واحد.
     */
    public function test_the_owners_worked_example_end_to_end(): void
    {
        // ١. الأساس: مزدوجة ٩٠٠.
        $price = BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::DOUBLE,
            'price' => 900,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        // ٢. الإطلالة تزيد ٢٠٠ — تُكتب مرّةً على النشاط.
        $this->hotel->syncOfferingOptions(null, [self::SEA_VIEW, self::BREAKFAST], [
            self::SEA_VIEW => ['type' => 'amount', 'value' => 200],
            // ٣. والإفطار ٥٠ لكل فرد.
            self::BREAKFAST => ['type' => 'amount', 'value' => 50, 'per_person' => true],
        ]);

        // ٤. وD117 وحدها مطلّة على البحر.
        $d117 = BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::DOUBLE,
            'code' => 'D117',
            'quantity' => 1,
            'is_active' => 1,
        ]);
        $d117->syncOfferingOptions(self::DOUBLE, [self::SEA_VIEW]);

        $d118 = BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::DOUBLE,
            'code' => 'D118',
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $engine = app(ServiceExecutionEngine::class);

        $nightly = function (BookableItem $room, array $chosen, int $people, int $nights = 1) use ($engine, $price) {
            $from = now()->addDay()->startOfDay()->addHours(14);

            return (float) $engine->resolvePriceBreakdown(
                service: PlatformService::findOrFail($price->service_id),
                businessPrice: $price,
                bookable: $room->fresh(),
                quantity: 1,
                pricingDate: $from->toDateTimeString(),
                optionIds: $engine->withUnitOwnOptions($room->fresh(), $chosen),
                until: $from->copy()->addDays($nights)->toDateTimeString(),
                partySize: $people
            )['final_price'];
        };

        // الإطلالةُ تُحسب بلا أن يؤشّرها أحد: D117 بـ١١٠٠ وD118 بـ٩٠٠.
        $this->assertSame(1100.0, $nightly($d117, [], 1), 'D117 المطلة على البحر');
        $this->assertSame(900.0, $nightly($d118, [], 1), 'D118 بلا إطلالة');

        // والإفطارُ يُضرب فى عدد الأفراد ويُجمع: ١١٠٠ + ٣×٥٠ = ١٢٥٠.
        $this->assertSame(1250.0, $nightly($d117, [self::BREAKFAST], 3), 'ليلةٌ بإفطار لثلاثة');

        // وعشرُ ليالٍ عشرةُ أمثالها — لا مئة.
        $this->assertSame(12500.0, $nightly($d117, [self::BREAKFAST], 3, 10), 'عشرُ ليالٍ');
    }

    /**
     * وعشرةُ أيامٍ على غرفةٍ بـ١٣٠٠ تساوى ١٣٠٠٠.
     *
     * «قمت بعمل حجز ١٠ ايام على الغرفة الثلاثية ١٣٠٠ جنية اليوم الاجمالى اصبح
     * ١٣٥٠٠٠» — كانت الشاشةُ تنسخ عددَ الليالى إلى خانة العدد، فيُضرب الرقمُ
     * فى نفسه: عشرُ ليالٍ × عشرِ غرفٍ لم يطلبها أحد.
     */
    public function test_ten_nights_at_1300_is_thirteen_thousand(): void
    {
        BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::DOUBLE,
            'price' => 1300,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $room = BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::DOUBLE,
            'code' => 'T301',
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $from = now()->addDay()->startOfDay()->addHours(14);

        $breakdown = app(ServiceExecutionEngine::class)->prepare(
            businessId: (int) $this->hotel->id,
            serviceId: $this->serviceId(),
            bookableId: (int) $room->id,
            quantity: 1,
            pricingDate: $from->toDateTimeString(),
            until: $from->copy()->addDays(10)->toDateTimeString()
        )['price_breakdown'];

        $this->assertSame(10, (int) $breakdown['periods_count']);
        $this->assertSame(1, (int) $breakdown['units']);
        $this->assertSame(13000.0, (float) $breakdown['final_price']);
    }

    /** والشاشةُ لا تنسخ المدّة فى خانة العدد بعد اليوم. */
    public function test_the_booking_form_no_longer_copies_the_duration_into_the_quantity(): void
    {
        $form = file_get_contents(resource_path('views/admin-v2/bookings/_form.blade.php'));

        $this->assertStringNotContainsString(
            'quantity.value = qty;',
            $form,
            'الشاشةُ عادت تنسخ عددَ الليالى إلى خانة العدد'
        );
    }
}
