<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Guarantees\GuaranteeBadgeService;
use Illuminate\Http\Request;

/**
 * GET /api/v2/guarantee-badges?ids=1,2,3 — the guarantee level each of these accounts wears beside its name, so any
 * screen that shows an account can show the badge with one batched call (see GuaranteeBadgeService).
 */
class GuaranteeBadgeController extends Controller
{
    public function index(Request $request, GuaranteeBadgeService $badges)
    {
        $raw = $request->query('ids', '');
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);

        return response()->json([
            'success' => true,
            // keyed by account id; an account with no usable guarantee is null (no badge)
            'data' => ['badges' => $badges->forUsers($ids)],
        ]);
    }
}
