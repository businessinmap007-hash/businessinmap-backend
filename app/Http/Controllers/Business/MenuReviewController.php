<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Menu\MenuOutline;
use App\Support\BusinessPanelNav;
use Illuminate\View\View;

/**
 * «منيو كامل» — the merchant's own review of his own list.
 *
 * The admin has had this screen since the same day and the merchant is the one
 * who can act on it: a band he was given and never filled is a gap only he can
 * close. His «الأصناف» screen is a flat table of what he already wrote, so it
 * can show him everything except the thing missing from it.
 *
 * Scoped to the ACTING business — the owner, or the employer a delegated staff
 * member is acting for — through the same trait every other owner screen uses.
 * There is no id in the URL, so there is nothing to widen.
 *
 * @see \App\Services\Menu\MenuOutline
 */
class MenuReviewController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MenuOutline $outline)
    {
    }

    public function index(): View
    {
        $business = $this->actingBusiness() ?: User::query()->findOrFail($this->businessId());

        return view('business.menu.review', [
            'outline' => $this->outline->for($business),
            // «المنيو» for a restaurant, «الكتالوج» for a showroom — the same
            // name its branch in the sidebar carries.
            'catalogLabel' => BusinessPanelNav::catalogLabel(),
        ]);
    }
}
