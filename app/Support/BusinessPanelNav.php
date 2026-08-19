<?php

namespace App\Support;

use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
 * الرئيسية والأسعار والموظفون والطلبات ليست خدمةً يبيعها أحد — هى إدارةُ
 * الحساب نفسه. أمّا ما يُشترى ويُباع فيُعرض بمقدار ما وُصِّل للتصنيف.
 */
class BusinessPanelNav
{
    /** الرابط ⇒ مفتاح الخدمة التى تبرّره. ما ليس هنا يظهر دائمًا. */
    private const GATED = [
        'bookable-items' => PlatformService::KEY_BOOKING,
        'booking-settings' => PlatformService::KEY_BOOKING,
        'bookings' => PlatformService::KEY_BOOKING,
        'menu' => 'menu',
        'tables' => 'menu',
        'table-calls' => 'menu',
        'products' => 'retail',
        'schedules' => 'schedules',
        'training-plans' => 'training',
    ];

    /**
     * مفاتيح الخدمات النشطة على تصنيف النشاط.
     *
     * @return array<int, string>
     */
    public static function servicesOf(?User $business = null): array
    {
        $business ??= Auth::user();
        $childId = (int) ($business->category_child_id ?? 0);

        if ($childId <= 0) {
            return [];
        }

        /*
         * بلا ذاكرة ساكنة.
         *
         * كانت هنا واحدة، وأخوها فى BookingShapeResolver أبات جوابًا فى اختبارٍ
         * سأل مرّتين وحفظ بينهما. حالةٌ ساكنة تعيش أطول من الطلب، والاستعلامُ
         * مرّةً لكل رسمِ صفحةٍ ثمنٌ زهيد مقابل شريطٍ يعرض ما لم يعد صحيحًا.
         */
        return CategoryPlatformService::query()
            ->join('platform_services as s', 's.id', '=', 'category_platform_services.platform_service_id')
            ->where('category_platform_services.child_id', $childId)
            ->where('category_platform_services.is_active', 1)
            ->where('s.is_active', 1)
            ->distinct()
            ->pluck('s.key')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    /** هل يُعرض هذا الرابط لهذا النشاط؟ */
    public static function shows(string $link, ?User $business = null): bool
    {
        $needs = self::GATED[$link] ?? null;

        if ($needs === null) {
            return true;
        }

        return in_array($needs, self::servicesOf($business), true);
    }
}
