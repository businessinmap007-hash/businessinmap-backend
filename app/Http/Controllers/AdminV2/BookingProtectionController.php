<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingProtectionDecisionEngine;
use App\Services\ServiceExecutionEngine;
use Illuminate\Http\Request;

class BookingProtectionController extends Controller
{
    public function preview(Request $request, BookingProtectionDecisionEngine $engine, ServiceExecutionEngine $executionEngine)
    {
        $bookingId = (int) $request->get('booking_id', 0);

        if ($bookingId > 0) {
            $booking = Booking::withTrashed()->findOrFail($bookingId);

            return response()->json([
                'ok' => true,
                'protection' => $engine->decideForBooking($booking, $executionEngine->depositPolicy($booking)),
            ]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'business_id' => ['required', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_required' => ['nullable'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $depositPolicy = [
            'required' => $request->boolean('deposit_required', false),
            'amount' => round((float) ($data['deposit_amount'] ?? 0), 2),
        ];

        return response()->json([
            'ok' => true,
            'protection' => $engine->decide(
                clientId: (int) $data['user_id'],
                businessId: (int) $data['business_id'],
                amount: round((float) ($data['amount'] ?? 0), 2),
                depositPolicy: $depositPolicy
            ),
        ]);
    }
}
