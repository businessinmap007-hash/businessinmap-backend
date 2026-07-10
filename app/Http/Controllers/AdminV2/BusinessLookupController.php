<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared search-as-you-type business picker used by every admin form/filter that
 * chooses a business. There are ~1,750 businesses, so embedding them as static
 * <option>s silently dropped names sorting last (e.g. Arabic) once past the cap;
 * this endpoint searches the full set server-side. Matches name or #id and
 * returns category_child_id so cascade pickers can reuse it.
 */
final class BusinessLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        $businesses = User::query()
            ->select(['id', 'name', 'category_child_id'])
            ->where('type', User::TYPE_BUSINESS)
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $w) use ($term) {
                    $w->where('name', 'like', "%{$term}%");
                    if (is_numeric($term)) {
                        $w->orWhere('id', (int) $term);
                    }
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['ok' => true, 'businesses' => $businesses]);
    }
}
