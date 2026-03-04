<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    // ===== Core wallet operations =====
    case DEPOSIT       = 'deposit';        // إضافة رصيد
    case WITHDRAW      = 'withdraw';       // سحب رصيد

    // ===== Escrow / Deposits =====
    case HOLD          = 'hold';            // تجميد مبلغ
    case RELEASE       = 'release';         // فك تجميد
    case REFUND        = 'refund';          // إرجاع

    // ===== Platform fees =====
    case SERVICE_FEE   = 'service_fee';     // رسوم خدمة (على مقدم الخدمة)
    case COMMISSION    = 'commission';      // عمولة (لو استخدمت لاحقاً)

    // ===== Adjustments / Admin =====
    case ADJUSTMENT    = 'adjustment';      // تصحيح يدوي
    case REVERSAL      = 'reversal';        // عكس عملية

    /**
     * Helper: get all values (useful for validation)
     */
    public static function values(): array
    {
        return array_map(fn(self $e) => $e->value, self::cases());
    }
}
