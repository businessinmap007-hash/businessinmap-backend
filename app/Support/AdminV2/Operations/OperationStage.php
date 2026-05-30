<?php

namespace App\Support\AdminV2\Operations;

final class OperationStage
{
    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public const DEPOSIT_REQUIRED = 'deposit_required';
    public const DEPOSIT_FROZEN = 'deposit_frozen';

    public const AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const READY_TO_START = 'ready_to_start';

    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';

    public const DISPUTED = 'disputed';
    public const CLOSED = 'closed';

    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING,
            self::ACCEPTED,
            self::REJECTED,
            self::CANCELLED,

            self::DEPOSIT_REQUIRED,
            self::DEPOSIT_FROZEN,

            self::AWAITING_CONFIRMATION,
            self::READY_TO_START,

            self::IN_PROGRESS,
            self::COMPLETED,

            self::DISPUTED,
            self::CLOSED,

            self::UNKNOWN,
        ];
    }

    public static function labels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',

            self::DEPOSIT_REQUIRED => 'Deposit Required',
            self::DEPOSIT_FROZEN => 'Deposit Frozen',

            self::AWAITING_CONFIRMATION => 'Awaiting Confirmation',
            self::READY_TO_START => 'Ready To Start',

            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',

            self::DISPUTED => 'Disputed',
            self::CLOSED => 'Closed',

            self::UNKNOWN => 'Unknown',
        ];
    }

    public static function arabicLabels(): array
    {
        return [
            self::DRAFT => 'مسودة',
            self::PENDING => 'قيد الانتظار',
            self::ACCEPTED => 'مقبول',
            self::REJECTED => 'مرفوض',
            self::CANCELLED => 'ملغي',

            self::DEPOSIT_REQUIRED => 'يتطلب Deposit',
            self::DEPOSIT_FROZEN => 'Deposit مجمد',

            self::AWAITING_CONFIRMATION => 'بانتظار التأكيد',
            self::READY_TO_START => 'جاهز للبدء',

            self::IN_PROGRESS => 'قيد التنفيذ',
            self::COMPLETED => 'مكتمل',

            self::DISPUTED => 'يوجد نزاع',
            self::CLOSED => 'مغلق',

            self::UNKNOWN => 'غير معروف',
        ];
    }

    public static function tones(): array
    {
        return [
            self::DRAFT => 'muted',
            self::PENDING => 'warning',
            self::ACCEPTED => 'info',
            self::REJECTED => 'danger',
            self::CANCELLED => 'danger',

            self::DEPOSIT_REQUIRED => 'warning',
            self::DEPOSIT_FROZEN => 'info',

            self::AWAITING_CONFIRMATION => 'warning',
            self::READY_TO_START => 'success',

            self::IN_PROGRESS => 'primary',
            self::COMPLETED => 'success',

            self::DISPUTED => 'danger',
            self::CLOSED => 'muted',

            self::UNKNOWN => 'muted',
        ];
    }

    public static function label(string $stage, bool $arabic = true): string
    {
        $stage = self::normalize($stage) ?: self::UNKNOWN;

        return $arabic
            ? (self::arabicLabels()[$stage] ?? $stage)
            : (self::labels()[$stage] ?? $stage);
    }

    public static function tone(string $stage): string
    {
        $stage = self::normalize($stage) ?: self::UNKNOWN;

        return self::tones()[$stage] ?? 'muted';
    }

    public static function exists(?string $stage): bool
    {
        if (! $stage) {
            return false;
        }

        return in_array($stage, self::all(), true);
    }

    public static function normalize(?string $stage): ?string
    {
        $stage = trim((string) $stage);

        if ($stage === '') {
            return null;
        }

        return self::exists($stage) ? $stage : self::UNKNOWN;
    }

    public static function isFinal(string $stage): bool
    {
        return in_array(self::normalize($stage), [
            self::REJECTED,
            self::CANCELLED,
            self::COMPLETED,
            self::CLOSED,
        ], true);
    }

    public static function isActive(string $stage): bool
    {
        return in_array(self::normalize($stage), [
            self::PENDING,
            self::ACCEPTED,
            self::DEPOSIT_REQUIRED,
            self::DEPOSIT_FROZEN,
            self::AWAITING_CONFIRMATION,
            self::READY_TO_START,
            self::IN_PROGRESS,
            self::DISPUTED,
        ], true);
    }

    public static function needsAction(string $stage): bool
    {
        return in_array(self::normalize($stage), [
            self::PENDING,
            self::DEPOSIT_REQUIRED,
            self::AWAITING_CONFIRMATION,
            self::READY_TO_START,
            self::DISPUTED,
        ], true);
    }

    public static function toArray(string $stage): array
    {
        $stage = self::normalize($stage) ?: self::UNKNOWN;

        return [
            'key' => $stage,
            'label' => self::label($stage, false),
            'label_ar' => self::label($stage, true),
            'tone' => self::tone($stage),
            'is_final' => self::isFinal($stage),
            'is_active' => self::isActive($stage),
            'needs_action' => self::needsAction($stage),
        ];
    }

    public static function listAll(): array
    {
        return collect(self::all())
            ->map(fn ($stage) => self::toArray($stage))
            ->values()
            ->all();
    }
}