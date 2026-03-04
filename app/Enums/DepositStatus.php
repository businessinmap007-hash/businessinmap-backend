<?php

namespace App\Enums;

enum DepositStatus: string
{
    /**
     * تم إنشاء الـ deposit وتجميد المبالغ
     */
    case FROZEN = 'frozen';

    /**
     * تم بدء التنفيذ (بعد خصم رسوم الخدمة)
     * اختياري لو حاب تضيف مرحلة واضحة للتنفيذ
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * تم فك التجميد وإرجاع المبالغ للطرفين
     */
    case RELEASED = 'released';

    /**
     * تم إرجاع المبالغ (كليًا أو جزئيًا)
     */
    case REFUNDED = 'refunded';

    /**
     * Helper: كل القيم كنصوص (للفاليديشن)
     */
    public static function values(): array
    {
        return array_map(fn(self $e) => $e->value, self::cases());
    }

    /**
     * Helper: الحالات التي يُسمح فيها بالتصرف (release / refund)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::RELEASED,
            self::REFUNDED,
        ], true);
    }
}
