<?php

namespace App\Services\Business;

use App\Models\StaffActivityLog;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The one call a `business.member`-gated controller makes to answer "who did
 * this" — a business owner's end-of-shift review (StaffActivityController)
 * reads these rows back. Deliberately a single tiny method so wiring a new
 * capability in later (menu, prescriptions, ...) is a one-line addition, not
 * a new subsystem: `$this->activity->log($request, 'orders', $order, 'accepted')`.
 *
 * Always records the REAL acting user — `$request->user()->id`, the staff
 * member if one is acting — never the business id alone, which is all the
 * existing notification/event pipeline captures today.
 */
class StaffActivityLogger
{
    public function log(Request $request, string $capability, Model $subject, string $action, array $meta = []): StaffActivityLog
    {
        return StaffActivityLog::create([
            'business_id' => BusinessContext::id($request),
            'user_id' => (int) $request->user()->id,
            'capability' => $capability,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
