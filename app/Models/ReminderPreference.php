<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's reminder lead times (see the create migration). Read via
 * `forUser()`, which always returns an instance — a saved row or a fresh one
 * carrying the defaults.
 */
class ReminderPreference extends Model
{
    public const DEFAULT_FIRST = 1440;   // 24h before an appointment
    public const DEFAULT_SECOND = 120;   // 2h before an appointment
    public const DEFAULT_AGENDA = 0;     // at the dose/task time

    /** Bounds — also cap how far the reminder jobs look ahead. */
    public const MAX_FIRST_LEAD = 10080; // 7 days
    public const MAX_SECOND_LEAD = 1440; // 1 day
    public const MAX_AGENDA_LEAD = 1440; // 1 day

    protected $fillable = [
        'user_id',
        'appointment_first_lead_minutes',
        'appointment_second_lead_minutes',
        'agenda_lead_minutes',
    ];

    protected $casts = [
        'appointment_first_lead_minutes' => 'integer',
        'appointment_second_lead_minutes' => 'integer',
        'agenda_lead_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The saved row for a user, or a fresh one carrying the defaults. */
    public static function forUser(int $userId): self
    {
        return static::query()->firstOrNew(['user_id' => $userId]);
    }

    public function firstLead(): int
    {
        return (int) ($this->appointment_first_lead_minutes ?? self::DEFAULT_FIRST);
    }

    /** The closer reminder lead, or null when the user disabled it. */
    public function secondLead(): ?int
    {
        // A stored NULL means "disabled"; an unsaved model uses the default.
        if (! $this->exists) {
            return self::DEFAULT_SECOND;
        }

        return $this->appointment_second_lead_minutes !== null
            ? (int) $this->appointment_second_lead_minutes
            : null;
    }

    public function agendaLead(): int
    {
        return (int) ($this->agenda_lead_minutes ?? self::DEFAULT_AGENDA);
    }
}
