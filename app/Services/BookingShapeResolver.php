<?php

namespace App\Services;

use App\Enums\BookingPattern;
use App\Models\BusinessBookingSetting;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * شكل الحجز عند نشاطٍ بعينه: ما يفتحه تصنيفه، وما قرّره هو فوقه.
 *
 * القارئ الوحيد للدرجتين معًا. المحرّك يفرضه (الخطوة ٤)، ونقطةُ «شكل الشاشة»
 * ترسمه (الخطوة ٥)، ولوحةُ الإدارة تعرضه — ولا يعيد أحدٌ منهم اشتقاقه. تكرارُ
 * منطق الدمج فى كل قارئ هو ما صنع المفاتيح الثمانية المتفرّقة أصلًا.
 *
 * ── ترجمة الحقول ────────────────────────────────────────────────────────────
 *
 * `requires()` تتكلّم بلغة الشاشة — «كم نزيلًا»، «كم فردًا» — و`bookings`
 * تتكلّم بلغة الأعمدة. و`guest_count` و`party_size` يهبطان على العمود نفسه
 * عن قصد: هما سؤالٌ واحد بلسانين، والفندق يسمّيه نزلاء والمطعم أفرادًا.
 * وما لا عمود له يسكن `meta` بدل أن يُخترع له عمودٌ لن يُقرأ.
 */
class BookingShapeResolver
{
    /** حقلُ الشاشة → أين يصل فى الحمولة. */
    private const COLUMNS = [
        'datetime' => 'starts_at',
        'guest_count' => 'party_size',
        'party_size' => 'party_size',
        'quantity' => 'quantity',
        'notes' => 'notes',
    ];

    /*
     * بلا ذاكرة داخلية، عن قصد.
     *
     * كانت هنا ذاكرةٌ لكل نشاط توفّر استعلامين. وكشفها اختبارٌ يسأل عن الشكل،
     * ثم يحفظ إعدادَ النشاط، ثم يسأل ثانيةً — فيأتيه الجوابُ الأول. مهما كان
     * سببُ بقاء النسخة حيّة بين النداءين، فالحساب الذى يقرأ صفًّا قد يتغيّر
     * لا يُحفظ داخل الكائن: استعلامان أرخص من شاشةٍ تعرض شرطًا رُفع للتوّ.
     */
    public function forBusiness(int $businessId): ?array
    {
        $business = User::query()
            ->where('id', $businessId)
            ->first(['id', 'category_id', 'category_child_id']);

        return $business ? $this->forContext(
            (int) $business->category_id,
            (int) $business->category_child_id,
            $businessId
        ) : null;
    }

    /**
     * الشكل عند (جذر، ابن) لنشاطٍ بعينه — أو بلا نشاط، فيكون شكل التصنيف وحده.
     */
    public function forContext(int $rootId, int $childId, ?int $businessId = null): ?array
    {
        $config = $this->configOf($rootId, $childId);
        $patterns = $this->patternsIn($config);

        if ($patterns === []) {
            return null;
        }

        $row = $businessId
            ? BusinessBookingSetting::query()->where('business_id', $businessId)->first()
            : null;

        $chosen = $row?->pattern();

        // نشاطٌ اختار نمطًا لم يعد تصنيفه يفتحه: التصنيف هو الحَكَم.
        if (! $chosen || ! in_array($chosen, $patterns, true)) {
            $chosen = $patterns[0];
        }

        $shape = BusinessBookingSetting::resolve($chosen, $row);

        /*
         * الامتناع ينتهى هنا، ولا يخرج منه.
         *
         * `UNIT_OPTIONAL` كلامٌ عن الطفل: «أنا لا أعرف أىُّ صاحبِ محلٍّ أنت».
         * وهذه الدالّة تجيب عن نشاطٍ بعينه، فلا يصحّ أن تسلّم القارئَ سؤالًا.
         * وحين لا يكون صاحبُ المحل قد قرّر، يحكم العلمُ المخزَّن كما كان يحكم
         * قبل الأنماط كلِّها — فملعبُ الكرة لا يفقد حارسه لأننا أضفنا طبقة.
         */
        if ($shape['unit'] === BookingPattern::UNIT_OPTIONAL) {
            $shape['unit'] = ($config['requires_bookable_item'] ?? false)
                ? BookingPattern::UNIT_ALWAYS
                : BookingPattern::UNIT_NEVER;
        }

        return $shape + [
            'available_patterns' => array_map(fn (BookingPattern $p) => $p->value, $patterns),
        ];
    }

    /**
     * ما ينقص الحمولةَ ممّا يشترطه الشكل.
     *
     * تُرجع رسائل جاهزة للرمى — بلغة الشاشة لا بلغة الأعمدة، فالعميل يقرأ
     * «كم نزيلًا» ولا يعرف أن العمود اسمه party_size.
     *
     * @return array<string, string>
     */
    public function missingFrom(array $shape, array $payload): array
    {
        $missing = [];

        foreach ($shape['requires'] ?? [] as $field) {
            if ($this->satisfied($field, $payload)) {
                continue;
            }

            $key = self::COLUMNS[$field] ?? 'meta';
            $missing[$key] = $this->message($field);
        }

        return $missing;
    }

    private function satisfied(string $field, array $payload): bool
    {
        /*
         * `date` + `time` تساوى `starts_at`.
         *
         * النقطة تقبل الشكلين منذ زمن — والتطبيق يرسل الأقدم — والمتحكّم يشتقّ
         * أحدهما من الآخر بعد هذا الحارس لا قبله. قراءةُ `starts_at` وحدها
         * جعلت الحارس يرفض كلَّ حجزٍ من التطبيق نفسه.
         */
        $hasStart = ! empty($payload['starts_at']) || ! empty($payload['date']);

        if ($field === 'date_range' || $field === 'duration') {
            return $hasStart
                && (! empty($payload['ends_at']) || ! empty($payload['duration_value']));
        }

        if ($field === 'datetime') {
            return $hasStart;
        }

        if (isset(self::COLUMNS[$field])) {
            return ! empty($payload[self::COLUMNS[$field]]);
        }

        // A meta field's answer can legitimately be 0 — «كم طفلًا؟» is answered
        // by «صفر» as much as by «اثنان» — so presence, not truthiness, is what
        // "required" means here. `empty()` treated 0 the same as never having
        // asked, which made a required-but-zero answer impossible to give.
        $value = data_get($payload, "meta.{$field}");

        return $value !== null && $value !== '';
    }

    private function message(string $field): string
    {
        return match ($field) {
            'date_range' => __('حدّد بداية المدة ونهايتها.'),
            'duration' => __('حدّد وقت البداية والنهاية.'),
            'datetime' => __('حدّد موعد الحجز.'),
            'guest_count' => __('كم نزيلًا؟'),
            'party_size' => __('كم فردًا؟'),
            'quantity' => __('حدّد الكمية.'),
            'channel' => __('اختر: حضوريًا أم أونلاين؟'),
            default => __('هذا الحقل مطلوب: :field', ['field' => $field]),
        };
    }

    private function configOf(int $rootId, int $childId): array
    {
        if ($childId <= 0) {
            return [];
        }

        $serviceId = (int) DB::table('platform_services')
            ->where('key', PlatformService::KEY_BOOKING)
            ->where('is_active', 1)
            ->value('id');

        if ($serviceId <= 0) {
            return [];
        }

        $config = CategoryServiceConfig::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->when($rootId > 0, fn ($q) => $q->where('category_id', $rootId))
            ->value('config');

        return is_array($config) ? $config : (json_decode((string) $config, true) ?: []);
    }

    /** @return BookingPattern[] */
    private function patternsIn(array $config): array
    {
        $declared = $config['booking_patterns'] ?? array_filter([$config['booking_pattern'] ?? null]);

        return array_values(array_filter(array_map(
            fn ($value) => BookingPattern::tryFrom((string) $value),
            is_array($declared) ? $declared : []
        )));
    }
}
