<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dine-in customer's call for staff from a table (BIM-13.3). See
 * TableServiceCallService for the create/resolve rules.
 */
class TableServiceCall extends Model
{
    public const TYPE_WAITER = 'waiter';
    public const TYPE_BILL = 'bill';
    public const TYPE_ASSISTANCE = 'assistance';

    public const TYPES = [self::TYPE_WAITER, self::TYPE_BILL, self::TYPE_ASSISTANCE];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RESOLVED = 'resolved';

    /** Arabic label per type — used in notification bodies. */
    public const TYPE_LABELS_AR = [
        self::TYPE_WAITER => 'نداء الطاقم',
        self::TYPE_BILL => 'طلب الحساب',
        self::TYPE_ASSISTANCE => 'طلب مساعدة',
    ];

    public const TYPE_LABELS_EN = [
        self::TYPE_WAITER => 'Waiter call',
        self::TYPE_BILL => 'Bill request',
        self::TYPE_ASSISTANCE => 'Assistance',
    ];

    protected $fillable = [
        'business_id',
        'business_table_id',
        'user_id',
        'type',
        'status',
        'note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'business_table_id' => 'integer',
        'user_id' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function labelAr(): string
    {
        return self::TYPE_LABELS_AR[$this->type] ?? $this->type;
    }

    public function labelEn(): string
    {
        return self::TYPE_LABELS_EN[$this->type] ?? $this->type;
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(BusinessTable::class, 'business_table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
