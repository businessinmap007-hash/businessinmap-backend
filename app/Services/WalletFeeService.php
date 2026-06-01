<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\PlatformService;
use App\Models\PlatformServiceFeePromotion;
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
            'business:id,name,category_id,category_child_id',
            'business.serviceFeeConsent',
            'service:id,key,name_ar,name_en,is_active,supports_deposit',
            'user:id,name',
            'user.serviceFeeConsent',
        ]);

        $baseAmount = $this->resolveBookingBaseAmount($booking);
        $childId = $this->resolveBookingChildId($booking);
        $categoryId = $this->resolveBookingCategoryId($booking);
        $serviceId = (int) $booking->service_id;

        if ($serviceId <= 0) {
            return collect();
        }

        $service = $booking->service;

        if (! $service) {
            $service = PlatformService::query()
                ->select(['id', 'key', 'name_ar', 'name_en', 'is_active', 'supports_deposit'])
                ->find($serviceId);
        }

        if (! $service || ! (bool) ($service->is_active ?? false)) {
            return collect();
        }

        $feeRow = null;

        if ($categoryId > 0 && $childId > 0) {
            $feeRow = CategoryChildServiceFee::activeForRootChild($categoryId, $childId, $serviceId);
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy-safe fallback
        |--------------------------------------------------------------------------
        | لا نستخدم أي رسوم من platform_services.
        | هذا fallback محدود فقط داخل category_child_service_fees لحالات قديمة
        | لا تحتوي category_id بشرط وجود صف واحد فقط لنفس child/service.
        |--------------------------------------------------------------------------
        */
        if (! $feeRow && $childId > 0) {
            $legacyRows = CategoryChildServiceFee::query()
                ->active(1)
                ->forPair($childId, $serviceId)
                ->ordered()
                ->limit(2)
                ->get();

            if ($legacyRows->count() === 1) {
                $feeRow = $legacyRows->first();
            }
        }

        if (! $feeRow) {
            return collect();
        }

        $lines = collect();

        $business = $booking->business;

        if ($business && $this->canAutoChargeFees($business)) {
            $line = $this->resolveFeeLineForPayer(
                payer: CategoryChildServiceFee::PAYER_BUSINESS,
                booking: $booking,
                feeRow: $feeRow,
                baseAmount: $baseAmount,
                categoryId: $categoryId,
                childId: $childId,
                serviceId: $serviceId,
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
                feeRow: $feeRow,
                baseAmount: $baseAmount,
                categoryId: $categoryId,
                childId: $childId,
                serviceId: $serviceId,
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
        CategoryChildServiceFee $feeRow,
        float $baseAmount,
        int $categoryId,
        int $childId,
        int $serviceId,
        string $feeCode
    ): ?array {
        $payer = CategoryChildServiceFee::normalizePayer($payer);

        if (! $payer) {
            return null;
        }

        if (! $feeRow->isChargeableFor($payer)) {
            return null;
        }

        $line = $feeRow->toWalletFeeLine(
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

        if (! $line) {
            return null;
        }

        $line['category_id'] = $categoryId > 0 ? $categoryId : ($line['category_id'] ?? null);
        $line['child_id'] = $childId > 0 ? $childId : ($line['child_id'] ?? null);
        $line['service_id'] = $serviceId;
        $line['platform_service_id'] = $serviceId;
        $line['source'] = $line['source'] ?? 'category_child_service_fee';

        $promotion = $this->resolveActivePromotion(
            payer: $payer,
            serviceId: $serviceId,
            childId: $childId
        );

        if ($promotion) {
            $line = $this->applyPromotionToLine($line, $promotion, $payer);
        }

        $amount = round((float) ($line['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return null;
        }

        $line['amount'] = $amount;

        return $line;
    }

    protected function resolveActivePromotion(
        string $payer,
        int $serviceId,
        int $childId
    ): ?PlatformServiceFeePromotion {
        $payer = CategoryChildServiceFee::normalizePayer($payer);

        if (! $payer || $serviceId <= 0) {
            return null;
        }

        return PlatformServiceFeePromotion::query()
            ->active()
            ->currentlyRunning()
            ->forServiceAndChild($serviceId, $childId > 0 ? $childId : null)
            ->where(function ($query) use ($payer) {
                $query->where('target_party', $payer)
                    ->orWhere('target_party', PlatformServiceFeePromotion::TARGET_BOTH);
            })
            ->orderedForApply()
            ->first();
    }

    protected function applyPromotionToLine(
        array $line,
        PlatformServiceFeePromotion $promotion,
        string $payer
    ): array {
        $payer = CategoryChildServiceFee::normalizePayer($payer) ?: $payer;

        $originalAmount = round((float) ($line['amount'] ?? 0), 2);
        $discountValue = round((float) ($promotion->discount_value ?? 0), 2);

        if ($originalAmount <= 0) {
            return $line;
        }

        $finalAmount = $originalAmount;
        $discountAmount = 0.00;

        switch ((string) $promotion->discount_type) {
            case PlatformServiceFeePromotion::DISCOUNT_WAIVE:
                $discountAmount = $originalAmount;
                $finalAmount = 0.00;
                break;

            case PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT:
                $discountAmount = min($originalAmount, max($discountValue, 0));
                $finalAmount = max($originalAmount - $discountAmount, 0);
                break;

            case PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT:
                $percent = max(0, min($discountValue, 100));
                $discountAmount = round($originalAmount * ($percent / 100), 2);
                $finalAmount = max($originalAmount - $discountAmount, 0);
                break;

            case PlatformServiceFeePromotion::DISCOUNT_OVERRIDE_TO_FIXED:
                $finalAmount = max($discountValue, 0);
                $discountAmount = max($originalAmount - $finalAmount, 0);
                break;

            default:
                return $line;
        }

        $line['source_before_promotion'] = $line['source'] ?? 'category_child_service_fee';
        $line['source'] = 'platform_service_fee_promotion';

        $line['amount_before_promotion'] = $originalAmount;
        $line['promotion_discount_amount'] = round($discountAmount, 2);
        $line['amount'] = round($finalAmount, 2);

        $line['promotion'] = [
            'id' => (int) $promotion->id,
            'name' => $promotion->name,
            'scope_type' => $promotion->scope_type,
            'service_id' => $promotion->service_id ? (int) $promotion->service_id : null,
            'child_id' => $promotion->child_id ? (int) $promotion->child_id : null,
            'target_party' => $promotion->target_party,
            'applied_for_payer' => $payer,
            'discount_type' => $promotion->discount_type,
            'discount_value' => round((float) ($promotion->discount_value ?? 0), 2),
            'priority' => (int) ($promotion->priority ?? 0),
            'starts_at' => $promotion->starts_at?->toDateTimeString(),
            'ends_at' => $promotion->ends_at?->toDateTimeString(),
            'notes' => $promotion->notes,
        ];

        return $line;
    }

    public function applyBookingFees(Booking $booking, string $feeCode = self::DEFAULT_FEE_CODE): Collection
    {
        $feeCode = $this->normalizeFeeCode($feeCode);

        $booking->loadMissing([
            'user:id,name',
            'user.serviceFeeConsent',
            'business:id,name,category_id,category_child_id',
            'business.serviceFeeConsent',
            'service:id,name_ar,name_en,key,is_active,supports_deposit',
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

            'source' => $line['source'] ?? 'category_child_service_fee',
            'source_before_promotion' => $line['source_before_promotion'] ?? null,

            'currency' => $line['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY,
            'base_amount' => round((float) ($line['base_amount'] ?? 0), 2),

            'amount_before_promotion' => isset($line['amount_before_promotion'])
                ? round((float) $line['amount_before_promotion'], 2)
                : null,

            'promotion_discount_amount' => isset($line['promotion_discount_amount'])
                ? round((float) $line['promotion_discount_amount'], 2)
                : null,

            'promotion' => $line['promotion'] ?? null,

            'non_refundable_after_in_progress' => true,

            'category_child_service_fee_id' => $line['category_child_service_fee_id'] ?? null,
            'service_fee_id' => $line['service_fee_id'] ?? null,
            'fee_row_id' => $line['fee_row_id'] ?? null,

            'reference_type' => self::REFERENCE_TYPE_BOOKING,
            'reference_id' => (int) $booking->id,

            'source_type' => $line['source_type'] ?? self::REFERENCE_TYPE_BOOKING,
            'source_id' => (int) ($line['source_id'] ?? $booking->id),

            'booking_id' => (int) $booking->id,
            'category_id' => (int) ($line['category_id'] ?? $this->resolveBookingCategoryId($booking)),
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
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

        $price = (float) data_get($meta, 'pricing.final_price', 0);

        if ($price <= 0) {
            $price = (float) data_get($meta, 'pricing.price', 0);
        }

        if ($price <= 0) {
            $price = (float) data_get($meta, 'pricing.amount', 0);
        }

        if ($price <= 0) {
            $price = (float) ($booking->price ?? 0);
        }

        if ($price <= 0) {
            $price = (float) ($booking->total_price ?? 0);
        }

        return round(max($price, 0), 2);
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

    protected function resolveBookingCategoryId(Booking $booking): int
    {
        $businessCategoryId = (int) ($booking->business?->category_id ?? 0);

        if ($businessCategoryId > 0) {
            return $businessCategoryId;
        }

        $metaCategoryId = (int) data_get($booking->meta, 'business_context.category_id', 0);

        if ($metaCategoryId > 0) {
            return $metaCategoryId;
        }

        $metaExecutionCategoryId = (int) data_get($booking->meta, '_execution_fee.category_id', 0);

        if ($metaExecutionCategoryId > 0) {
            return $metaExecutionCategoryId;
        }

        $metaPlatformFeeCategoryId = (int) data_get($booking->meta, 'platform_service_fee.category_id', 0);

        if ($metaPlatformFeeCategoryId > 0) {
            return $metaPlatformFeeCategoryId;
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