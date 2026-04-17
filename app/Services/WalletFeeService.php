<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ServiceFee;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WalletFeeService
{
    public const REFERENCE_TYPE_BOOKING = 'booking';
    public const TX_STATUS_COMPLETED = 'completed';
    public const TX_DIRECTION_OUT = 'out';
    public const TX_TYPE_PLATFORM_FEE = 'platform_fee';

    /**
     * يجلب قواعد الرسوم الفعالة الخاصة بالحجز
     * ويعيد line لكل payer (business / client)
     */
    public function resolveBookingFees(Booking $booking, string $feeCode = 'booking_execution'): Collection
    {
        $booking->loadMissing([
            'business:id,name,category_child_id',
            'service:id,key,name_ar,name_en',
            'user:id,name',
        ]);

        $baseAmount = round((float) $booking->price, 2);
        $childId = $this->resolveBookingChildId($booking);
        $hasChildColumn = Schema::hasColumn('service_fees', 'child_id');

        $rulesQuery = ServiceFee::query()
            ->where('fee_code', $feeCode)
            ->where('is_active', 1)
            ->whereIn('payer', ['business', 'client'])
            ->where(function ($q) use ($booking) {
                $q->whereNull('business_id')
                    ->orWhere('business_id', (int) $booking->business_id);
            })
            ->where(function ($q) use ($booking) {
                $q->whereNull('service_id')
                    ->orWhere('service_id', (int) $booking->service_id);
            });

        if ($hasChildColumn) {
            $rulesQuery->where(function ($q) use ($childId) {
                $q->whereNull('child_id');

                if ($childId > 0) {
                    $q->orWhere('child_id', $childId);
                }
            });
        }

        $rules = $rulesQuery
            ->orderBy('priority')
            ->get()
            ->groupBy('payer');

        $lines = collect();

        foreach (['business', 'client'] as $payer) {
            $rule = $this->resolveBestRuleForPayer(
                rows: $rules->get($payer, collect()),
                booking: $booking,
                childId: $childId,
                hasChildColumn: $hasChildColumn
            );

            if (! $rule) {
                continue;
            }

            $amount = $this->calculateFeeAmount($rule, $baseAmount);

            if ($amount <= 0) {
                continue;
            }

            $lines->push([
                'payer' => $payer,
                'user_id' => $payer === 'business'
                    ? (int) $booking->business_id
                    : (int) $booking->user_id,

                'service_fee_id' => (int) $rule->id,
                'fee_code' => (string) $feeCode,
                'fee_type' => (string) $rule->fee_type,
                'calc_type' => (string) $rule->calc_type,
                'amount' => $amount,
                'currency' => (string) ($rule->currency ?: 'EGP'),
                'base_amount' => $baseAmount,

                'booking_id' => (int) $booking->id,
                'service_id' => (int) $booking->service_id,
                'business_id' => (int) $booking->business_id,
                'client_id' => (int) $booking->user_id,
                'child_id' => $hasChildColumn ? (int) ($rule->child_id ?? 0) : $childId,

                'rules' => $rule->rules,
                'notes' => $rule->notes,
            ]);
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
                'service_fee_id' => $line['service_fee_id'] ?? null,

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

    protected function resolveBestRuleForPayer(
        Collection $rows,
        Booking $booking,
        int $childId,
        bool $hasChildColumn
    ): ?ServiceFee {
        if ($rows->isEmpty()) {
            return null;
        }

        return $rows
            ->sortBy(function (ServiceFee $row) use ($booking, $childId, $hasChildColumn) {
                $score = 0;

                $rowBusinessId = $row->business_id !== null ? (int) $row->business_id : null;
                $rowServiceId  = $row->service_id !== null ? (int) $row->service_id : null;

                if ($rowBusinessId === (int) $booking->business_id) {
                    $score += 0;
                } elseif ($rowBusinessId === null) {
                    $score += 100;
                } else {
                    $score += 10000;
                }

                if ($rowServiceId === (int) $booking->service_id) {
                    $score += 0;
                } elseif ($rowServiceId === null) {
                    $score += 10;
                } else {
                    $score += 10000;
                }

                if ($hasChildColumn) {
                    $rowChildId = $row->child_id !== null ? (int) $row->child_id : null;

                    if ($childId > 0 && $rowChildId === $childId) {
                        $score += 0;
                    } elseif ($rowChildId === null) {
                        $score += 1;
                    } else {
                        $score += 10000;
                    }
                }

                $score += (int) ($row->priority ?? 9999);

                return $score;
            })
            ->first();
    }

    protected function calculateFeeAmount(ServiceFee $rule, float $baseAmount): float
    {
        $calcType = (string) $rule->calc_type;
        $amountValue = (float) $rule->amount;

        $amount = $calcType === 'percent'
            ? round($baseAmount * ($amountValue / 100), 2)
            : round($amountValue, 2);

        if (! is_null($rule->min_amount)) {
            $amount = max($amount, (float) $rule->min_amount);
        }

        if (! is_null($rule->max_amount)) {
            $amount = min($amount, (float) $rule->max_amount);
        }

        return round($amount, 2);
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
        return "booking:{$bookingId}:fee:{$feeCode}:{$payer}";
    }

    protected function buildHumanNote(string $feeCode, string $payer, Booking $booking): string
    {
        $payerLabel = $payer === 'business' ? 'Business' : 'Client';
        $serviceName = (string) ($booking->service->name_ar ?? $booking->service->name_en ?? $booking->service->key ?? 'service');
        $childId = $this->resolveBookingChildId($booking);

        return $childId > 0
            ? "Platform fee [{$feeCode}] charged to {$payerLabel} for booking #{$booking->id} ({$serviceName}) / child #{$childId}"
            : "Platform fee [{$feeCode}] charged to {$payerLabel} for booking #{$booking->id} ({$serviceName})";
    }
}