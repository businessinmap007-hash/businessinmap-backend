<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceFee extends Model
{
    protected $table = 'service_fees';

    protected $fillable = ['code','amount','rules','is_active'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * code = business user.code
     * rules يمكن أن تحتوي Overrides لكل service_id.
     *
     * مثال rules (JSON):
     * {
     *   "services": {
     *     "12": 5,
     *     "15": 1
     *   }
     * }
     */
    public static function amountForBusinessAndService(?string $businessCode, ?int $serviceId): float
    {
        $businessCode = trim((string)$businessCode);
        $serviceId = (int)($serviceId ?? 0);

        if ($businessCode === '') return 0.0;

        $row = self::query()
            ->where('code', $businessCode)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();

        if (!$row) return 0.0;

        // 1) حاول override من rules
        $rulesRaw = (string)($row->rules ?? '');
        if ($serviceId > 0 && $rulesRaw !== '') {
            $json = json_decode($rulesRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $services = $json['services'] ?? null;
                if (is_array($services)) {
                    $k = (string)$serviceId;
                    if (isset($services[$k]) && is_numeric($services[$k])) {
                        return (float)$services[$k];
                    }
                }
            }
        }

        // 2) fallback: amount العام للبزنس
        return (float)($row->amount ?? 0);
    }
}