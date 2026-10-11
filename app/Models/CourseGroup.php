<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of a course: its own start, schedule and seats. The seats taken are the bookings that joined it and were not
 * cancelled or refused.
 */
class CourseGroup extends Model
{
    protected $fillable = [
        'business_id', 'offering_id', 'name', 'level', 'schedule_text', 'starts_on', 'ends_on', 'seats', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'seats' => 'integer',
        'is_active' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(BusinessServicePrice::class, 'offering_id');
    }

    public function seatsTaken(): int
    {
        return (int) Booking::query()
            ->where('course_group_id', $this->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
            ->count();
    }

    public function seatsLeft(): int
    {
        return max((int) $this->seats - $this->seatsTaken(), 0);
    }
}
