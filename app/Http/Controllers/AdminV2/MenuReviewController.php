<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Menu\MenuOutline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «مراجعة المنيو» — one business's whole menu, arranged, on one page.
 *
 * The platform had two ways to look at a menu and neither answered the
 * question: `admin.menu-items.index` is a flat list of every item on the
 * platform, fifty to a page, and the merchant's own screen shows only his own
 * rows. Neither says what a menu is SUPPOSED to contain, so «هل نقص شىء؟» had
 * no screen at all.
 *
 * @see \App\Services\Menu\MenuOutline
 */
class MenuReviewController extends Controller
{
    public function __construct(private readonly MenuOutline $outline)
    {
    }

    public function index(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);

        $business = $businessId > 0
            ? User::query()->where('type', 'business')->find($businessId)
            : null;

        return view('admin-v2.menu-review.index', [
            'business' => $business,
            'outline' => $business ? $this->outline->for($business) : null,
            'withMenus' => $this->businessesWithMenus(),
        ]);
    }

    /**
     * Who has a menu at all, biggest first.
     *
     * The picker searches 1,748 businesses by name; this is the shorter and
     * more useful answer to «مين عنده منيو أصلًا؟», and it is one query.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function businessesWithMenus()
    {
        return DB::table('menu_items as mi')
            ->join('users as u', 'u.id', '=', 'mi.business_id')
            ->leftJoin('category_children_master as c', 'c.id', '=', 'u.category_child_id')
            ->groupBy('u.id', 'u.name', 'c.name_ar')
            ->orderByDesc(DB::raw('COUNT(mi.id)'))
            ->limit(60)
            ->get([
                'u.id',
                'u.name',
                'c.name_ar as child',
                DB::raw('COUNT(mi.id) as items'),
                DB::raw('SUM(CASE WHEN mi.is_active = 1 THEN 1 ELSE 0 END) as active_items'),
            ]);
    }
}
