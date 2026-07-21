<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\FineResource;
use App\Models\Fine;
use App\Services\FineService;
use Illuminate\Http\Request;

/**
 * The fined user's own view: see your fines and contest one while its window is
 * open. There is no way here to levy, decide or collect — those are the
 * platform's, on the admin side. A fine that isn't yours is 404, never 403, so
 * the endpoint never confirms another user's fine exists.
 */
final class FineController extends Controller
{
    public function __construct(private readonly FineService $fines) {}

    public function index(Request $request)
    {
        $fines = Fine::query()
            ->where('user_id', (int) $request->user()->id)
            ->with('appeals')
            ->orderByDesc('id')
            ->paginate(20);

        return FineResource::collection($fines);
    }

    public function show(Request $request, int $fine)
    {
        $model = Fine::query()
            ->where('id', $fine)
            ->where('user_id', (int) $request->user()->id)
            ->with('appeals')
            ->firstOrFail();

        return new FineResource($model);
    }

    public function appeal(Request $request, int $fine)
    {
        $data = $request->validate([
            'statement' => ['required', 'string', 'max:2000'],
        ]);

        $model = Fine::query()
            ->where('id', $fine)
            ->where('user_id', (int) $request->user()->id)
            ->firstOrFail();

        $this->fines->appeal($model, (int) $request->user()->id, $data['statement']);

        return (new FineResource($model->fresh('appeals')))
            ->additional(['success' => true, 'message' => __('تم تقديم اعتراضك وسيُراجَع.')]);
    }
}
