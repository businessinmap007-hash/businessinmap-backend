<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Children with no root at all — the review screen the owner asked for
 * 2026-08-25: «جمع الابناء الذين ليس لديهم جذر لاقرر مصيرهم … جمعهم فى صفحة
 * اقوم بالمراجعة والحذف يدويًا».
 *
 * A rootless child is not an error by itself — it is what every completed
 * merge or retirement in this taxonomy leaves behind on purpose, the undo
 * record `child-merge-procedure` and every retirement seeder promise never to
 * delete. This screen exists because deciding whether ONE of them has outlived
 * that purpose is a human judgment, not something a seeder should guess at —
 * and because deleting one for real touches tables a plain
 * `$categoryChild->delete()` does not reach.
 *
 * ── What `destroy()` cleans up that `CategoryChildController::destroy()` does not ──
 *
 * That controller's destroy is written for a ROOTED child being taken off ONE
 * root, and already clears `category_parent_child`, `category_child_option`
 * and `category_platform_services` before the delete. Two more tables have NO
 * foreign key on `category_children_master` at all, so MySQL will not stop a
 * raw delete from orphaning them silently:
 *
 *   - `category_child_option_decisions` — the pin/withdrawal ledger. Losing the
 *     row it is about is exactly the case `ChildOptionDecisionTest > a
 *     dissolved row leaves no decision behind` already established the rule
 *     for: a decision about nothing is not a decision, so it is deleted with
 *     the child, not left to reference a row that no longer exists.
 *   - `business_deposit_policies` — found carrying a LIVE, enabled policy for
 *     a real business while auditing this exact list (id 1, business #212).
 *     This one is not debris the way the ledger is: it is money-adjacent
 *     configuration for an account that still exists. `destroy()` refuses
 *     outright rather than orphan it — the same refuse-loudly rule every
 *     seeder in this taxonomy follows when a live account is in the way.
 *
 * Everything else referencing `category_children_master.id` already has a real
 * foreign key (`information_schema.REFERENTIAL_CONSTRAINTS`, checked
 * 2026-08-25): `business_service_prices`, `category_child_service_fees`,
 * `category_service_configs`, `category_child_option_new` CASCADE; `users`,
 * `posts`, `job_follows`, `platform_service_fee_promotions` SET NULL. MySQL
 * handles those on its own.
 */
class RootlessChildrenController extends Controller
{
    /** GET admin/rootless-children */
    public function index(): View
    {
        $rows = DB::table('category_children_master as c')
            ->whereNotExists(fn ($q) => $q->from('category_parent_child')->whereColumn('child_id', 'c.id'))
            ->orderBy('c.id')
            ->get(['c.id', 'c.name_ar', 'c.name_en', 'c.created_at']);

        $ids = $rows->pluck('id');

        $decisionCounts = DB::table('category_child_option_decisions')
            ->whereIn('child_id', $ids)->selectRaw('child_id, COUNT(*) as n')
            ->groupBy('child_id')->pluck('n', 'child_id');

        $configCounts = DB::table('category_service_configs')
            ->whereIn('child_id', $ids)->selectRaw('child_id, COUNT(*) as n')
            ->groupBy('child_id')->pluck('n', 'child_id');

        $optionLinkCounts = DB::table('category_child_option')
            ->whereIn('child_id', $ids)->selectRaw('child_id, COUNT(*) as n')
            ->groupBy('child_id')->pluck('n', 'child_id');

        $depositPolicies = DB::table('business_deposit_policies')
            ->whereIn('category_child_id', $ids)
            ->selectRaw('category_child_id, GROUP_CONCAT(business_id) as business_ids, SUM(is_enabled) as enabled_count')
            ->groupBy('category_child_id')->get()->keyBy('category_child_id');

        $out = $rows->map(function ($r) use ($decisionCounts, $configCounts, $optionLinkCounts, $depositPolicies) {
            return [
                'id' => (int) $r->id,
                'name_ar' => $r->name_ar,
                'name_en' => $r->name_en,
                'created_at' => $r->created_at,
                'decisions' => (int) ($decisionCounts[$r->id] ?? 0),
                'configs' => (int) ($configCounts[$r->id] ?? 0),
                'option_links' => (int) ($optionLinkCounts[$r->id] ?? 0),
                'deposit_policy' => isset($depositPolicies[$r->id]) ? [
                    'business_ids' => (string) $depositPolicies[$r->id]->business_ids,
                    'enabled' => (int) $depositPolicies[$r->id]->enabled_count > 0,
                ] : null,
            ];
        });

        return view('admin-v2.rootless-children.index', [
            'rows' => $out,
            'total' => $out->count(),
            'blocked' => $out->where('deposit_policy', '!=', null)->count(),
        ]);
    }

    /** DELETE admin/rootless-children/{categoryChild} */
    public function destroy(CategoryChild $categoryChild): RedirectResponse
    {
        $stillRooted = DB::table('category_parent_child')->where('child_id', $categoryChild->id)->exists();

        if ($stillRooted) {
            return back()->with('error', __('هذا الابن تحت جذرٍ الآن — لم يعد يتيمًا. راجع صفحة التصنيفات الفرعية بدلًا من هذه.'));
        }

        $deposit = DB::table('business_deposit_policies')
            ->where('category_child_id', $categoryChild->id)
            ->pluck('business_id');

        if ($deposit->isNotEmpty()) {
            return back()->with('error', __(
                'لا يمكن الحذف: مرتبطٌ بسياسة ضمانٍ لحساب/حسابات :ids. عالج ذلك أولًا من شاشة سياسات الضمان.',
                ['ids' => $deposit->implode('، ')]
            ));
        }

        $name = $categoryChild->name_ar;

        DB::transaction(function () use ($categoryChild) {
            DB::table('category_child_option_decisions')->where('child_id', $categoryChild->id)->delete();
            DB::table('category_child_option')->where('child_id', $categoryChild->id)->delete();
            DB::table('category_platform_services')->where('child_id', $categoryChild->id)->delete();

            // Everything else — business_service_prices, category_service_configs,
            // category_child_service_fees, category_child_option_new — has a real
            // foreign key with ON DELETE CASCADE, and users/posts/job_follows/
            // platform_service_fee_promotions SET NULL. MySQL clears those.
            $categoryChild->delete();
        });

        return back()->with('success', __('تم حذف «:name» نهائيًا.', ['name' => $name]));
    }
}
