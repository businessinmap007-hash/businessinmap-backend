<?php

namespace App\Support\AdminV2\Operations;

final class OperationWorkflowResult
{
    public function __construct(
        public readonly OperationContext $context,
        public readonly string $state,
        public readonly string $label,
        public readonly ?string $nextAction = null,
        public readonly array $availableActions = [],
        public readonly array $blockedReasons = [],
        public readonly array $warnings = [],
        public readonly array $flags = [],
        public readonly array $meta = [],
    ) {
    }

    public static function make(
        OperationContext $context,
        string $state,
        ?string $label = null,
        ?string $nextAction = null,
        array $availableActions = [],
        array $blockedReasons = [],
        array $warnings = [],
        array $flags = [],
        array $meta = []
    ): self {
        return new self(
            context: $context,
            state: trim($state) !== '' ? trim($state) : 'unknown',
            label: $label ?: ucfirst(str_replace('_', ' ', trim($state) ?: 'unknown')),
            nextAction: $nextAction ? trim($nextAction) : null,
            availableActions: array_values(array_unique(array_filter($availableActions))),
            blockedReasons: array_values(array_filter($blockedReasons)),
            warnings: array_values(array_filter($warnings)),
            flags: $flags,
            meta: $meta,
        );
    }

    public function context(): OperationContext
    {
        return $this->context;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function nextAction(): ?string
    {
        return $this->nextAction;
    }

    public function hasNextAction(): bool
    {
        return $this->nextAction !== null && $this->nextAction !== '';
    }

    public function availableActions(): array
    {
        return $this->availableActions;
    }

    public function can(string $action): bool
    {
        return in_array($action, $this->availableActions, true);
    }

    public function cannot(string $action): bool
    {
        return ! $this->can($action);
    }

    public function blockedReasons(): array
    {
        return $this->blockedReasons;
    }

    public function isBlocked(): bool
    {
        return ! empty($this->blockedReasons);
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function flags(): array
    {
        return $this->flags;
    }

    public function flag(string $key, mixed $default = null): mixed
    {
        return data_get($this->flags, $key, $default);
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function metaValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }

    public function isReady(): bool
    {
        return (bool) ($this->flags['ready'] ?? false);
    }

    public function isFinal(): bool
    {
        return (bool) ($this->flags['final'] ?? false);
    }

    public function needsAction(): bool
    {
        return $this->hasNextAction() || $this->isBlocked();
    }

    public function statusTone(): string
    {
        if ($this->isFinal()) {
            return 'success';
        }

        if ($this->isBlocked()) {
            return 'danger';
        }

        if ($this->hasWarnings()) {
            return 'warning';
        }

        if ($this->isReady()) {
            return 'success';
        }

        return 'info';
    }

    public function toArray(): array
    {
        return [
            'context' => $this->context->toArray(),

            'state' => $this->state,
            'label' => $this->label,
            'tone' => $this->statusTone(),

            'next_action' => $this->nextAction,
            'has_next_action' => $this->hasNextAction(),

            'available_actions' => $this->availableActions,
            'blocked_reasons' => $this->blockedReasons,
            'warnings' => $this->warnings,

            'flags' => $this->flags,
            'meta' => $this->meta,

            'is_ready' => $this->isReady(),
            'is_final' => $this->isFinal(),
            'is_blocked' => $this->isBlocked(),
            'needs_action' => $this->needsAction(),
        ];
    }
}