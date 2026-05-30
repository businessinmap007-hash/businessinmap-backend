<?php

namespace App\Support\AdminV2\Operations;

final class OperationReference
{
    public const TYPE_BOOKING = 'booking';
    public const TYPE_MENU_ORDER = 'menu_order';
    public const TYPE_DELIVERY_ORDER = 'delivery_order';
    public const TYPE_PRODUCT_ORDER = 'product_order';
    public const TYPE_SERVICE_ORDER = 'service_order';

    public function __construct(
        public readonly string $type,
        public readonly int|string $id,
        public readonly ?string $modelClass = null,
    ) {
    }

    public static function booking(int|string $id): self
    {
        return new self(
            type: self::TYPE_BOOKING,
            id: $id,
            modelClass: \App\Models\Booking::class
        );
    }

    public static function make(string $type, int|string $id, ?string $modelClass = null): self
    {
        return new self(
            type: trim((string) $type),
            id: $id,
            modelClass: $modelClass
        );
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): int|string
    {
        return $this->id;
    }

    public function idAsString(): string
    {
        return (string) $this->id;
    }

    public function idAsInt(): int
    {
        return (int) $this->id;
    }

    public function modelClass(): ?string
    {
        return $this->modelClass;
    }

    public function isBooking(): bool
    {
        return $this->type === self::TYPE_BOOKING;
    }

    public function toArray(): array
    {
        return [
            'reference_type' => $this->type,
            'reference_id' => $this->idAsString(),
            'model_class' => $this->modelClass,
        ];
    }

    public function toWalletReference(): array
    {
        return [
            'reference_type' => $this->type,
            'reference_id' => $this->idAsString(),
        ];
    }

    public function toMeta(): array
    {
        return [
            'operation_type' => $this->type,
            'operation_id' => $this->idAsString(),
            'operation_model' => $this->modelClass,
        ];
    }

    public function key(): string
    {
        return $this->type . ':' . $this->idAsString();
    }
}