<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the shared medicine dictionary (see the create migration): a drug
 * name and its strength, contributed by doctors as they write prescriptions.
 */
class Medicine extends Model
{
    protected $fillable = [
        'name',
        'strength',
        'created_by',
        'uses_count',
    ];

    protected $casts = [
        'uses_count' => 'integer',
    ];

    /**
     * Record that a doctor wrote this drug: create it on first sight, and count
     * the use either way. Normalises blank strength to null so "500mg" and
     * "500mg " don't split into two rows. Returns the dictionary row.
     */
    public static function remember(string $name, ?string $strength, ?int $doctorId): self
    {
        $name = trim($name);
        $strength = trim((string) $strength);
        $strength = $strength === '' ? null : $strength;

        $medicine = static::query()->firstOrCreate(
            ['name' => $name, 'strength' => $strength],
            ['created_by' => $doctorId, 'uses_count' => 0],
        );

        $medicine->increment('uses_count');

        return $medicine;
    }
}
