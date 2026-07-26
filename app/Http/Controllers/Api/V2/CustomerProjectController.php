<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectService;
use Illuminate\Http\Request;

/**
 * The customer following the operation they contracted: a read-only progress
 * view of the project the business linked to their order/booking — which build
 * stage it reached and the camera evidence for each. Either party (the buyer or
 * the business) may read it; a stranger gets a 404, never proof it exists.
 */
class CustomerProjectController extends Controller
{
    public function __construct(
        private readonly OperationChatService $operations,
        private readonly ProjectService $projects,
    ) {
    }

    /** GET /api/v2/operations/{type}/{id}/project — progress for my operation. */
    public function show(Request $request, string $type, int $id)
    {
        $operation = $this->operations->resolve($type, $id);
        $this->operations->assertParty($operation, (int) $request->user()->id);

        $project = Project::query()
            ->where('operation_type', $operation->getMorphClass())
            ->where('operation_id', $operation->getKey())
            ->first();

        if (! $project) {
            return response()->json([
                'success' => true,
                'message' => __('لا توجد خطة تقدّم لهذه العملية بعد.'),
                'data' => ['project' => null],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->projects->customerView($project),
        ]);
    }
}
