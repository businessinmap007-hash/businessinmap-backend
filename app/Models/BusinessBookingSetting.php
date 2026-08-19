<?php

namespace App\Models;

use App\Enums\BookingPattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ما قرّره صاحبُ النشاط داخل النمط الذى فتحه له الطفل.
 *
 * كل عمودٍ هنا يقبل NULL، وNULL تعنى «خذ ما يقوله النمط». فالنشاط الذى لم
 * يفتح الشاشة قطّ يسلك سلوك نمطه بالضبط، والصفُّ لا يوجد أصلًا حتى يقرّر
 * صاحبه شيئًا — ولهذا `resolve()` ثابتةٌ تقبل `null` بدل أن تكون تابعًا:
 * أكثرُ الأنشطة لن يكون لها صفّ، وهذه هى الحال السليمة لا الناقصة.
 */
class BusinessBookingSetting extends Model
{
    public const VISIT_AT_BUSINESS = 'at_business';

    public const VISIT_AT_CUSTOMER = 'at_customer';

    public const VISIT_BOTH = 'both';

    public const CHANNEL_IN_PERSON = 'in_person';

    public const CHANNEL_ONLINE = 'online';

    protected $fillable = [
        'business_id',
        'pattern',
        'uses_units',
        'slot_minutes',
        'min_nights',
        'lead_time_minutes',
        'visit_mode',
        'channels',
        'asks',
        'requires',
        'notes_label',
    ];

    /**
     * كل عمودٍ حاضرٌ بقيمة null على نسخةٍ جديدة.
     *
     * الشاشة تُفتح على `firstOrNew`، ونمطُ العرض هو `old(.., $row->x)` — ومع
     * منع الوصول إلى خاصّيةٍ غائبة يصير كل حقلٍ لم يُحفظ قطّ استثناءً بدل أن
     * يكون فراغًا. والفراغ هنا معنًى أصيل: «خذ ما يقوله النمط».
     */
    protected $attributes = [
        'pattern' => null,
        'uses_units' => null,
        'slot_minutes' => null,
        'min_nights' => null,
        'lead_time_minutes' => null,
        'visit_mode' => null,
        'channels' => null,
        'asks' => null,
        'requires' => null,
        'notes_label' => null,
    ];

    protected $casts = [
        'business_id' => 'integer',
        'uses_units' => 'boolean',
        'slot_minutes' => 'integer',
        'min_nights' => 'integer',
        'lead_time_minutes' => 'integer',
        'channels' => 'array',
        'asks' => 'array',
        'requires' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public static function visitModes(): array
    {
        return [self::VISIT_AT_BUSINESS, self::VISIT_AT_CUSTOMER, self::VISIT_BOTH];
    }

    public static function channels(): array
    {
        return [self::CHANNEL_IN_PERSON, self::CHANNEL_ONLINE];
    }

    public function pattern(): ?BookingPattern
    {
        return BookingPattern::tryFrom((string) $this->pattern);
    }

    /**
     * الشكل النهائى لشاشة الحجز: النمط، ثم ما عدّله صاحبُ النشاط فوقه.
     *
     * هذه هى الدالّة التى ستقرأها نقطةُ «شكل الشاشة» (الخطوة ٥) ويفرضها
     * المحرّك (الخطوة ٤). تُبقى القرارَ فى مكانٍ واحد بدل أن يتكرّر منطقُ
     * الدمج فى كل قارئ — وهو الخطأ نفسه الذى صنع ثمانيةَ مفاتيح متفرّقة.
     *
     * @param  BookingPattern  $pattern  ما يعلنه الطفل — الأساسى أو ما اختاره النشاط منه
     */
    public static function resolve(BookingPattern $pattern, ?self $row): array
    {
        $asks = $pattern->asks();
        $requires = $pattern->requires();

        // «زيارةٌ عندك» لا تُعرض إلا على من يفعل الاثنين.
        if ($row?->visit_mode !== self::VISIT_BOTH) {
            $asks = array_values(array_diff($asks, ['visit_place']));
        }

        $unit = $pattern->unit();

        if ($unit === BookingPattern::UNIT_OPTIONAL && $row?->uses_units !== null) {
            $unit = $row->uses_units ? BookingPattern::UNIT_ALWAYS : BookingPattern::UNIT_NEVER;
        }

        if ($row) {
            // النشاط يضيف ولا يحذف: ما يعلنه النمط مطلوبًا يبقى مطلوبًا.
            $asks = array_values(array_unique(array_merge($asks, $row->asks ?? [])));
            $requires = array_values(array_unique(array_merge($requires, $row->requires ?? [])));
            $requires = array_values(array_intersect($requires, $asks));
        }

        return [
            'pattern' => $pattern->value,
            'label' => $pattern->label(),
            'unit' => $unit,
            'asks' => $asks,
            'requires' => $requires,
            'slot_minutes' => $row?->slot_minutes,
            'min_nights' => $row?->min_nights,
            'lead_time_minutes' => $row?->lead_time_minutes,
            'visit_mode' => $row?->visit_mode ?? self::VISIT_AT_BUSINESS,
            'channels' => $row?->channels ?: ($pattern === BookingPattern::CONSULTATION ? self::channels() : []),
            'notes_label' => $row?->notes_label,
        ];
    }
}
