<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TableServiceCall;
use App\Services\TableServiceCallService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * The business side of dine-in table service calls (BIM-13.3): the live queue of
 * "waiter please / the bill" requests, and marking one handled. Scoped to the
 * acting business (owner or a staff delegate with the `orders` capability).
 */
final class TableServiceCallController extends Controller
{
    public function __construct(private readonly TableServiceCallService $tableCalls)
    {
    }

    /** GET /api/v2/business/table-calls — pending calls, newest first. */
    public function index(Request $request)
    {
        $calls = $this->tableCalls->pendingFor(BusinessContext::id($request))
            ->map(fn (TableServiceCall $call) => $this->present($call))
            ->values();

        return response()->json(['success' => true, 'data' => $calls]);
    }

    /** POST /api/v2/business/table-calls/{call}/resolve — mark handled. */
    public function resolve(Request $request, int $call)
    {
        $resolved = $this->tableCalls->resolve(
            BusinessContext::id($request),
            $call,
            (int) $request->user()->id,
        );

        return response()->json(['success' => true, 'data' => $this->present($resolved)]);
    }

    /** @return array<string,mixed> */
    private function present(TableServiceCall $call): array
    {
        return [
            'id' => (int) $call->id,
            'type' => $call->type,
            'type_label' => $call->labelAr(),
            'status' => $call->status,
            'note' => $call->note,
            'table' => [
                'id' => (int) $call->business_table_id,
                'label' => optional($call->table)->label,
            ],
            'created_at' => optional($call->created_at)->toIso8601String(),
            'resolved_at' => optional($call->resolved_at)->toIso8601String(),
        ];
    }
}
