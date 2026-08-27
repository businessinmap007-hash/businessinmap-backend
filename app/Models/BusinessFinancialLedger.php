<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفٌّ متراكم واحد لكل (نشاط، مصدر) — راجع migration الإنشاء ولماذا هو
 * تراكمى لا تجميعى. {@see \App\Services\FinancialLedgerService}.
 */
class BusinessFinancialLedger extends Model
{
    public const SOURCE_TOTAL = 'total';
    public const SOURCE_MENU = 'menu';
    public const SOURCE_RETAIL = 'retail';
    public const SOURCE_BOOKING = 'booking';

    public const SOURCES = [self::SOURCE_MENU, self::SOURCE_RETAIL, self::SOURCE_BOOKING];

    protected $fillable = [
        'business_id',
        'source',
        'revenue_total',
        'cost_of_goods_total',
        'platform_fees_total',
        'operations_count',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'revenue_total' => 'decimal:2',
        'cost_of_goods_total' => 'decimal:2',
        'platform_fees_total' => 'decimal:2',
        'operations_count' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /** المكسب = الوارد - (تكلفة البضاعة + رسوم المنصة). حسابٌ فورى، لا تجميع. */
    public function profitTotal(): float
    {
        return round(
            (float) $this->revenue_total - (float) $this->cost_of_goods_total - (float) $this->platform_fees_total,
            2
        );
    }
}
