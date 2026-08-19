<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One coordinate of a priced offering: either WHAT is sold or what qualifies it.
 *
 * @see \App\Models\Concerns\HasOfferingOptions
 */
class OfferingOption extends Model
{
    protected $table = 'offering_options';

    public const ROLE_LINE = 'line';
    public const ROLE_MODIFIER = 'modifier';

    /** مبلغٌ ثابت يُضاف إلى سعر الوحدة. */
    public const ADJUST_AMOUNT = 'amount';

    /** نسبةٌ من سعر الوحدة قبل أىِّ زيادةٍ أخرى. */
    public const ADJUST_PERCENT = 'percent';

    protected $fillable = [
        'offering_type',
        'offering_id',
        'option_id',
        'role',
        'adjust_type',
        'adjust_value',
        'sort_order',
    ];

    protected $casts = [
        'offering_id' => 'integer',
        'option_id' => 'integer',
        'adjust_value' => 'float',
        'sort_order' => 'integer',
    ];

    public static function adjustTypes(): array
    {
        return [self::ADJUST_AMOUNT, self::ADJUST_PERCENT];
    }

    /**
     * ما يضيفه هذا المُوصِّف على سعر وحدةٍ واحدة.
     *
     * النسبةُ تُحسب من السعر الأصلىِّ للوحدة لا من المتراكم، فلا يعتمد الناتج
     * على ترتيب اختيار العميل: «+١٠٪» و«+٢٠ جنيهًا» معًا تعطيان الرقمَ نفسه
     * أيًّا كان أيُّهما أوّلًا.
     */
    public function appliedTo(float $unitPrice): float
    {
        if ((string) $this->adjust_type === self::ADJUST_PERCENT) {
            return round($unitPrice * ((float) $this->adjust_value / 100), 2);
        }

        return round((float) $this->adjust_value, 2);
    }

    public function offering(): MorphTo
    {
        return $this->morphTo();
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }
}
