<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessBookingSetting;
use App\Services\AppointmentQueue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «بالموعد أم بالدور» — how this business takes its appointments: the customer picks a time, or is given the next
 * turn of the day and the time it should come to.
 */
final class BusinessBookingFlowController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/booking-settings/flow */
    public function show()
    {
        return response()->json(['success' => true, 'data' => ['flow' => AppointmentQueue::flowOf($this->businessId())]]);
    }

    /** PUT /api/v2/business/booking-settings/flow */
    public function update(Request $request)
    {
        $data = $request->validate(['flow' => ['required', Rule::in([AppointmentQueue::FLOW_SLOTS, AppointmentQueue::FLOW_QUEUE])]]);

        BusinessBookingSetting::query()->updateOrCreate(
            ['business_id' => $this->businessId()],
            ['booking_flow' => $data['flow'] === AppointmentQueue::FLOW_QUEUE ? AppointmentQueue::FLOW_QUEUE : null]
        );

        return response()->json(['success' => true, 'data' => ['flow' => AppointmentQueue::flowOf($this->businessId())]]);
    }
}
