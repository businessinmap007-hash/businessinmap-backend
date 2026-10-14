<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use App\Support\BusinessContext;
use App\Support\Providers;
use Illuminate\Http\Request;

/**
 * «نظام التعيين» — the same three calls for every business:
 *   GET business/providers                       who can be assigned here (the owner's choice list)
 *   PUT business/bookings/{booking}/assignee     hand a booking to a provider, or take it back (assignee_id null)
 *   GET businesses/{business}/providers          the people a customer may see at a business (name + title)
 * Orders and anything else morphable follow the same shape through AssignmentService.
 */
class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments)
    {
    }

    /** GET /api/v2/business/providers */
    public function providers(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['providers' => Providers::of(BusinessContext::id($request))->all()],
        ]);
    }

    /** GET /api/v2/businesses/{business}/providers — public to any signed-in customer. */
    public function publicProviders(int $business)
    {
        abort_unless(User::query()->whereKey($business)->where('type', User::TYPE_BUSINESS)->exists(), 404);

        return response()->json([
            'success' => true,
            'data' => ['providers' => Providers::of($business)->map(fn (array $p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'title' => $p['title'],
                'logo' => $p['logo'],
            ])->all()],
        ]);
    }

    /** PUT /api/v2/business/bookings/{booking}/assignee */
    public function assignBooking(Request $request, int $booking)
    {
        $data = $request->validate(['assignee_id' => ['nullable', 'integer', 'min:1']]);

        $row = Booking::query()->findOrFail($booking);
        $businessId = BusinessContext::id($request);

        abort_if((int) $row->business_id !== $businessId, 403, 'Booking does not belong to this business account.');
        // a provider works his own list — handing work around is the owner's / the reception's call
        abort_if(Providers::actingId($request) !== null, 403, __('التعيين من صلاحيات المالك أو الاستقبال.'));

        $this->assignments->assign($row, isset($data['assignee_id']) ? (int) $data['assignee_id'] : null, (int) $request->user()->id);

        $assignee = $this->assignments->assigneeOf($row);

        return response()->json([
            'success' => true,
            'message' => $assignee ? __('تم تعيين المسؤول.') : __('تم إلغاء التعيين.'),
            'data' => ['assignee' => $assignee ? (Providers::parties($businessId, [$assignee])[$assignee] ?? null) : null],
        ]);
    }
}
