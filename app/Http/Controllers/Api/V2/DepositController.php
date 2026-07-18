<?php

namespace App\Http\Controllers\Api\V2;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\DepositResource;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Escrow deposits, from the point of view of a party to one.
 *
 * **Read-only, deliberately.** v1 exposed create/release/refund/start-execution
 * as raw REST with no authorization at all, which let any signed-in user move
 * money between wallets they had nothing to do with. Those endpoints also went
 * straight past the services that own this lifecycle:
 *
 *   - `BookingDepositService` creates, releases and refunds alongside a booking;
 *   - `DisputeService` releases or refunds when a dispute is ruled on.
 *
 * Every one of the deposits in the database was created that way. Re-exposing
 * raw escrow controls here would rebuild the same hole in a new namespace, so
 * v2 answers the question the app actually has — "what is being held for me,
 * and what happened to it" — and leaves the money moves to the flows that
 * understand why they are happening.
 */
final class DepositController extends Controller
{
    /** GET /api/v2/deposits — deposits this account is a party to. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(DepositStatus::values())],
            'role' => ['nullable', 'in:client,business'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $userId = (int) $request->user()->id;

        $deposits = Deposit::query()
            ->with(['client:id,name', 'business:id,name'])
            ->where(function ($w) use ($data, $userId) {
                // No filter parameter can widen this beyond the caller.
                match ($data['role'] ?? null) {
                    'client' => $w->where('client_id', $userId),
                    'business' => $w->where('business_id', $userId),
                    default => $w->where('client_id', $userId)->orWhere('business_id', $userId),
                };
            })
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->appends($request->query());

        return DepositResource::collection($deposits);
    }

    /** GET /api/v2/deposits/{deposit} — one, if you are a party to it. */
    public function show(Request $request, Deposit $deposit)
    {
        $userId = (int) $request->user()->id;

        // 404 rather than 403: a stranger must not learn that the id exists.
        abort_if(
            (int) $deposit->client_id !== $userId && (int) $deposit->business_id !== $userId,
            404
        );

        $deposit->loadMissing(['client:id,name', 'business:id,name']);

        return response()->json(['success' => true, 'data' => new DepositResource($deposit)]);
    }
}
