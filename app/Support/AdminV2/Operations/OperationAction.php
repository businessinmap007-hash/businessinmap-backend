<?php

namespace App\Support\AdminV2\Operations;

final class OperationAction
{
    public const ACCEPT = 'accept';
    public const REJECT = 'reject';
    public const CANCEL = 'cancel';

    public const CONFIRM_CLIENT = 'confirm_client';
    public const CONFIRM_BUSINESS = 'confirm_business';

    public const FREEZE_DEPOSIT = 'freeze_deposit';
    public const RELEASE_DEPOSIT = 'release_deposit';
    public const REFUND_DEPOSIT = 'refund_deposit';

    public const START = 'start';
    public const COMPLETE = 'complete';

    public const CHARGE_FEES = 'charge_fees';

    public const OPEN_DISPUTE = 'open_dispute';
    public const CLOSE_DISPUTE = 'close_dispute';

    public const VIEW = 'view';
    public const EDIT = 'edit';

    public static function all(): array
    {
        return [
            self::ACCEPT,
            self::REJECT,
            self::CANCEL,

            self::CONFIRM_CLIENT,
            self::CONFIRM_BUSINESS,

            self::FREEZE_DEPOSIT,
            self::RELEASE_DEPOSIT,
            self::REFUND_DEPOSIT,

            self::START,
            self::COMPLETE,

            self::CHARGE_FEES,

            self::OPEN_DISPUTE,
            self::CLOSE_DISPUTE,

            self::VIEW,
            self::EDIT,
        ];
    }

    public static function labels(): array
    {
        return [
            self::ACCEPT => 'قبول',
            self::REJECT => 'رفض',
            self::CANCEL => 'إلغاء',

            self::CONFIRM_CLIENT => 'تأكيد العميل',
            self::CONFIRM_BUSINESS => 'تأكيد البزنس',

            self::FREEZE_DEPOSIT => 'تجميد الـ Deposit',
            self::RELEASE_DEPOSIT => 'Release للـ Deposit',
            self::REFUND_DEPOSIT => 'Refund للـ Deposit',

            self::START => 'بدء التنفيذ',
            self::COMPLETE => 'إنهاء العملية',

            self::CHARGE_FEES => 'خصم الرسوم',

            self::OPEN_DISPUTE => 'فتح نزاع',
            self::CLOSE_DISPUTE => 'إغلاق النزاع',

            self::VIEW => 'عرض',
            self::EDIT => 'تعديل',
        ];
    }

    public static function tones(): array
    {
        return [
            self::ACCEPT => 'success',
            self::REJECT => 'danger',
            self::CANCEL => 'danger',

            self::CONFIRM_CLIENT => 'info',
            self::CONFIRM_BUSINESS => 'info',

            self::FREEZE_DEPOSIT => 'warning',
            self::RELEASE_DEPOSIT => 'success',
            self::REFUND_DEPOSIT => 'warning',

            self::START => 'success',
            self::COMPLETE => 'success',

            self::CHARGE_FEES => 'warning',

            self::OPEN_DISPUTE => 'danger',
            self::CLOSE_DISPUTE => 'success',

            self::VIEW => 'info',
            self::EDIT => 'info',
        ];
    }

    public static function label(string $action): string
    {
        return self::labels()[$action] ?? $action;
    }

    public static function tone(string $action): string
    {
        return self::tones()[$action] ?? 'info';
    }

    public static function exists(?string $action): bool
    {
        if (! $action) {
            return false;
        }

        return in_array($action, self::all(), true);
    }

    public static function normalize(?string $action): ?string
    {
        $action = trim((string) $action);

        if ($action === '') {
            return null;
        }

        return self::exists($action) ? $action : null;
    }

    public static function isDanger(string $action): bool
    {
        return self::tone($action) === 'danger';
    }

    public static function isSuccess(string $action): bool
    {
        return self::tone($action) === 'success';
    }

    public static function isFinancial(string $action): bool
    {
        return in_array($action, [
            self::FREEZE_DEPOSIT,
            self::RELEASE_DEPOSIT,
            self::REFUND_DEPOSIT,
            self::CHARGE_FEES,
        ], true);
    }

    public static function isConfirmation(string $action): bool
    {
        return in_array($action, [
            self::CONFIRM_CLIENT,
            self::CONFIRM_BUSINESS,
        ], true);
    }

    public static function toArray(string $action): array
    {
        return [
            'key' => $action,
            'label' => self::label($action),
            'tone' => self::tone($action),
            'is_financial' => self::isFinancial($action),
            'is_confirmation' => self::isConfirmation($action),
            'is_danger' => self::isDanger($action),
            'is_success' => self::isSuccess($action),
        ];
    }

    public static function listFor(array $actions): array
    {
        return collect($actions)
            ->map(fn ($action) => self::normalize((string) $action))
            ->filter()
            ->unique()
            ->map(fn ($action) => self::toArray($action))
            ->values()
            ->all();
    }
}