<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single, canonical list of the manageable services on a business account —
 * one place gathering everything a business can operate, and the vocabulary a
 * business grants to a delegated staff member (see [[business_staff]]).
 *
 * Keys are stable strings stored in business_staff.capabilities and named on the
 * `business.member:{capability}` route middleware. Labels are for the picker UI.
 */
final class BusinessCapability
{
    public const ORDERS = 'orders';
    public const MENU = 'menu';
    public const BOOKINGS = 'bookings';
    public const OFFERS = 'offers';
    public const RETAIL = 'retail';
    public const WORKING_HOURS = 'working_hours';
    public const PROJECTS = 'projects';
    public const PRESCRIPTIONS = 'prescriptions';
    public const SCHEDULES = 'schedules';
    public const PRICES = 'prices';
    public const TRAINING = 'training';
    public const CLINIC = 'clinic';

    /**
     * The registry: key => [ar, en]. Order here is the display order.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function registry(): array
    {
        return [
            self::ORDERS => ['الطلبات', 'Orders'],
            self::MENU => ['المنيو', 'Menu'],
            self::BOOKINGS => ['الحجوزات', 'Bookings'],
            self::OFFERS => ['العروض', 'Offers'],
            self::RETAIL => ['منتجات التجزئة', 'Retail products'],
            self::PRICES => ['الأسعار', 'Prices'],
            self::WORKING_HOURS => ['مواعيد العمل', 'Working hours'],
            self::PROJECTS => ['المشاريع', 'Projects'],
            self::PRESCRIPTIONS => ['الوصفات الطبية', 'Prescriptions'],
            self::SCHEDULES => ['خطوط التشغيل', 'Trip schedules'],
            self::TRAINING => ['خطط التدريب والتغذية', 'Training & nutrition plans'],
            self::CLINIC => ['مواعيد العيادة', 'Clinic appointments'],
        ];
    }

    /**
     * ما تبرّره خدمةُ التصنيف. وما ليس هنا يخصّ كل نشاط.
     *
     * «لماذا فى الموظفون يوجد الوصفات الطبية والحساب تسويق» — المالك،
     * 2026-08-19. وكان السجلّ يُعرض كاملًا على الجميع، فوكالةُ تسويق تمنح
     * سكرتيرتها «الوصفات الطبية» و«مواعيد العيادة» و«المنيو».
     *
     * الطلباتُ والعروضُ والأسعارُ ومواعيدُ العمل ليست خدمةً تُباع — هى إدارةُ
     * الحساب نفسه، فتبقى للجميع. و«المشاريع» كذلك عن قصد: خطُّ زمنٍ عامّ
     * يستعمله المقاول والوكالة سواء، ولا إشارةَ فى البيانات تفرّق بينهما،
     * وحارسٌ مبنىٌّ على حدسٍ يُخفى ما لا ينبغى إخفاؤه.
     */
    private const NEEDS_SERVICE = [
        self::MENU => 'menu',
        self::BOOKINGS => 'booking',
        self::RETAIL => 'retail',
        self::SCHEDULES => 'schedules',
        self::TRAINING => 'training',
    ];

    /**
     * الوصفةُ والعيادة تخصّان الطبّ وحده، ولا خدمةَ منصّةٍ تسمّيهما.
     *
     * فالحارس هو الجذر، مقروءًا بـ`slug` لا بالمعرّف: الجذور تُنقل ويُعاد
     * ترقيمها، و«الصحة» لا تتغيّر تسميتُها الإنجليزية.
     */
    private const NEEDS_HEALTH_ROOT = [
        self::PRESCRIPTIONS,
        self::CLINIC,
    ];

    private const HEALTH_ROOT_SLUG = 'health';

    /**
     * السجلّ مقصورًا على ما يستطيع هذا النشاط فعله.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function forBusiness(?User $business): array
    {
        if (! $business) {
            return self::registry();
        }

        $services = BusinessPanelNav::servicesOf($business);
        $isHealth = self::standsUnderHealth($business);

        return array_filter(
            self::registry(),
            function (string $key) use ($services, $isHealth) {
                if (isset(self::NEEDS_SERVICE[$key])) {
                    return in_array(self::NEEDS_SERVICE[$key], $services, true);
                }

                if (in_array($key, self::NEEDS_HEALTH_ROOT, true)) {
                    return $isHealth;
                }

                return true;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * لا يُمنَح إلا ما يستطيع النشاط فعله.
     *
     * إخفاءُ المربّع من الشاشة لا يكفى: الحفظ يُرسَل، ومن يرسله بيده يمنح
     * موظّفه صلاحيةً لا يملكها هو.
     *
     * @return list<string>
     */
    public static function sanitizeFor(?User $business, array $keys): array
    {
        $allowed = array_keys(self::forBusiness($business));

        return array_values(array_intersect(self::sanitize($keys), $allowed));
    }

    private static function standsUnderHealth(User $business): bool
    {
        $rootId = (int) ($business->category_id ?? 0);

        if ($rootId <= 0) {
            return false;
        }

        return (string) DB::table('categories')->where('id', $rootId)->value('slug') === self::HEALTH_ROOT_SLUG;
    }

    /** السجلّ لنشاطٍ بعينه، بالشكل الذى تنتظره الواجهة البرمجية. */
    public static function catalogFor(?User $business): array
    {
        $out = [];

        foreach (self::forBusiness($business) as $key => [$ar, $en]) {
            $out[] = ['key' => $key, 'name_ar' => $ar, 'name_en' => $en];
        }

        return $out;
    }

    /** @return list<string> every valid capability key */
    public static function keys(): array
    {
        return array_keys(self::registry());
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::registry());
    }

    /** Keep only known keys, de-duplicated and re-indexed. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_unique(array_filter(
            array_map('strval', $keys),
            fn (string $k) => self::isValid($k),
        )));
    }

    /** The registry shaped for an API response. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::registry() as $key => [$ar, $en]) {
            $out[] = ['key' => $key, 'name_ar' => $ar, 'name_en' => $en];
        }

        return $out;
    }
}
