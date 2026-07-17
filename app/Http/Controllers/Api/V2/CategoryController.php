<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * v2 classification — the marketplace's front door.
 *
 * Found by MenuOrderJourneyTest's sibling: `discovery/filters` and
 * `discovery/businesses` both REQUIRE `child_id`, and v2 had no endpoint that
 * returned one. The app could not browse a single business — same shape as the
 * BIM-11.1 address bug: a required parameter with no way to discover it.
 *
 * The structure, read out of the data rather than assumed:
 *   - 21 root categories — `categories.parent_id = 0` (NOT null; nothing uses null).
 *   - Each root links to specialties in `category_children_master` through
 *     `category_parent_child`. All 418 links hang off roots; the `categories`
 *     self-tree below a root carries none, so it is not part of this path.
 *   - `child_id` is a `category_children_master.id`, and that is what discovery
 *     is keyed on.
 *
 * So: root → specialty → discovery. Two hops, and the app needs both.
 *
 * Public: browsing the catalogue of what exists must not require an account.
 */
final class CategoryController extends Controller
{
    /** GET /api/v2/categories — the root categories the app opens with. */
    public function index(Request $request)
    {
        $categories = DB::table('categories')
            ->where('parent_id', 0)
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id')
            // per_month / per_year are the abandoned subscription pricing and
            // are deliberately not exposed.
            ->get(['id', 'name_ar', 'name_en', 'slug', 'image'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name_ar' => $c->name_ar,
                'name_en' => $c->name_en,
                'slug' => $c->slug,
                'image' => $c->image,
            ]);

        return response()->json(['success' => true, 'data' => ['categories' => $categories]]);
    }

    /**
     * GET /api/v2/categories/{category}/specialties
     *
     * The `child_id` values discovery needs. Each carries how many businesses
     * actually sell it: a specialty with none is a dead end, and the app should
     * be able to tell before the customer taps it. `?sellable=1` drops them.
     */
    public function specialties(Request $request, int $category)
    {
        $data = $request->validate([
            'sellable' => ['nullable', 'boolean'],
        ]);

        $exists = DB::table('categories')->where('id', $category)->where('is_active', 1)->exists();

        if (! $exists) {
            return response()->json(['success' => false, 'message' => 'التصنيف غير موجود.'], 404);
        }

        // The count mirrors what DiscoveryController actually searches:
        // active business_service_prices for this child.
        $specialties = DB::table('category_parent_child as l')
            ->join('category_children_master as m', 'm.id', '=', 'l.child_id')
            ->leftJoin('business_service_prices as p', function ($join) {
                $join->on('p.child_id', '=', 'm.id')->where('p.is_active', '=', 1);
            })
            ->where('l.parent_id', $category)
            ->groupBy('m.id', 'm.name_ar', 'm.name_en', 'm.reorder')
            ->selectRaw('m.id, m.name_ar, m.name_en, m.reorder, COUNT(DISTINCT p.business_id) as businesses')
            ->orderByRaw('COALESCE(m.reorder, 999999) ASC')
            ->orderBy('m.id')
            ->get();

        if (! empty($data['sellable'])) {
            $specialties = $specialties->filter(fn ($s) => (int) $s->businesses > 0)->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'specialties' => $specialties->map(fn ($s) => [
                    'id' => (int) $s->id,          // this is discovery's child_id
                    'name_ar' => $s->name_ar,
                    'name_en' => $s->name_en,
                    'businesses' => (int) $s->businesses,
                ])->values(),
            ],
        ]);
    }
}
