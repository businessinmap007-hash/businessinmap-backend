<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AllocationReleaseRequest;
use App\Models\BookableItem;
use App\Services\Partnerships\AllocationReleases;
use App\Support\BusinessContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * «طلب تحرير غرفة»: a guest asks for a room a partner was promised; the partner answers. The guest reads the status; the partner reads
 * only the room and the dates, never who asked.
 */
class ReleaseRequestController extends Controller
{
    public function __construct(private readonly AllocationReleases $releases)
    {
    }

    /** POST bookings/release-requests {bookable_id, starts_at, ends_at, quantity?} */
    public function store(Request $request)
    {
        $data = $request->validate([
            'bookable_id' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $unit = BookableItem::query()->where('is_active', 1)->findOrFail((int) $data['bookable_id']);
        $req = $this->releases->request($request->user(), $unit, Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']), max((int) ($data['quantity'] ?? 1), 1));

        return response()->json(['success' => true, 'data' => ['request' => $this->releases->present($req)]], 201);
    }

    /** GET bookings/release-requests/{id} — the guest's own request. */
    public function show(Request $request, int $id)
    {
        $req = AllocationReleaseRequest::query()->where('requester_id', $request->user()->id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => ['request' => $this->releases->present($req)]]);
    }

    /** GET business/partnerships/release-requests — what this partner is asked. */
    public function pending(Request $request)
    {
        $rows = $this->releases->pendingFor(BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => ['requests' => $rows->map(fn ($r) => $this->releases->presentForPartner($r))->values()]]);
    }

    /** POST business/partnerships/release-requests/{id}/approve|decline */
    public function answer(Request $request, int $id, string $answer)
    {
        abort_unless(in_array($answer, ['approve', 'decline'], true), 404);

        $me = BusinessContext::id($request);

        // a business that was never asked gets 404, like a stranger to a partnership
        abort_unless($this->releases->pendingFor($me)->contains('id', $id), 404);

        $req = $answer === 'approve' ? $this->releases->approve($id, $me) : $this->releases->decline($id, $me);

        return response()->json(['success' => true, 'data' => ['request' => $this->releases->presentForPartner($req->load('item.lineOption:id,name_ar,name_en'))]]);
    }
}
