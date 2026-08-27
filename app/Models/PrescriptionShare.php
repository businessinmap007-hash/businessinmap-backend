<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only access grant onto a prescription for a doctor who did not write
 * it — a second opinion, a new clinic taking over the case. Either the
 * patient or the original doctor may create one («الاثنين معا»);
 * `shared_by_user_id` records which. See Prescription::partyIds().
 */
class PrescriptionShare extends Model
{
    protected $fillable = [
        'prescription_id',
        'doctor_id',
        'shared_by_user_id',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}
