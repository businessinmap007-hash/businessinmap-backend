<?php

namespace App\Support;

use App\Enums\BookingPattern;
use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingShapeResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * أىُّ شاشاتِ لوحة النشاط تخصّ هذا النشاط فعلًا.
 *
 * «فتحت حساب تسويق واعطانى منيو وحجوزات والخطط التدريبية» — المالك،
 * 2026-08-19. وكان محقًّا: «تسويق» #177 لا يفعّل إلا `booking`، و`menu`
 * و`delivery` مطفأتان عليه فى قاعدة البيانات منذ البداية. لكن شريطَ اللوحة
 * كان **سبعةَ عشرَ رابطًا مكتوبةً بلا شرطٍ واحد** — لم يسأل يومًا عمّا يبيعه
 * صاحبُ المحل.
 *
 * فالبيانات كانت صحيحة، والشاشة وحدها هى التى لم تقرأها.
 *
 * ── ما يظهر دائمًا ─────────────────────────────────────────────────────────
 *
 * الرئيسية والأسعار والموظفون ليست خدمةً يبيعها أحد — هى إدارةُ الحساب نفسه.
 * أمّا ما يُشترى ويُباع فيُعرض بمقدار ما وُصِّل للتصنيف.
 */
class BusinessPanelNav
{
    /** الرابط ⇒ مفتاح الخدمة التى تبرّره. ما ليس هنا يظهر دائمًا. */
    private const GATED = [
        'booking-settings' => PlatformService::KEY_BOOKING,
        'bookings' => PlatformService::KEY_BOOKING,
        'menu' => 'menu',
        'products' => 'retail',
        'schedules' => 'schedules',
        'training-plans' => 'training',
    ];

    /**
     * روابط يبرّرها أىُّ واحدةٍ من عدّة خدمات.
     *
     * «الطلبات» تأتى من منيو أو توصيل أو تجزئة. ووكالةُ التسويق لا تملك واحدةً
     * منها، فكانت تفتح شاشةً لا تمتلئ أبدًا.
     */
    private const GATED_ANY = [
        'orders' => ['menu', 'delivery', 'retail'],
    ];

    /**
     * «وحداتي» ليست سؤالَ خدمةٍ بل سؤالَ شكل.
     *
     * الحجزُ مفعَّلٌ على وكالة التسويق فعلًا — نمطُها «استشارة» — لكن الاستشارة
     * لا وحدةَ فيها تُحجَز، فالشاشة تبقى فارغةً مهما فعل صاحبُها. والجوابُ
     * يأتى من الدرجة الثالثة لا من التصنيف: صالةُ بلايستيشن تشارك الجيمَ نمطَه
     * وتفارقه هنا بالضبط، لأنها أعلنت أن عندها أجهزة تُحجَز.
     */
    private const NEEDS_UNITS = 'bookable-items';

    /** الطاولةُ ونداؤها من الطعام وحده، لا من كل ما يُسمّى منيو. */
    private const NEEDS_FOOD_MENU = ['tables', 'table-calls'];

    private const FOOD_KIND = 'menu_food';

    /**
     * مفاتيح الخدمات النشطة على تصنيف النشاط.
     *
     * @return array<int, string>
     */
    public static function servicesOf(?User $business = null): array
    {
        $business ??= Auth::user();
        $childId = (int) ($business->category_child_id ?? 0);
        $rootId = (int) ($business->category_id ?? 0);

        if ($childId <= 0) {
            return [];
        }

        /*
         * ── مقصورةٌ على (الجذر، الابن) ───────────────────────────────────────
         *
         * قراءةُ الابن وحده هى ما أبقى الشريطَ مخطئًا بعد أول إصلاح. «آثاث»
         * #116 يقف تحت ثلاثة جذور بثلاث حزمٍ مختلفة: تحت «شركات» يحجز ويوصّل
         * ويعرض منيو، وتحت جذرِ محلٍّ لا يحجز أصلًا، وتحت الثالث يبيع تجزئة.
         * فصاحبُ المحل الواقف على الجذر الثانى كان يرى «حجوزاتي» و«وحداتي»
         * و«منتجاتي» — لأن ابنه يفعلها فى مكانٍ آخر لا يقف هو فيه.
         *
         * وهذه هى قاعدةُ الشجرة كلِّها: الابن مشتركٌ بين الجذور ولا يجيب فيها
         * السؤالَ نفسه.
         *
         * وحسابٌ بلا جذر يعود إلى قراءة ابنه وحده — أوسع من الصواب، وأضيقُ من
         * أن يُخفى عنه كلَّ شىء.
         *
         * ولا ذاكرة ساكنة هنا: كانت واحدة، وأختُها فى BookingShapeResolver
         * أبات جوابًا فى اختبارٍ سأل مرّتين وحفظ بينهما.
         */
        return CategoryPlatformService::query()
            ->join('platform_services as s', 's.id', '=', 'category_platform_services.platform_service_id')
            ->where('category_platform_services.child_id', $childId)
            ->when($rootId > 0, fn ($q) => $q->where('category_platform_services.category_id', $rootId))
            ->where('category_platform_services.is_active', 1)
            ->where('s.is_active', 1)
            ->distinct()
            ->pluck('s.key')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    /**
     * أنواعُ عناصر المنيو عند هذا النشاط — والطاولةُ سؤالُ طعامٍ لا سؤالُ منيو.
     *
     * «المنيو» عندنا هى آلةُ الكتالوج لكل تاجر: `menu_furniture` لمعرض الأثاث،
     * و`menu_properties` لمكتب العقارات، و`menu_market` لتاجر الجملة. لكن
     * الطاولةَ ونداءَها من `menu_food` وحدها — فكان تاجرُ الأقمشة يجد فى لوحته
     * «الطاولات» و«نداءات الطاولات».
     *
     * @return array<int, string>
     */
    public static function menuKindsOf(User $business): array
    {
        $serviceId = (int) DB::table('platform_services')
            ->where('key', 'menu')->where('is_active', 1)->value('id');

        if ($serviceId <= 0) {
            return [];
        }

        $config = DB::table('category_service_configs')
            ->where('child_id', (int) $business->category_child_id)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->when((int) $business->category_id > 0, fn ($q) => $q->where('category_id', (int) $business->category_id))
            ->value('config');

        $config = json_decode((string) $config, true) ?: [];

        return array_map('strval', $config['allowed_item_types'] ?? []);
    }

    /**
     * ماذا نسمّى كتالوج هذا النشاط.
     *
     * الآلة واحدة، والاسمُ ليس كذلك. «المنيو» تخصّ من يقدّم طعامًا؛ ومعرضُ
     * الأثاث وتاجرُ الجملة ومكتبُ العقارات يستعملون الآلةَ نفسها ولا يسمّون
     * بضاعتهم منيو. والاسمُ يُقرأ من نوع العناصر لا من التصنيف، لأن النوع هو
     * ما يقول بأىِّ لغةٍ يتكلّم هذا التاجر.
     *
     * اسمان لا أكثر: التخصيصُ لكل نوعٍ يصنع ستّة أسماءٍ تتباعد مع أول تعديل،
     * والفرقُ الذى يهمّ هو بين مَن يُطعم ومَن يعرض.
     */
    public static function catalogLabel(?User $business = null): string
    {
        $business ??= Auth::user();

        if ($business && ! in_array(self::FOOD_KIND, self::menuKindsOf($business), true)) {
            return 'الكتالوج';
        }

        return 'المنيو';
    }

    /** هل يُعرض هذا الرابط لهذا النشاط؟ */
    public static function shows(string $link, ?User $business = null): bool
    {
        if ($link === self::NEEDS_UNITS) {
            return self::booksAUnit($business);
        }

        if (in_array($link, self::NEEDS_FOOD_MENU, true)) {
            $business ??= Auth::user();

            return $business
                && in_array('menu', self::servicesOf($business), true)
                && in_array(self::FOOD_KIND, self::menuKindsOf($business), true);
        }

        if (isset(self::GATED_ANY[$link])) {
            return (bool) array_intersect(self::GATED_ANY[$link], self::servicesOf($business));
        }

        $needs = self::GATED[$link] ?? null;

        if ($needs === null) {
            return true;
        }

        return in_array($needs, self::servicesOf($business), true);
    }

    /** هل يحجز عملاءُ هذا النشاط وحدةً بعينها؟ */
    private static function booksAUnit(?User $business): bool
    {
        $business ??= Auth::user();

        if (! $business || ! in_array(PlatformService::KEY_BOOKING, self::servicesOf($business), true)) {
            return false;
        }

        $shape = app(BookingShapeResolver::class)->forBusiness((int) $business->id);

        // بلا نمطٍ معلن يبقى الرابط: الغياب سكوتٌ لا حكم.
        return ! $shape || $shape['unit'] !== BookingPattern::UNIT_NEVER;
    }
}
