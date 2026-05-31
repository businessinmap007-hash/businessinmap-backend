<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ServiceEvent extends Model
{
    protected $table = 'service_events';

    public const STATUS_RECORDED = 'recorded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'event_key',
        'service_key',
        'action_key',
        'subject_type',
        'subject_id',
        'actor_id',
        'business_id',
        'client_id',
        'status',
        'payload',
        'occurred_at',
        'processed_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'actor_id' => 'integer',
        'business_id' => 'integer',
        'client_id' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeForEvent(Builder $query, ?string $eventKey): Builder
    {
        $eventKey = trim((string) $eventKey);

        if ($eventKey === '') {
            return $query;
        }

        return $query->where('event_key', $eventKey);
    }

    public function scopeForService(Builder $query, ?string $serviceKey): Builder
    {
        $serviceKey = trim((string) $serviceKey);

        if ($serviceKey === '') {
            return $query;
        }

        return $query->where('service_key', $serviceKey);
    }

    public function scopeForAction(Builder $query, ?string $actionKey): Builder
    {
        $actionKey = trim((string) $actionKey);

        if ($actionKey === '') {
            return $query;
        }

        return $query->where('action_key', $actionKey);
    }

    public function scopeForSubject(Builder $query, ?Model $subject): Builder
    {
        if (! $subject || ! $subject->exists) {
            return $query;
        }

        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeForActor(Builder $query, ?int $actorId): Builder
    {
        if (! $actorId) {
            return $query;
        }

        return $query->where('actor_id', $actorId);
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }

    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        if (! $clientId) {
            return $query;
        }

        return $query->where('client_id', $clientId);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);

        if ($status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function markProcessing(): bool
    {
        $this->status = self::STATUS_PROCESSING;

        return $this->save();
    }

    public function markProcessed(): bool
    {
        $this->status = self::STATUS_PROCESSED;
        $this->processed_at = now();

        return $this->save();
    }

    public function markFailed(?string $message = null): bool
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        if ($message) {
            $payload['_failure'] = [
                'message' => $message,
                'failed_at' => now()->toDateTimeString(),
            ];
        }

        $this->payload = $payload;
        $this->status = self::STATUS_FAILED;

        return $this->save();
    }
}