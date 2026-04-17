<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
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

    /**
     * يجلب رسوم التنفيذ الفعالة الخاصة بالحجز
     * من category_child_service_fees ويعيد line لكل payer (business / client)
     */
    public function resolveBookingFees(Booking $booking, string $feeCode = 'booking_execution'): Collection
    {
        $booking->loadMissing([
            'business:id,name,category_child_id',
            'service:id,key,name_ar,name_en',
            'user:id,name',
        ]);

        $baseAmount = round((float) $booking->price, 2);
        $childId    = $this->resolveBookingChildId($booking);
        $serviceId  = (int) $booking->service_id;

        if ($childId <= 0 || $serviceId <= 0) {
            return collect();
        }

        $row = CategoryChildServiceFee::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (! $row) {
            return collect();
        }

        $lines = collect();

        if ($row->hasBusinessFee()) {
            $amount = round((float) $row->business_fee_amount, 2);

            if ($amount > 0) {
                $lines->push([
                    'payer' => 'business',
                    'user_id' => (int) $booking->business_id,

                    'category_child_service_fee_id' => (int) $row->id,
                    'service_fee_id' => (int) $row->id, // توافق مؤقت مع أي meta قديم
                    'fee_code' => (string) $feeCode,
                    'fee_type' => 'business_fee',
                    'calc_type' => 'fixed',
                    'amount' => $amount,
                    'currency' => (string) ($row->currency ?: 'EGP'),
                    'base_amount' => $baseAmount,

                    'booking_id' => (int) $booking->id,
                    'service_id' => $serviceId,
                    'business_id' => (int) $booking->business_id,
                    'client_id' => (int) $booking->user_id,
                    'child_id' => $childId,

                    'rules' => null,
                    'notes' => $row->notes,
                ]);
            }
        }

        if ($row->hasClientFee()) {
            $amount = round((float) $row->client_fee_amount, 2);

            if ($amount > 0) {
                $lines->push([
                    'payer' => 'client',
                    'user_id' => (int) $booking->user_id,

                    'category_child_service_fee_id' => (int) $row->id,
                    'service_fee_id' => (int) $row->id, // توافق مؤقت مع أي meta قديم
                    'fee_code' => (string) $feeCode,
                    'fee_type' => 'client_fee',
                    'calc_type' => 'fixed',
                    'amount' => $amount,
                    'currency' => (string) ($row->currency ?: 'EGP'),
                    'base_amount' => $baseAmount,

                    'booking_id' => (int) $booking->id,
                    'service_id' => $serviceId,
                    'business_id' => (int) $booking->business_id,
                    'client_id' => (int) $booking->user_id,
                    'child_id' => $childId,

                    'rules' => null,
                    'notes' => $row->notes,
                ]);
            }
        }

        return $lines->values();
    }

    /**
     * يطبق الرسوم على المحافظ ويعيد wallet transactions التي أُنشئت
     */
    public function applyBookingFees(Booking $booking, string $feeCode = 'booking_execution'): Collection
    {
        $booking->loadMissing([
            'user:id,name',
            'business:id,name,category_child_id',
            'service:id,name_ar,name_en,key',
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

    /**
     * ينشئ wallet transaction واحدة مع منع التكرار عبر idempotency_key
     */
    protected function createWalletFeeTransaction(
        Booking $booking,
        string $feeCode,
        string $payer,
        int $userId,
        float $amount,
        array $line
    ): WalletTransaction {
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

        $wallet = $this->getActiveWalletForUser($userId);

        $balanceBefore = round((float) $wallet->balance, 2);
        $lockedBefore  = round((float) $wallet->locked_balance, 2);
        $amount        = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('قيمة الرسوم غير صالحة.');
        }

        if ($balanceBefore < $amount) {
            throw new RuntimeException(
                "رصيد المستخدم رقم {$userId} غير كافٍ لتطبيق رسوم {$payer} على الحجز #{$booking->id}"
            );
        }

        $balanceAfter = round($balanceBefore - $amount, 2);
        $lockedAfter  = $lockedBefore;

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

            'meta' => [
                'context' => 'booking_fee',
                'payer' => $payer,
                'fee_code' => $feeCode,
                'fee_type' => $line['fee_type'] ?? null,
                'calc_type' => $line['calc_type'] ?? null,
                'currency' => $line['currency'] ?? 'EGP',
                'base_amount' => round((float) ($line['base_amount'] ?? 0), 2),

                'category_child_service_fee_id' => $line['category_child_service_fee_id'] ?? null,
                'service_fee_id' => $line['service_fee_id'] ?? null, // توافق مؤقت

                'booking_id' => (int) $booking->id,
                'service_id' => (int) $booking->service_id,
                'business_id' => (int) $booking->business_id,
                'client_id' => (int) $booking->user_id,
                'child_id' => (int) ($line['child_id'] ?? $this->resolveBookingChildId($booking)),

                'rules' => $line['rules'] ?? null,
                'notes' => $line['notes'] ?? null,
            ],
        ]);
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

        return 0;
    }

    protected function getActiveWalletForUser(int $userId): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->first();

        if (! $wallet) {
            throw new RuntimeException("لا توجد محفظة للمستخدم رقم {$userId}");
        }

        if ((string) $wallet->status === 'blocked') {
            throw new RuntimeException("محفظة المستخدم رقم {$userId} محظورة");
        }

        return $wallet;
    }

    protected function buildIdempotencyKey(int $bookingId, string $feeCode, string $payer): string
    {
        return 'booking_fee:' . $bookingId . ':' . $feeCode . ':' . $payer;
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
            'business' => 'البزنس',
            'client' => 'العميل',
            default => $payer,
        };

        return "خصم رسوم {$feeCode} ({$payerLabel}) للحجز #{$booking->id} - {$serviceName}";
    }
}