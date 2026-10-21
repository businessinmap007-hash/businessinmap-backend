<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BusinessHoursService;
use Illuminate\Http\Request;

/**
 * The app's search box: businesses by NAME, across every category.
 *
 * It used to ride along on the old commercial-offers search (`/search/offers`), which was removed with that system and left the
 * box answering «حدث خطأ». Names only: this endpoint is public, and matching e-mail or phone would let anyone confirm that an address
 * belongs to a registered account by watching the result count.
 */
final class BusinessSearchController extends Controller
{
    /** GET /api/v2/search/businesses?q= */
    public function index(Request $request, BusinessHoursService $hours)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $q = trim((string) ($data['q'] ?? ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => ['query' => ['q' => $q], 'businesses' => []]]);
        }

        // `searchByName` also covers `name_en`, which makes a Latin-named business findable in Arabic and the reverse
        $rows = User::query()->live()->where('type', User::TYPE_BUSINESS)->searchByName($q)
            ->latest('id')->limit(20)
            ->get(['id', 'name', 'name_en', 'type', 'logo', 'category_id', 'category_child_id']);

        return response()->json(['success' => true, 'data' => [
            'query' => ['q' => $q],
            'businesses' => $rows->map(fn (User $b) => [
                'id' => (int) $b->id,
                'name' => $b->displayName(),
                'type' => (string) $b->type,
                'logo' => $b->logo,
                'category_id' => $b->category_id !== null ? (int) $b->category_id : null,
                'category_child_id' => $b->category_child_id !== null ? (int) $b->category_child_id : null,
                'is_open_now' => $hours->isOpenNow((int) $b->id),
            ])->values(),
        ]]);
    }
}
