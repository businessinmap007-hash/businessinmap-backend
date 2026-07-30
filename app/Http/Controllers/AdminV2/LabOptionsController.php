<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Taxonomy Lab — the child ↔ options builder. Pick a category child (الابن),
 * then a two-column transfer assigns which options describe it. The target is
 * the real link table (sandbox copy `category_child_option_new`, i.e.
 * "child_options"). Sandbox only; live data is untouched.
 */
class LabOptionsController extends Controller
{
    /** Children grouped by their parent category, each with its assigned-option count. */
    public function index()
    {
        $counts = DB::table('category_child_option_new')
            ->select('child_id', DB::raw('count(*) as c'))
            ->groupBy('child_id')->pluck('c', 'child_id');

        $parents = [];
        $rows = DB::table('category_parent_child as pc')
            ->join('category_children_master as m', 'm.id', '=', 'pc.child_id')
            ->join('categories as c', 'c.id', '=', 'pc.parent_id')
            ->orderBy('c.name_ar')->orderBy('m.name_ar')
            ->get(['m.id', 'm.name_ar', 'pc.parent_id', 'c.name_ar as parent_name']);

        foreach ($rows as $r) {
            $parents[$r->parent_id]['name'] ??= $r->parent_name;
            $parents[$r->parent_id]['children'][] = [
                'id' => $r->id,
                'name' => $r->name_ar,
                'count' => (int) ($counts[$r->id] ?? 0),
            ];
        }

        return view('admin-v2.taxonomy-lab.options-index', ['parents' => $parents]);
    }

    /** Two-column data for one child: all options + the ones already assigned. */
    public function child(int $child)
    {
        $childRow = DB::table('category_children_master')->where('id', $child)->first(['id', 'name_ar']);
        abort_unless($childRow, 404);

        $all = DB::table('options_new')
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($o) => ['id' => (int) $o->id, 'name' => (string) ($o->name_ar ?: $o->name_en ?: "#{$o->id}")])
            ->values();

        $selected = DB::table('category_child_option_new')->where('child_id', $child)->pluck('option_id')
            ->map(fn ($v) => (int) $v)->values();

        return view('admin-v2.taxonomy-lab.options-builder', [
            'child' => $childRow,
            'all' => $all,
            'selected' => $selected,
        ]);
    }

    /** Sync the child's options to exactly the submitted set (batch save). */
    public function save(Request $request, int $child): JsonResponse
    {
        abort_unless(DB::table('category_children_master')->where('id', $child)->exists(), 404);

        $data = $request->validate([
            'option_ids' => ['present', 'array'],
            'option_ids.*' => ['integer'],
        ]);

        $wanted = collect($data['option_ids'])->map(fn ($v) => (int) $v)->unique();
        // Keep only ids that are real options.
        $valid = DB::table('options_new')->whereIn('id', $wanted)->pluck('id')->map(fn ($v) => (int) $v);

        DB::transaction(function () use ($child, $valid) {
            $existing = DB::table('category_child_option_new')->where('child_id', $child)->pluck('option_id')
                ->map(fn ($v) => (int) $v);

            $toAdd = $valid->diff($existing);
            $toRemove = $existing->diff($valid);

            if ($toRemove->isNotEmpty()) {
                DB::table('category_child_option_new')->where('child_id', $child)
                    ->whereIn('option_id', $toRemove->all())->delete();
            }
            foreach ($toAdd as $optId) {
                DB::table('category_child_option_new')->insert(['child_id' => $child, 'option_id' => $optId]);
            }
        });

        return response()->json(['ok' => true, 'count' => $valid->count()]);
    }
}
