<?php

namespace App\Support\AdminV2\Operations;

final class OperationContext
{
    public function __construct(
        public readonly OperationReference $reference,
        public readonly ?int $clientId = null,
        public readonly ?int $businessId = null,
        public readonly ?int $platformServiceId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $childId = null,
        public readonly ?string $status = null,
        public readonly float $amount = 0.0,
        public readonly string $currency = 'EGP',
        public readonly array $meta = [],
    ) {
    }

    public static function make(
        OperationReference $reference,
        ?int $clientId = null,
        ?int $businessId = null,
        ?int $platformServiceId = null,
        ?int $categoryId = null,
        ?int $childId = null,
        ?string $status = null,
        float $amount = 0.0,
        string $currency = 'EGP',
        array $meta = []
    ): self {
        return new self(
            reference: $reference,
            clientId: $clientId,
            businessId: $businessId,
            platformServiceId: $platformServiceId,
            categoryId: $categoryId,
            childId: $childId,
            status: $status,
            amount: round(max($amount, 0), 2),
            currency: strtoupper(trim($currency)) ?: 'EGP',
            meta: $meta,
        );
    }

    public static function fromBooking(\App\Models\Booking $booking): self
    {
        $booking->loadMissing([
            'business:id,name,category_id,category_child_id',
            'service:id,key,name_ar,name_en',
        ]);

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

        $categoryId = (int) (
            $booking->business?->category_id
            ?: data_get($meta, 'business_context.category_id', 0)
            ?: data_get($meta, '_execution_fee.category_id', 0)
        );

        $childId = (int) (
            $booking->business?->category_child_id
            ?: data_get($meta, 'business_context.category_child_id', 0)
            ?: data_get($meta, '_execution_fee.child_id', 0)
        );

        $amount = (float) (
            data_get($meta, 'pricing.final_price')
            ?? data_get($meta, 'pricing.price')
            ?? $booking->price
            ?? 0
        );

        $currency = (string) (
            data_get($meta, 'pricing.currency')
            ?: \App\Models\Booking::DEFAULT_CURRENCY
        );

        return new self(
            reference: OperationReference::booking((int) $booking->id),
            clientId: (int) $booking->user_id,
            businessId: (int) $booking->business_id,
            platformServiceId: (int) $booking->service_id,
            categoryId: $categoryId > 0 ? $categoryId : null,
            childId: $childId > 0 ? $childId : null,
            status: (string) $booking->status,
            amount: round(max($amount, 0), 2),
            currency: strtoupper(trim($currency)) ?: 'EGP',
            meta: $meta,
        );
    }

    public function reference(): OperationReference
    {
        return $this->reference;
    }

    public function referenceType(): string
    {
        return $this->reference->type();
    }

    public function referenceId(): int|string
    {
        return $this->reference->id();
    }

    public function referenceIdAsString(): string
    {
        return $this->reference->idAsString();
    }

    public function isBooking(): bool
    {
        return $this->reference->isBooking();
    }

    public function hasClient(): bool
    {
        return (int) $this->clientId > 0;
    }

    public function hasBusiness(): bool
    {
        return (int) $this->businessId > 0;
    }

    public function hasService(): bool
    {
        return (int) $this->platformServiceId > 0;
    }

    public function hasCategoryContext(): bool
    {
        return (int) $this->categoryId > 0 && (int) $this->childId > 0;
    }

    public function amount(): float
    {
        return round(max((float) $this->amount, 0), 2);
    }

    public function currency(): string
    {
        $currency = strtoupper(trim((string) $this->currency));

        return $currency !== '' ? $currency : 'EGP';
    }

    public function metaValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }

    public function toWalletReference(): array
    {
        return $this->reference->toWalletReference();
    }

    public function toMeta(): array
    {
        return array_merge($this->reference->toMeta(), [
            'client_id' => $this->clientId,
            'business_id' => $this->businessId,
            'platform_service_id' => $this->platformServiceId,
            'category_id' => $this->categoryId,
            'child_id' => $this->childId,
            'status' => $this->status,
            'amount' => $this->amount(),
            'currency' => $this->currency(),
        ]);
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference->toArray(),
            'reference_type' => $this->referenceType(),
            'reference_id' => $this->referenceIdAsString(),

            'client_id' => $this->clientId,
            'business_id' => $this->businessId,
            'platform_service_id' => $this->platformServiceId,
            'category_id' => $this->categoryId,
            'child_id' => $this->childId,

            'status' => $this->status,
            'amount' => $this->amount(),
            'currency' => $this->currency(),
            'meta' => $this->meta,
        ];
    }
}