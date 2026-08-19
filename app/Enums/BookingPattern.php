<?php

namespace App\Enums;

/**
 * نمط الحجز — الشكل الكامل لعملية حجزٍ واحدة، باسمٍ واحد.
 *
 * ── لماذا نمطٌ بدل ثمانية مفاتيح ─────────────────────────────────────────────
 *
 * `category_service_configs.config` كان يحمل ثمانية مفاتيح مستقلّة تصف الحجز:
 * `requires_bookable_item`, `requires_start_end`, `supports_quantity`,
 * `supports_guest_count`, `supports_extras`, `required_fields`,
 * `booking_modes`, `item_family`.
 *
 * على ١٩٤ إعدادًا نشطًا كان **اثنان** منها مضبوطَين، والستّة الباقية مطفأةً
 * بلا استثناءٍ واحد — لأن كل بذرةٍ ضبطت ما تذكّرته ونسيت الباقى، ولأن ثمانية
 * مفاتيح لا تُملأ يدويًا صحيحةً مرّتين. والنتيجة: ٢٣ شكلًا بالاسم، وشكلٌ
 * واحد فى الحقيقة يتقاسمه ١٢٨ ابنًا — «موعد، ولا شىء آخر».
 *
 * فالنمط يُذكر باسمه، ويأتى بشكله كاملًا. لا يُنسى نصفه.
 *
 * ── سُلّم التفصيل ────────────────────────────────────────────────────────────
 *
 * ١. الطفل يفتح الأنماط المسموحة — «مطعم» يفتح طاولة + موعد.
 * ٢. النشاط يختار نمطه ويضبط تفاصيله — وهنا يقع التفصيل الحقيقى.
 * ٣. العميل يرى شاشةً مبنيّةً من ذلك، بلا حقلٍ لا معنى له.
 *
 * ولهذا `unit()` ثلاثىٌّ لا ثنائى: `OPTIONAL` تعنى **أن الطفل يمتنع عن الحكم**
 * لا أن الجواب «لا». البلايستيشن يؤجّر ستّة أجهزة، والبولينج أربع حارات،
 * والجيم لا يؤجّر شيئًا — يبيع دخولًا. ثلاثتهم فى نمطٍ واحد، وصاحبُ المحل
 * وحده يعرف أيّهم هو.
 *
 * ── ما لا يفعله هذا الملف ────────────────────────────────────────────────────
 *
 * لا يفرض شيئًا. `requires()` إعلانٌ يقرأه المحرّك لاحقًا (الخطوة ٤)، وحتى
 * ذلك الحين تظلّ نقطة إنشاء الحجز تقبل كلَّ شىء وتشترط لا شىء — وهى الحال
 * القائمة اليوم: `party_size` و`quantity` و`notes` كلُّها `nullable`.
 * وظيفةُ النمط أن يحوّل `nullable` إلى «مطلوبٌ هنا».
 */
enum BookingPattern: string
{
    /** غرفة أو شقة، بالليلة. فندق، منتجع، مالك عقار. */
    case STAY = 'stay';

    /** طاولة فى مطعم أو كافيه. */
    case TABLE = 'table';

    /** وقتٌ بالساعة — على وحدةٍ أو بدونها. ملعب، بلايستيشن، جيم، قاعة. */
    case DURATION = 'duration';

    /** خبرةٌ تُستشار، حضوريًا أو أونلاين. عيادة، محاماة، محاسبة. */
    case CONSULTATION = 'consultation';

    /** التحاقٌ ممتدّ. سنتر دروس، حضانة، أكاديمية. */
    case COURSE = 'course';

    /** وقتٌ مع النشاط نفسه، بلا وحدة. الحرفىّ والتاجر والكوافير. */
    case APPOINTMENT = 'appointment';

    /** الوحدة مفروضة — لا حجزَ بلا اختيارها. */
    public const UNIT_ALWAYS = 'always';

    /** الطفل يمتنع؛ النشاط يقرّر. */
    public const UNIT_OPTIONAL = 'optional';

    /** لا وحدة أصلًا — المحجوز هو النشاط. */
    public const UNIT_NEVER = 'never';

    public function label(): string
    {
        return match ($this) {
            self::STAY => 'إقامة',
            self::TABLE => 'طاولة',
            self::DURATION => 'مدّة',
            self::CONSULTATION => 'استشارة',
            self::COURSE => 'كورس',
            self::APPOINTMENT => 'موعد',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::STAY, self::TABLE => self::UNIT_ALWAYS,
            self::DURATION => self::UNIT_OPTIONAL,
            self::CONSULTATION, self::COURSE, self::APPOINTMENT => self::UNIT_NEVER,
        };
    }

    /**
     * كل ما تعرضه الشاشة على العميل — مطلوبًا كان أو اختياريًا.
     *
     * `visit_place` هو «فى المحل أم زيارةٌ عندك». وُضع حقلًا لا نمطًا سابعًا،
     * لأن السبّاك يذهب وتاجر الرخام لا — **والطفل الواحد فوقهما لا يعرف
     * أيّهما أنت**. لا يظهر للعميل إلا حين يعلن النشاط أنه يفعل الاثنين.
     */
    public function asks(): array
    {
        return match ($this) {
            self::STAY => ['date_range', 'guest_count', 'children_count', 'notes'],
            self::TABLE => ['datetime', 'party_size', 'notes'],
            self::DURATION => ['datetime', 'duration', 'quantity', 'notes'],
            self::CONSULTATION => ['datetime', 'channel', 'topic', 'notes'],
            self::COURSE => ['date_range', 'group', 'level', 'notes'],
            self::APPOINTMENT => ['datetime', 'visit_place', 'notes'],
        };
    }

    /** ما لا يُقبل الحجز بدونه. مجموعةٌ جزئية من asks(). */
    public function requires(): array
    {
        return match ($this) {
            self::STAY => ['date_range', 'guest_count'],
            self::TABLE => ['datetime', 'party_size'],
            self::DURATION => ['datetime', 'duration'],
            self::CONSULTATION => ['datetime', 'channel'],
            self::COURSE => ['date_range'],
            self::APPOINTMENT => ['datetime'],
        };
    }

    /**
     * المفاتيح الستّة القديمة، مشتقّةً من النمط بدل أن تُكتب يدويًا.
     *
     * تبقى فى الإعداد ليقرأها ما لم يُنقل بعد؛ ولا تُكتب من مكانٍ آخر.
     * و`requires_bookable_item` غائبٌ عمدًا حين يكون النمط `OPTIONAL` —
     * الامتناع لا يُكتب `false`، وإلا صار حكمًا.
     */
    public function legacyFlags(): array
    {
        $asks = $this->asks();

        $flags = [
            'requires_start_end' => (bool) array_intersect(['date_range', 'duration'], $this->requires()),
            'supports_quantity' => in_array('quantity', $asks, true),
            'supports_guest_count' => (bool) array_intersect(['guest_count', 'party_size'], $asks),
            'supports_extras' => false,
            'required_fields' => $this->requires(),
        ];

        if ($this->unit() !== self::UNIT_OPTIONAL) {
            $flags['requires_bookable_item'] = $this->unit() === self::UNIT_ALWAYS;
        }

        return $flags;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    public static function tryFromConfig(mixed $config): ?self
    {
        $config = is_string($config) ? json_decode($config, true) : $config;

        if (! is_array($config)) {
            return null;
        }

        return self::tryFrom((string) ($config['booking_pattern'] ?? ''));
    }
}
