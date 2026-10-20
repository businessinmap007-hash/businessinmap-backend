<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One month of a client's body composition, recorded by the trainer.
 *
 * The plan's own progress log holds a weight and a note whenever the client
 * feels like adding one. That cannot answer the only question a training plan is
 * judged on — whether the weight that moved was muscle, fat or water.
 *
 * One row per plan per month, and the month is normalised to its first day so
 * «سبتمبر» is one report however many times the scale is read.
 */
class BodyCompositionReport extends Model
{
    protected $table = 'body_composition_reports';

    protected $fillable = [
        'training_plan_id',
        'client_id',
        'trainer_id',
        'for_month',
        'measured_on',
        'weight_kg',
        'muscle_mass_kg',
        'fat_percent',
        'water_percent',
        'bone_mass_kg',
        'visceral_fat',
        'notes',
    ];

    protected $casts = [
        'training_plan_id' => 'integer',
        'client_id' => 'integer',
        'trainer_id' => 'integer',
        'for_month' => 'date',
        'measured_on' => 'date',
        'weight_kg' => 'decimal:2',
        'muscle_mass_kg' => 'decimal:2',
        'fat_percent' => 'decimal:2',
        'water_percent' => 'decimal:2',
        'bone_mass_kg' => 'decimal:2',
        'visceral_fat' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** Any day in a month means that month. */
    public static function monthOf(?string $date): Carbon
    {
        return ($date ? Carbon::parse($date) : Carbon::now())->startOfMonth();
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('for_month')->orderByDesc('id');
    }

    /**
     * What changed since the month before — the whole point of a series.
     *
     * Returned as a signed delta per measure, or null where either month is
     * silent about it, so «-2.1 دهون +0.8 عضل» can be shown without the client
     * doing arithmetic on two cards.
     *
     * @return array<string,float|null>
     */
    public function deltaFrom(?self $previous): array
    {
        $keys = ['weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent', 'bone_mass_kg', 'visceral_fat'];
        $out = [];

        foreach ($keys as $key) {
            $now = $this->{$key};
            $was = $previous?->{$key};

            $out[$key] = ($now === null || $was === null)
                ? null
                : round((float) $now - (float) $was, 2);
        }

        return $out;
    }
}
