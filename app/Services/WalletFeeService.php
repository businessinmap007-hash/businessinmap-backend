<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletFeeService
{
    public const REFERENCE_TYPE_BOOKING = 'booking';

    public const TX_STATUS_COMPLETED = 'completed';
    public const TX_DIRECTION_OUT = 'out';
    public const TX_TYPE_PLATFORM_FEE = 'platform_fee';

    public const DEFAULT_FEE_CODE = CategoryChildServiceFee::DEFAULT_FEE_CODE;

    public function resolveBookingFees(Booking $booking, string $feeCode = self::DEFAULT_FEE_CODE): Collection
    {
        $feeCode = $this->normalizeFeeCode($feeCode);

        $booking->loadMissing([
            'business:id,name,category_child_id',
            'business.serviceFeeConsent',
            'service:id,key,name_ar,name_en,is_active,business_fee_enabled,business_fee_type,business_fee_value,client_fee_enabled,client_fee_type,client_fee_value,fee_currency,fee_notes',
            'user:id,name',
            'user.serviceFeeConsent',
        ]);

        $baseAmount = $this->resolveBookingBaseAmount($booking);
        $childId = $this->resolveBookingChildId($booking);
        $serviceId = (int) $booking->service_id;

        if ($serviceId <= 0) {
            return collect();
        }

        $service = $booking->service;

        if (! $service) {
            $service = PlatformService::query()
                ->find($serviceId);
        }

        if (! $service || ! (bool) ($service->is_active ?? false)) {
            return collect();
        }

        $feeRow = null;

        if ($childId > 0) {
            $feeRow = CategoryChildServiceFee::activeForPair($childId, $serviceId);
        }

        $lines = collect();

        $business = $booking->business;

        if ($business && $this->canAutoChargeFees($business)) {
            $line = $this->resolveFeeLineForPayer(
                payer: CategoryChildServiceFee::PAYER_BUSINESS,
                booking: $booking,
                service: $service,
                feeRow: $feeRow,
                baseAmount: $baseAmount,
                childId: $childId,
                feeCode: $feeCode
            );

            if ($line) {
                $line['consent_checked'] = true;
                $line['consent_enabled'] = true;
                $lines->push($line);
            }
        }

        $client = $booking->user;

        if ($client && $this->canAutoChargeFees($client)) {
            $line = $this->resolveFeeLineForPayer(
                payer: CategoryChildServiceFee::PAYER_CLIENT,
                booking: $booking,
                service: $service,
                feeRow: $feeRow,
                baseAmount: $baseAmount,
                childId: $childId,
                feeCode: $feeCode
            );

            if ($line) {
                $line['consent_checked'] = true;
                $line['consent_enabled'] = true;
                $lines->push($line);
            }
        }

        return $lines->values();
    }

        protected function resolveFeeLineForPayer(
        string $payer,
        Booking $booking,
        PlatformService $service,
        ?CategoryChildServiceFee $feeRow,
        float $baseAmount,
        int $childId,
        string $feeCode
    ): ?array {
        $payer = CategoryChildServiceFee::normalizePayer($payer);

        if (! $payer) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) Category Child Override First
        |--------------------------------------------------------------------------
        */
        if ($feeRow && $feeRow->isChargeableFor($payer)) {
            return $feeRow->toWalletFeeLine(
                payer: $payer,
                userId: $payer === CategoryChildServiceFee::PAYER_BUSINESS
                    ? (int) $booking->business_id
                    : (int) $booking->user_id,
                baseAmount: $baseAmount,
                bookingId: (int) $booking->id,
                businessId: (int) $booking->business_id,
                clientId: (int) $booking->user_id,
                feeCode: $feeCode
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Platform Service Default Fallback
        |--------------------------------------------------------------------------
        */
        $snapshot = $service->defaultFeeSnapshotFor(
            payer: $payer,
            baseAmount: $baseAmount
        );

        if (! $snapshot) {
            return null;
        }

        $amount = round((float) ($snapshot['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'payer' => $payer,
            'user_id' => $payer === CategoryChildServiceFee::PAYER_BUSINESS
                ? (int) $booking->business_id
                : (int) $booking->user_id,

            'category_child_service_fee_id' => null,
            'service_fee_id' => null,
            'fee_row_id' => null,
            'source' => 'platform_service_default',

            'fee_code' => $feeCode,
            'fee_type' => $snapshot['fee_type'] ?? null,
            'calc_type' => $snapshot['calc_type'] ?? CategoryChildServiceFee::CALC_TYPE_FIXED,
            'rate_value' => $snapshot['rate_value'] ?? null,

            'amount' => $amount,
            'currency' => $snapshot['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY,
            'base_amount' => round((float) $baseAmount, 2),

            'booking_id' => (int) $booking->id,
            'service_id' => (int) $booking->service_id,
            'platform_service_id' => (int) $booking->service_id,

            'business_id' => (int) $booking->business_id,
            'client_id' => (int) $booking->user_id,
            'child_id' => $childId > 0 ? $childId : null,

            'rules' => null,
            'notes' => $snapshot['notes'] ?? null,
        ];
    }

    public function applyBookingFees(Booking $booking, string $feeCode = self::DEFAULT_FEE_CODE): Collection
    {
        $feeCode = $this->normalizeFeeCode($feeCode);

        $booking->loadMissing([
            'user:id,name',
            'user.serviceFeeConsent',
            'business:id,name,category_child_id',
            'business.serviceFeeConsent',
            'service:id,name_ar,name_en,key,is_active,business_fee_enabled,business_fee_type,business_fee_value,client_fee_enabled,client_fee_type,client_fee_value,fee_currency,fee_notes',
        ]);

        $lines = $this->resolveBookingFees($booking, $feeCode);

        if ($lines->isEmpty()) {
            return collect();
        }

        return DB::transaction(function () use ($booking, $feeCode, $lines) {
            $transactions = collect();

            foreach ($lines as $line) {
                $transactions->push(
                    $this->createWalletFeeTransaction(
                        booking: $booking,
                        feeCode: $feeCode,
                        payer: (string) $line['payer'],
                        userId: (int) $line['user_id'],
                        amount: (float) $line['amount'],
                        line: $line
                    )
                );
            }

            return $transactions;
        });
    }

    protected function createWalletFeeTransaction(
        Booking $booking,
        string $feeCode,
        string $payer,
        int $userId,
        float $amount,
        array $line
    ): WalletTransaction {
        $feeCode = $this->normalizeFeeCode($feeCode);
        $payer = CategoryChildServiceFee::normalizePayer($payer) ?: $payer;

        $idempotencyKey = $this->buildIdempotencyKey(
            bookingId: (int) $booking->id,
            feeCode: $feeCode,
            payer: $payer
        );

        $existing = WalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $user = User::query()
            ->with('serviceFeeConsent')
            ->find($userId);

        if (! $user) {
            throw new RuntimeException("المستخدم رقم {$userId} غير موجود.");
        }

        if (! $this->canAutoChargeFees($user)) {
            throw new RuntimeException("المستخدم رقم {$userId} لم يوافق على خصم رسوم الخدمة تلقائيًا.");
        }

        $wallet = $this->getActiveWalletForUser($userId);

        $balanceBefore = round((float) $wallet->balance, 2);
        $lockedBefore = round((float) $wallet->locked_balance, 2);
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('قيمة الرسوم غير صالحة.');
        }

        if ($balanceBefore < $amount) {
            throw new RuntimeException(
                "رصيد المستخدم رقم {$userId} غير كافٍ لتطبيق رسوم {$payer} على الحجز #{$booking->id}"
            );
        }

        $balanceAfter = round($balanceBefore - $amount, 2);
        $lockedAfter = $lockedBefore;

        $wallet->balance = $balanceAfter;
        $wallet->total_out = round(((float) $wallet->total_out) + $amount, 2);
        $wallet->last_activity_at = now();
        $wallet->save();

        return WalletTransaction::create([
            'wallet_id' => (int) $wallet->id,
            'user_id' => $userId,

            'status' => self::TX_STATUS_COMPLETED,
            'direction' => self::TX_DIRECTION_OUT,
            'type' => self::TX_TYPE_PLATFORM_FEE,

            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'locked_before' => $lockedBefore,
            'locked_after' => $lockedAfter,

            'reference_type' => self::REFERENCE_TYPE_BOOKING,
            'reference_id' => (string) $booking->id,
            'idempotency_key' => $idempotencyKey,

            'note' => $this->buildHumanNote($feeCode, $payer, $booking),

            'meta' => $this->buildTransactionMeta(
                booking: $booking,
                feeCode: $feeCode,
                payer: $payer,
                line: $line
            ),
        ]);
    }
        protected function buildTransactionMeta(
        Booking $booking,
        string $feeCode,
        string $payer,
        array $line
    ): array {
        return [
            'context' => 'booking_fee',
            'payer' => $payer,

            'fee_code' => $feeCode,
            'fee_type' => $line['fee_type'] ?? null,
            'calc_type' => $line['calc_type'] ?? CategoryChildServiceFee::CALC_TYPE_FIXED,
            'rate_value' => $line['rate_value'] ?? null,
            'source' => $line['source'] ?? 'category_child_override',
            'currency' => $line['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY,
            'base_amount' => round((float) ($line['base_amount'] ?? 0), 2),

            'category_child_service_fee_id' => $line['category_child_service_fee_id'] ?? null,
            'service_fee_id' => $line['service_fee_id'] ?? null,
            'fee_row_id' => $line['fee_row_id'] ?? null,

            'booking_id' => (int) $booking->id,
            'service_id' => (int) $booking->service_id,
            'platform_service_id' => (int) ($line['platform_service_id'] ?? $booking->service_id),

            'business_id' => (int) $booking->business_id,
            'client_id' => (int) $booking->user_id,
            'child_id' => (int) ($line['child_id'] ?? $this->resolveBookingChildId($booking)),

            'consent_checked' => (bool) ($line['consent_checked'] ?? true),
            'consent_enabled' => (bool) ($line['consent_enabled'] ?? true),

            'rules' => $line['rules'] ?? null,
            'notes' => $line['notes'] ?? null,
        ];
    }

    protected function canAutoChargeFees(User $user): bool
    {
        if (method_exists($user, 'hasFeeAutoChargeEnabled')) {
            return $user->hasFeeAutoChargeEnabled();
        }

        if (! $user->relationLoaded('serviceFeeConsent')) {
            $user->load('serviceFeeConsent');
        }

        return (bool) optional($user->serviceFeeConsent)->fee_auto_charge_enabled;
    }

    protected function getActiveWalletForUser(int $userId): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            throw new RuntimeException("لا توجد محفظة للمستخدم رقم {$userId}");
        }

        if ((string) $wallet->status === 'blocked') {
            throw new RuntimeException("محفظة المستخدم رقم {$userId} محظورة");
        }

        return $wallet;
    }

    protected function resolveBookingBaseAmount(Booking $booking): float
    {
        $price = $booking->price ?? null;

        if ($price === null || (float) $price <= 0) {
            $price = $booking->total_price ?? 0;
        }

        return round((float) $price, 2);
    }

    protected function resolveBookingChildId(Booking $booking): int
    {
        $businessChildId = (int) ($booking->business?->category_child_id ?? 0);

        if ($businessChildId > 0) {
            return $businessChildId;
        }

        $metaChildId = (int) data_get($booking->meta, 'business_context.category_child_id', 0);

        if ($metaChildId > 0) {
            return $metaChildId;
        }

        $metaExecutionChildId = (int) data_get($booking->meta, '_execution_fee.child_id', 0);

        if ($metaExecutionChildId > 0) {
            return $metaExecutionChildId;
        }

        $metaPlatformFeeChildId = (int) data_get($booking->meta, 'platform_service_fee.child_id', 0);

        if ($metaPlatformFeeChildId > 0) {
            return $metaPlatformFeeChildId;
        }

        return 0;
    }

    protected function buildIdempotencyKey(int $bookingId, string $feeCode, string $payer): string
    {
        return 'booking_fee:' . $bookingId . ':' . $this->normalizeFeeCode($feeCode) . ':' . $payer;
    }

    protected function buildHumanNote(string $feeCode, string $payer, Booking $booking): string
    {
        $serviceName = (string) (
            $booking->service?->name_ar
            ?: $booking->service?->name_en
            ?: $booking->service?->key
            ?: ('Service #' . $booking->service_id)
        );

        $payerLabel = match ($payer) {
            CategoryChildServiceFee::PAYER_BUSINESS => 'البزنس',
            CategoryChildServiceFee::PAYER_CLIENT => 'العميل',
            default => $payer,
        };

        return "خصم رسوم {$feeCode} ({$payerLabel}) للحجز #{$booking->id} - {$serviceName}";
    }

    protected function normalizeFeeCode(?string $feeCode): string
    {
        $feeCode = trim((string) $feeCode);

        return $feeCode !== ''
            ? $feeCode
            : self::DEFAULT_FEE_CODE;
    }
}