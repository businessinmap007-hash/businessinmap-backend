<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeFee extends Model
{
    protected $fillable = [
        'platform_service_id',
        'amount',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'platform_service_id' => 'integer',
        'amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    /**
     * The session price for a service: its own row, else the fallback row with
     * no service, else zero.
     *
     * The fallback exists so a service added later is never silently free —
     * a session that costs nothing is one nobody hesitates to demand.
     */
    public static function amountFor(?int $platformServiceId): int
    {
        $rows = static::query()->where('is_active', true)->get();

        $own = $platformServiceId
            ? $rows->firstWhere('platform_service_id', $platformServiceId)
            : null;

        return (int) ($own->amount ?? $rows->firstWhere('platform_service_id', null)?->amount ?? 0);
    }
}
