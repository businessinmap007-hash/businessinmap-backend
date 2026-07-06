<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\GuaranteeLevel;
use App\Services\Guarantees\GuaranteeOperationCoverageService;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceExecutionEngine
{
    public const EXECUTION_FEE_CODE = WalletFeeService::DEFAULT_FEE_CODE;

    public function __construct(
        protected WalletFeeService $walletFeeService,
        protected BookingDepositService $bookingDepositService,
        protected BookablePricingService $bookablePricingService,
        protected BookableAvailabilityService $bookableAvailabilityService,
        protected BookingDepositPolicyResolver $bookingDepositPolicyResolver,
        protected BookingDepositCalculator $bookingDepositCalculator,
        protected BookingGuaranteeIntegration $bookingGuaranteeIntegration,
        protected GuaranteeOperationCoverageService $operationCoverageService,
        protected BusinessServicePriceResolver $businessServicePriceResolver,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare / Preview
    |--------------------------------------------------------------------------
    */

    public function prepare(
        int $businessId,
        int $serviceId,
        ?int $bookableId = null,
        int $quantity = 1,
        mixed $pricingDate = null
    ): array {
        $quantity = max($quantity, 1);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        $service = PlatformService::query()
            ->select([
                'id',
                'key',
                'name_ar',
                'name_en',
                'is_active',
                'supports_deposit',
            ])
            ->where('id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير موجودة أو غير مفعلة.',
            ]);
        }

        $bookable = null;

        if ($bookableId) {
            $bookable = $this->resolveBookableItem($businessId, $serviceId, $bookableId);
        }

        $itemType = $bookable
            ? trim((string) ($bookable->item_type ?? ''))
            : null;

        $businessPrice = $this->resolveBusinessServicePrice(
            businessId: $businessId,
            serviceId: $serviceId,
            childId: $childId,
            itemType: $itemType
        );

        if (! $businessPrice) {
            throw ValidationException::withMessages([
                'service_id' => $itemType
                    ? "لا يوجد سعر مفعل لهذا البزنس والخدمة ونوع العنصر ({$itemType})."
                    : 'هذه الخدمة غير مفعلة لهذا البزنس داخل هذا القسم الفرعي.',
            ]);
        }

        $priceBreakdown = $this->resolvePriceBreakdown(
            service: $service,
            businessPrice: $businessPrice,
            bookable: $bookable,
            quantity: $quantity,
            pricingDate: $pricingDate
        );

        $depositPolicy = $this->resolveDepositPolicy(
            service: $service,
            businessPrice: $businessPrice,
            price: (float) $priceBreakdown['final_price'],
            bookable: $bookable
        );

        $feeSnapshot = $this->resolveExecutionFeeSnapshot(
            businessId: $businessId,
            categoryId: $categoryId,
            serviceId: $serviceId,
            childId: $childId,
            baseAmount: (float) $priceBreakdown['final_price']
        );

        return [
            'business' => $business,
            'service' => $service,
            'business_price' => $businessPrice,
            'bookable' => $bookable,

            'business_id' => $businessId,
            'business_category_id' => $categoryId,
            'business_child_id' => $childId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,

            'price' => (float) $priceBreakdown['final_price'],
            'price_breakdown' => $priceBreakdown,
            'deposit_policy' => $depositPolicy,

            'service_fee_row' => $feeSnapshot['row'] ?? null,
            'service_fee_rows' => [
                'business' => $feeSnapshot['business'] ?? null,
                'client' => $feeSnapshot['client'] ?? null,
            ],
            'fee_snapshot' => $feeSnapshot,
        ];
    }

    public function preview(
        int $businessId,
        int $serviceId,
        ?int $bookableId = null,
        int $quantity = 1,
        mixed $startsAt = null,
        mixed $endsAt = null
    ): array {
        $calc = $this->prepare(
            businessId: $businessId,
            serviceId: $serviceId,
            bookableId: $bookableId,
            quantity: $quantity,
            pricingDate: $startsAt
        );

        $availability = null;

        if ($calc['bookable'] && $startsAt && $endsAt) {
            $availability = $this->bookableAvailabilityService->check(
                $calc['bookable'],
                $startsAt,
                $endsAt
            );
        }

        return array_merge($calc, [
            'availability' => $availability,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Preview / Guard
    |--------------------------------------------------------------------------
    */

    public function financialPreview(Booking $booking): array
    {
        $booking->loadMissing([
            'user:id,name,type',
            'user.serviceFeeConsent',
            'business:id,name,type,category_id,category_child_id',
            'business.serviceFeeConsent',
            'service:id,key,name_ar,name_en,is_active,supports_deposit',
            'bookable',
        ]);

        $deposit = $this->latestDeposit($booking);
        $depositPolicy = $this->depositPolicy($booking);
        $feeLines = $this->walletFeeService->resolveBookingFees($booking, self::EXECUTION_FEE_CODE);

        $clientId = (int) $booking->user_id;
        $businessId = (int) $booking->business_id;

        $clientDepositRequired = 0.0;
        $businessDepositRequired = 0.0;

        if (($depositPolicy['required'] ?? false) && ! ($deposit && $deposit->isFrozen())) {
           $clientDepositRequired = round((float) ($depositPolicy['wallet_hold_amount'] ?? $depositPolicy['hold'] ?? 0), 2);
           $businessDepositRequired = round((float) ($depositPolicy['business_counter_hold_amount'] ?? 0), 2);
        }

        $clientFeeRequired = 0.0;
        $businessFeeRequired = 0.0;

        foreach ($feeLines as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $payer = (string) ($line['payer'] ?? '');

            if ($payer === CategoryChildServiceFee::PAYER_CLIENT) {
                $clientFeeRequired += $amount;
            }

            if ($payer === CategoryChildServiceFee::PAYER_BUSINESS) {
                $businessFeeRequired += $amount;
            }
        }

        $clientWallet = $this->walletSnapshot($clientId);
        $businessWallet = $this->walletSnapshot($businessId);

        $clientDepositCoverage = $this->bookingCoverageDecision(
            user: $booking->user,
            amount: $clientDepositRequired,
            targetType: GuaranteeLevel::TARGET_CLIENT,
            booking: $booking,
            side: 'client'
        );

        $businessDepositCoverage = $this->bookingCoverageDecision(
            user: $booking->business,
            amount: $businessDepositRequired,
            targetType: GuaranteeLevel::TARGET_BUSINESS,
            booking: $booking,
            side: 'business'
        );

        $clientDepositCoveredByGuarantee = (bool) data_get($depositPolicy, 'client_guarantee_covered', false)
            && (bool) data_get($clientDepositCoverage, 'covered', false);

        $businessDepositCoveredByGuarantee = (bool) data_get($depositPolicy, 'business_guarantee_covered', false)
            && (bool) data_get($businessDepositCoverage, 'covered', false);

        $clientWalletDepositRequired = $clientDepositCoveredByGuarantee
            ? 0.0
            : $clientDepositRequired;

        $businessWalletDepositRequired = $businessDepositCoveredByGuarantee
            ? 0.0
            : $businessDepositRequired;

        $clientRequiredTotal = round($clientWalletDepositRequired + $clientFeeRequired, 2);
        $businessRequiredTotal = round($businessWalletDepositRequired + $businessFeeRequired, 2);

        $clientReady = $clientWallet['active']
            && $clientWallet['balance'] >= $clientRequiredTotal;

        $businessReady = $businessWallet['active']
            && $businessWallet['balance'] >= $businessRequiredTotal;

        $messages = [];

        if ((bool) data_get($depositPolicy, 'client_guarantee_covered', false) && ! $clientDepositCoveredByGuarantee && $clientDepositRequired > 0) {
            $messages[] = 'ضمان طالب الحجز غير كافٍ أو غير صالح لتغطية Wallet Hold لهذا الحجز.';
        }

        if ((bool) data_get($depositPolicy, 'business_guarantee_covered', false) && ! $businessDepositCoveredByGuarantee && $businessDepositRequired > 0) {
            $messages[] = 'ضمان مقدم الخدمة غير كافٍ أو غير صالح لتغطية Counter Hold لهذا الحجز.';
        }

        if (! $clientReady && $clientRequiredTotal > 0) {
            $messages[] = 'رصيد طالب الحجز غير كافٍ لتجميد الديبوزت أو خصم رسوم الخدمة.';
        }

        if (! $businessReady && $businessRequiredTotal > 0) {
            $messages[] = 'رصيد مقدم الخدمة غير كافٍ لتجميد الديبوزت أو خصم رسوم الخدمة.';
        }

        return [
            'ok' => empty($messages),

            'booking_id' => (int) $booking->id,
            'status' => (string) $booking->status,

            'operation_coverage' => [
                'operation_type' => GuaranteeOperationCoverageService::OP_BOOKING,
                'client' => $clientDepositCoverage,
                'business' => $businessDepositCoverage,
            ],

            'deposit' => [
                'required' => (bool) ($depositPolicy['required'] ?? false),
                'already_frozen' => (bool) ($deposit && $deposit->isFrozen()),
                'existing_deposit_id' => $deposit ? (int) $deposit->id : null,
                'policy' => $depositPolicy,
                'client_guarantee_covered' => $clientDepositCoveredByGuarantee,
                'business_guarantee_covered' => $businessDepositCoveredByGuarantee,
                'client_wallet_required' => $clientWalletDepositRequired,
                'business_wallet_required' => $businessWalletDepositRequired,

                'client_required' => $clientDepositRequired,
                'business_required' => $businessDepositRequired,
                'guarantee_checks' => [
                    'client' => $clientDepositCoverage,
                    'business' => $businessDepositCoverage,
                ],
            ],

            'fees' => [
                'code' => self::EXECUTION_FEE_CODE,
                'lines' => $feeLines->values()->all(),
                'client_required' => round($clientFeeRequired, 2),
                'business_required' => round($businessFeeRequired, 2),
                'non_refundable_after_in_progress' => true,
            ],

            'client' => [
                'user_id' => $clientId,
                'wallet' => $clientWallet,
                'required_total' => $clientRequiredTotal,
                'ready' => $clientReady || $clientRequiredTotal <= 0,
            ],

            'business' => [
                'user_id' => $businessId,
                'wallet' => $businessWallet,
                'required_total' => $businessRequiredTotal,
                'ready' => $businessReady || $businessRequiredTotal <= 0,
            ],

            'messages' => $messages,
        ];
    }

    public function ensureFinancialReadiness(Booking $booking): array
    {
        $preview = $this->financialPreview($booking);

        if (! ($preview['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'balance' => $preview['messages'] ?: ['لا يمكن بدء التنفيذ بسبب عدم جاهزية الرصيد.'],
            ]);
        }

        return $preview;
    }

    public function operationCoveragePreview(Booking $booking): array
    {
        return $this->financialPreview($booking)['operation_coverage'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Meta
    |--------------------------------------------------------------------------
    */

    public function buildBookingMeta(
        array $existingMeta,
        array $calc,
        ?BookableItem $bookable = null
    ): array {
        $meta = $existingMeta;

        $service = $calc['service'];
        $business = $calc['business'];
        $businessPrice = $calc['business_price'];
        $priceBreakdown = $calc['price_breakdown'];
        $depositPolicy = $calc['deposit_policy'];
        $feeSnapshot = $calc['fee_snapshot'];

        $meta['platform_service'] = [
            'id' => (int) $service->id,
            'key' => (string) $service->key,
            'name_ar' => (string) ($service->name_ar ?? ''),
            'name_en' => (string) ($service->name_en ?? ''),
            'supports_deposit' => (bool) ($service->supports_deposit ?? false),
        ];

        $meta['business_context'] = [
            'business_id' => (int) $business->id,
            'category_id' => (int) ($business->category_id ?? 0),
            'category_child_id' => (int) ($business->category_child_id ?? 0),
        ];

        $meta['pricing'] = [
            'original_price' => (float) $priceBreakdown['original_price'],
            'discount_enabled' => (bool) $priceBreakdown['discount_enabled'],
            'discount_percent' => (int) $priceBreakdown['discount_percent'],
            'discount_amount' => (float) $priceBreakdown['discount_amount'],
            'unit_price' => (float) $priceBreakdown['unit_price'],
            'quantity' => (int) $priceBreakdown['quantity'],
            'final_price' => (float) $priceBreakdown['final_price'],
            'price' => (float) $priceBreakdown['final_price'],
            'currency' => (string) ($priceBreakdown['currency'] ?? 'EGP'),
            'source' => (string) $priceBreakdown['source'],
            'business_service_price_id' => (int) $businessPrice->id,
            'business_service_price_child_id' => (int) ($businessPrice->child_id ?? 0),
            'pricing_source' => (string) ($priceBreakdown['pricing_source'] ?? $priceBreakdown['source'] ?? ''),
            'bookable_rule' => $priceBreakdown['bookable_rule'] ?? null,
            'bookable_rules' => $priceBreakdown['bookable_rules'] ?? [],
            'bookable_breakdown' => $priceBreakdown['bookable_breakdown'] ?? [],
        ];

        $meta['service_fees_snapshot'] = $feeSnapshot;
        $meta['deposit_policy'] = $depositPolicy;

        if ($bookable) {
            $meta['bookable_item'] = [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'code' => (string) ($bookable->code ?? ''),
                'item_type' => (string) ($bookable->item_type ?? ''),
                // Price and deposit are sourced from BusinessServicePrice / the
                // resolved deposit policy, not from the unit (deprecated).
                'price' => (float) ($priceBreakdown['unit_price'] ?? 0),
                'deposit_enabled' => (bool) ($depositPolicy['business_deposit_enabled'] ?? false),
                'deposit_percent' => (int) round((float) ($depositPolicy['business_deposit_percent'] ?? 0)),
            ];
        } else {
            unset($meta['bookable_item']);
        }

        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];

        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['category_id'] = (int) ($calc['business_category_id'] ?? 0);
        $meta['_execution_fee']['child_id'] = (int) ($calc['business_child_id'] ?? 0);
        $meta['_execution_fee']['service_id'] = (int) ($calc['service_id'] ?? 0);
        $meta['_execution_fee']['platform_service_id'] = (int) ($calc['platform_service_id'] ?? $calc['service_id'] ?? 0);

        $meta['_execution_fee']['fee_row_id'] = $feeSnapshot['fee_row_id'] ?? null;
        $meta['_execution_fee']['category_child_service_fee_id'] = $feeSnapshot['category_child_service_fee_id'] ?? null;

        $meta['_execution_fee']['snapshot'] = [
            'business' => $feeSnapshot['business'] ?? null,
            'client' => $feeSnapshot['client'] ?? null,
        ];

        $meta['_execution_fee']['client_amount'] = (float) ($meta['_execution_fee']['client_amount'] ?? 0);
        $meta['_execution_fee']['business_amount'] = (float) ($meta['_execution_fee']['business_amount'] ?? 0);
        $meta['_execution_fee']['charged_at'] = $meta['_execution_fee']['charged_at'] ?? null;
        $meta['_execution_fee']['transactions'] = $meta['_execution_fee']['transactions'] ?? [];
        $meta['_execution_fee']['non_refundable_after_in_progress'] = true;

        return $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Move To In Progress
    |--------------------------------------------------------------------------
    */

    public function moveBookingToInProgress(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->canMoveToInProgress()) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن بدء التنفيذ من الحالة الحالية.',
                ]);
            }

            $deposit = $this->latestDeposit($booking);
            $depositPolicy = $this->depositPolicy($booking);

            [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

            if (! $clientConfirmed || ! $businessConfirmed) {
                throw ValidationException::withMessages([
                    'status' => 'يجب تأكيد الطرفين قبل بدء التنفيذ.',
                ]);
            }

            $readiness = $this->ensureFinancialReadiness($booking);

            if ((bool) ($depositPolicy['required'] ?? false)) {
                $walletHoldRequired = (bool) ($depositPolicy['wallet_hold_required'] ?? false);
                $externalRequired = (bool) ($depositPolicy['external_deposit_required'] ?? false);

                $clientGuaranteeCovered = (bool) data_get($readiness, 'deposit.client_guarantee_covered', false);
                $businessGuaranteeCovered = (bool) data_get($readiness, 'deposit.business_guarantee_covered', false);

                if ($clientGuaranteeCovered) {
                    $walletHoldRequired = false;
                }

                if ($businessGuaranteeCovered) {
                    $depositPolicy['business_counter_hold_amount'] = 0.0;
                    $depositPolicy['business_counter_hold_required'] = false;
                }

                if ($walletHoldRequired) {
                    if (! $deposit || ! $deposit->isFrozen()) {
                        $holdAmount = round((float) ($depositPolicy['hold'] ?? $depositPolicy['wallet_hold_amount'] ?? $depositPolicy['amount'] ?? 0), 2);

                        if ($holdAmount <= 0) {
                            throw ValidationException::withMessages([
                                'deposit' => 'قيمة Wallet Hold المطلوبة غير صالحة.',
                            ]);
                        }

                        $deposit = $this->bookingDepositService->freezeForBooking($booking, $holdAmount, $depositPolicy);
                        $deposit->refresh();

                        if (! $deposit->isFrozen()) {
                            throw ValidationException::withMessages([
                                'deposit' => 'تعذر تجميد الـ Deposit تلقائيًا. راجع حالة الـ Deposit الحالية.',
                            ]);
                        }
                    }
                } elseif (! $deposit && $externalRequired) {
                    throw ValidationException::withMessages([
                        'deposit' => 'يجب إنشاء سجل العربون الخارجي واعتماده قبل بدء التنفيذ.',
                    ]);
                }

                if ($externalRequired) {
                    $deposit = $deposit ?: $this->latestDeposit($booking);

                    if (! $deposit || (string) ($deposit->external_deposit_status ?? '') !== 'verified') {
                        throw ValidationException::withMessages([
                            'external_deposit' => 'يجب اعتماد العربون الخارجي قبل بدء التنفيذ.',
                        ]);
                    }
                }
            }

            $this->chargeExecutionFeeOnce($booking);

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

            $meta['_financial_guard'] = [
                'checked_at' => now()->toDateTimeString(),
                'ok' => true,
                'deposit_required' => (bool) data_get($readiness, 'deposit.required', false),
                'deposit_auto_freeze_enabled' => true,
                'client_guarantee_covered' => (bool) data_get($readiness, 'deposit.client_guarantee_covered', false),
                'business_guarantee_covered' => (bool) data_get($readiness, 'deposit.business_guarantee_covered', false),
                'fees_non_refundable_after_in_progress' => true,
                'client_required_total' => (float) data_get($readiness, 'client.required_total', 0),
                'business_required_total' => (float) data_get($readiness, 'business.required_total', 0),
            ];

            $meta['_operation_coverage'] = [
                'checked_at' => now()->toDateTimeString(),
                'operation_type' => GuaranteeOperationCoverageService::OP_BOOKING,
                'client' => data_get($readiness, 'operation_coverage.client'),
                'business' => data_get($readiness, 'operation_coverage.business'),
            ];

            $meta['_operation_stats_snapshot'] = [
                'client' => [
                    'user_id' => (int) $booking->user_id,
                    'fees_resolved' => (float) data_get($readiness, 'fees.client_required', 0) > 0,
                    'stats_candidate' => true,
                ],
                'business' => [
                    'user_id' => (int) $booking->business_id,
                    'fees_resolved' => (float) data_get($readiness, 'fees.business_required', 0) > 0,
                    'stats_candidate' => true,
                ],
                'operation_started_at' => now()->toDateTimeString(),
            ];

            $booking->meta = $meta;
            $booking->status = Booking::STATUS_IN_PROGRESS;
            $booking->save();
        });
    }

    public function chargeExecutionFeeOnce(Booking $booking): void
    {
        $booking->refresh();

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];

        if (! empty($meta['_execution_fee']['charged_at'])) {
            return;
        }

        $transactions = $this->walletFeeService->applyBookingFees(
            $booking,
            self::EXECUTION_FEE_CODE
        );

        $clientAmount = 0.0;
        $businessAmount = 0.0;
        $txMap = [];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $payer = (string) data_get($tx->meta, 'payer', '');

            if ($payer === CategoryChildServiceFee::PAYER_CLIENT) {
                $clientAmount += $amount;
            }

            if ($payer === CategoryChildServiceFee::PAYER_BUSINESS) {
                $businessAmount += $amount;
            }

            $txMap[] = [
                'id' => (int) $tx->id,
                'user_id' => (int) $tx->user_id,
                'payer' => $payer,
                'amount' => $amount,
                'type' => (string) $tx->type,
                'direction' => (string) $tx->direction,
                'status' => (string) $tx->status,
                'category_child_service_fee_id' => data_get($tx->meta, 'category_child_service_fee_id'),
                'service_fee_id' => data_get($tx->meta, 'service_fee_id'),
                'fee_row_id' => data_get($tx->meta, 'fee_row_id'),
                'source' => data_get($tx->meta, 'source'),
                'promotion' => data_get($tx->meta, 'promotion'),
            ];
        }

        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['client_amount'] = round($clientAmount, 2);
        $meta['_execution_fee']['business_amount'] = round($businessAmount, 2);
        $meta['_execution_fee']['charged_at'] = now()->toDateTimeString();
        $meta['_execution_fee']['transactions'] = $txMap;
        $meta['_execution_fee']['non_refundable'] = true;
        $meta['_execution_fee']['non_refundable_reason'] = 'service_entered_in_progress';

        $booking->meta = $meta;
        $booking->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function resolvePriceBreakdown(
        PlatformService $service,
        BusinessServicePrice $businessPrice,
        ?BookableItem $bookable = null,
        int $quantity = 1,
        mixed $pricingDate = null
    ): array {
        $quantity = max($quantity, 1);

        // Price authority is BusinessServicePrice (per item type). The bookable
        // only identifies which type; $businessPrice was already resolved for
        // that type by the caller. The unit no longer carries its own price, so
        // discounts now apply to bookable bookings too. The charge mode (free /
        // reservation fee / minimum) is honoured here; food add-ons (unified
        // invoice) will pass a food total later. See services-blueprint.
        $unitPrice = round($businessPrice->resolveBaseCharge(), 2);

        if ($unitPrice <= 0 && $businessPrice->chargeMode() === BusinessServicePrice::CHARGE_STANDARD && isset($businessPrice->base_price)) {
            $unitPrice = round((float) ($businessPrice->base_price ?? 0), 2);
        }

        $originalPrice = round($unitPrice * $quantity, 2);

        $discountEnabled = (bool) ($businessPrice->discount_enabled ?? false);
        $discountPercent = $discountEnabled ? (int) ($businessPrice->discount_percent ?? 0) : 0;
        $discountPercent = max(0, min($discountPercent, 100));

        $discountAmount = $discountEnabled
            ? round($originalPrice * ($discountPercent / 100), 2)
            : 0.00;

        $finalPrice = max(round($originalPrice - $discountAmount, 2), 0);

        return [
            'source' => 'business_service_price',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'original_price' => $originalPrice,
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'currency' => (string) ($businessPrice->currency ?: 'EGP'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */

    public function resolveDepositPolicy(
            PlatformService $service,
            BusinessServicePrice $businessPrice,
            float $price,
            ?BookableItem $bookable = null,
            ?User $client = null,
            array $guarantees = []
        ): array {
        $business = User::query()
            ->where('id', (int) $businessPrice->business_id)
            ->where('type', User::TYPE_BUSINESS)
            ->first();

        if (! $business) {
            return [
                'service_supports_deposit' => (bool) ($service->supports_deposit ?? false),
                'required' => false,
                'amount' => 0.00,
                'hold' => 0.00,
                'source' => 'business_not_found',
            ];
        }

        // Deposit is single-source: the business deposit policy (per business +
        // child + service) drives it. The physical unit no longer carries any
        // deposit override — a premium unit is modelled as a distinct item type.
        $policy = $this->bookingDepositPolicyResolver->resolve($business, $service);

        // Deposit base follows the same single source as pricing: the unit
        // price no longer participates.
        $firstDayAmount = round((float) ($businessPrice->price ?? 0), 2);

        if ($firstDayAmount <= 0) {
            $firstDayAmount = $price;
        }

        $resolved = $this->bookingDepositCalculator->calculate($policy, [
            'total_amount' => $price,
            'first_day_amount' => $firstDayAmount,
            'guarantees' => [
            'client' => $guarantees['client'] ?? [],
            'business' => $guarantees['business'] ?? [],
        ],
        ]);

        return array_merge($resolved, [
            'service_supports_deposit' => (bool) ($service->supports_deposit ?? false),
            'service_max_percent' => 20,
            'business_deposit_enabled' => (bool) ($policy['enabled'] ?? false),
            'business_deposit_percent' => (float) ($resolved['configured_percent'] ?? 0),
            // Retained for API/UI shape only; per-unit deposit overrides were
            // removed (units are inventory-only, deposit is single-source).
            'bookable_deposit_enabled' => false,
            'bookable_deposit_percent' => 0,
        ]);
    }

    public function depositPolicy(Booking $booking): array
    {
        $booking->loadMissing([
            'service:id,key,name_ar,name_en,supports_deposit',
            'business:id,name,type,category_id,category_child_id',
            'user:id,name,type,guarantee_enabled,rating_enabled,commercial_operations_enabled',
            'bookable',
        ]);

        $childId = (int) ($booking->business?->category_child_id ?? 0);

        $bookable = $booking->bookable instanceof BookableItem
            ? $booking->bookable
            : null;

        $itemType = $bookable
            ? trim((string) ($bookable->item_type ?? ''))
            : null;

        $businessPrice = $this->resolveBusinessServicePrice(
            businessId: (int) $booking->business_id,
            serviceId: (int) $booking->service_id,
            childId: $childId,
            itemType: $itemType
        );

        if (! $booking->service || ! $businessPrice) {
            return [
                'service_supports_deposit' => (bool) ($booking->service?->supports_deposit ?? false),
                'service_max_percent' => 0,

                'business_deposit_enabled' => false,
                'business_deposit_percent' => 0,

                'bookable_deposit_enabled' => false,
                'bookable_deposit_percent' => 0,

                'required' => false,
                'amount' => 0.00,
                'hold' => 0.00,
                'configured_percent' => 0,
                'source' => 'none',
            ];
        }

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

        $price = (float) data_get($meta, 'pricing.final_price', 0);

        if ($price <= 0) {
            $price = (float) ($booking->price ?? 0);
        }

        if ($price <= 0) {
            $price = (float) data_get($meta, 'pricing.price', 0);
        }

        if ($price <= 0) {
            $price = (float) ($booking->total_price ?? 0);
        }
        $guarantees = $this->bookingGuaranteeIntegration->payloadForBooking($booking);

        return $this->resolveDepositPolicy(
            service: $booking->service,
            businessPrice: $businessPrice,
            price: $price,
            bookable: $bookable,
            client: $booking->user,
            guarantees: $guarantees
        );
    }

    protected function bookingCoverageDecision(?User $user, float $amount, string $targetType, Booking $booking, string $side): array
    {
        $amount = round(max($amount, 0), 2);

        if (! $user || $amount <= 0) {
            return [
                'covered' => false,
                'reason' => $amount <= 0 ? 'not_required' : 'missing_user',
                'operation_type' => GuaranteeOperationCoverageService::OP_BOOKING,
                'operation_id' => (int) $booking->id,
                'amount' => $amount,
                'target_type' => $targetType,
                'user_id' => $user ? (int) $user->id : null,
                'side' => $side,
            ];
        }

        return array_merge(
            $this->operationCoverageService->check(
                user: $user,
                amount: $amount,
                operationType: GuaranteeOperationCoverageService::OP_BOOKING,
                operationId: (int) $booking->id,
                targetType: $targetType,
                context: [
                    'source' => 'ServiceExecutionEngine.financialPreview',
                    'side' => $side,
                    'booking_id' => (int) $booking->id,
                    'service_id' => (int) $booking->service_id,
                    'business_id' => (int) $booking->business_id,
                    'client_id' => (int) $booking->user_id,
                    'coverage_purpose' => $side === 'client' ? 'wallet_hold' : 'business_counter_hold',
                ]
            ),
            ['side' => $side]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Business / Bookable Resolution
    |--------------------------------------------------------------------------
    */

    protected function resolveBusinessContext(int $businessId): array
    {
        if ($businessId <= 0) {
            return [null, 0, 0];
        }

        $business = User::query()
            ->select(['id', 'name', 'type', 'category_id', 'category_child_id'])
            ->where('id', $businessId)
            ->where('type', 'business')
            ->first();

        if (! $business) {
            return [null, 0, 0];
        }

        return [
            $business,
            (int) ($business->category_id ?? 0),
            (int) ($business->category_child_id ?? 0),
        ];
    }

    /**
     * Resolve the BusinessServicePrice backing an existing booking (its
     * business + service + the subcategory/item type it was placed for). Used
     * by the unified-invoice logic to recompute the table charge.
     */
    public function resolveBusinessPriceForBooking(Booking $booking): ?BusinessServicePrice
    {
        $booking->loadMissing(['business:id,category_id,category_child_id', 'bookable']);

        $itemType = $booking->bookable?->item_type
            ?: (string) data_get($booking->bookableMeta(), 'item_type', '');

        return $this->resolveBusinessServicePrice(
            businessId: (int) $booking->business_id,
            serviceId: (int) $booking->service_id,
            childId: (int) ($booking->business?->category_child_id ?? 0),
            itemType: $itemType !== '' ? $itemType : null
        );
    }

    protected function resolveBusinessServicePrice(
        int $businessId,
        int $serviceId,
        int $childId = 0,
        ?string $itemType = null
    ): ?BusinessServicePrice {
        return $this->businessServicePriceResolver->resolve(
            businessId: $businessId,
            serviceId: $serviceId,
            childId: $childId,
            itemType: $itemType
        );
    }

    protected function resolveBookableItem(int $businessId, int $serviceId, int $bookableId): ?BookableItem
    {
        $bookable = BookableItem::query()
            ->where('id', $bookableId)
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (! $bookable) {
            throw ValidationException::withMessages([
                'bookable_id' => 'العنصر القابل للحجز غير موجود أو غير تابع لهذا البزنس/الخدمة.',
            ]);
        }

        return $bookable;
    }

    /*
    |--------------------------------------------------------------------------
    | Service Fees
    |--------------------------------------------------------------------------
    */

    protected function resolveExecutionFeeSnapshot(
        int $businessId,
        int $categoryId,
        int $serviceId,
        int $childId = 0,
        float $baseAmount = 0
    ): array {
        $row = $this->resolveChildServiceFeeRow($categoryId, $childId, $serviceId);

        return [
            'row' => $row,
            'business_id' => $businessId,
            'category_id' => $categoryId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,

            'fee_code' => self::EXECUTION_FEE_CODE,
            'fee_row_id' => $row ? (int) $row->id : null,
            'category_child_service_fee_id' => $row ? (int) $row->id : null,

            'business' => $row
                ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_BUSINESS, $baseAmount)
                : null,

            'client' => $row
                ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_CLIENT, $baseAmount)
                : null,
        ];
    }

    protected function resolveChildServiceFeeRow(int $categoryId, int $childId, int $serviceId): ?CategoryChildServiceFee
    {
        if ($categoryId > 0 && $childId > 0 && $serviceId > 0) {
            return CategoryChildServiceFee::activeForRootChild($categoryId, $childId, $serviceId);
        }

        if ($childId > 0 && $serviceId > 0) {
            $rows = CategoryChildServiceFee::query()
                ->active(1)
                ->forPair($childId, $serviceId)
                ->ordered()
                ->limit(2)
                ->get();

            if ($rows->count() === 1) {
                return $rows->first();
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Confirmations / Wallet Helpers
    |--------------------------------------------------------------------------
    */

    protected function latestDeposit(Booking $booking): ?Deposit
    {
        return $this->bookingDepositService->latestDeposit($booking);
    }

    protected function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $confirm = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        $metaClientConfirmed = ! empty($confirm['client']);
        $metaBusinessConfirmed = ! empty($confirm['business']);

        if ($deposit) {
            return [
                ((bool) $deposit->client_confirmed) || $metaClientConfirmed,
                ((bool) $deposit->business_confirmed) || $metaBusinessConfirmed,
            ];
        }

        return [
            $metaClientConfirmed,
            $metaBusinessConfirmed,
        ];
    }

    protected function walletSnapshot(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'exists' => false,
                'active' => false,
                'balance' => 0.0,
                'locked_balance' => 0.0,
                'status' => null,
            ];
        }

        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->first();

        if (! $wallet) {
            return [
                'exists' => false,
                'active' => false,
                'balance' => 0.0,
                'locked_balance' => 0.0,
                'status' => null,
            ];
        }

        return [
            'exists' => true,
            'active' => (string) ($wallet->status ?? '') === 'active',
            'balance' => round((float) ($wallet->balance ?? 0), 2),
            'locked_balance' => round((float) ($wallet->locked_balance ?? 0), 2),
            'status' => (string) ($wallet->status ?? ''),
        ];
    }
}
