<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\LabList;
use App\Models\LabListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Taxonomy Lab "lists" builder — the hierarchical, unified sections we build
 * on top of the sandbox. A list nests (سيارات → ماركات سيارات/موتوسيكلات) and its
 * items come from either source (options_new / platform_service_item_types_new).
 * Works only on the sandbox tables; live data is never touched.
 */
class LabListController extends Controller
{
    /** Top-level lists, each with its sub-list + item counts. */
    public function index()
    {
        $lists = LabList::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (LabList $l) => [
                'id' => $l->id,
                'name' => $l->displayName('ar'),
                'children' => $l->children->count(),
                'items' => $l->items()->count()
                    + $l->children->sum(fn (LabList $c) => $c->items()->count()),
            ]);

        return view('admin-v2.taxonomy-lab.lists', ['lists' => $lists]);
    }

    /** The internal drill-down page for one list: its sub-lists + a two-column
     *  transfer that assigns SERVICES (item types) to this branch. */
    public function show(LabList $list)
    {
        $list->load(['children.items', 'items', 'parent']);

        // The transfer manages item-type items only; other sources (options /
        // specialties) are shown read-only so nothing looks lost.
        $selected = $list->items->where('source', LabListItem::SOURCE_ITEM_TYPE)
            ->pluck('source_id')->map(fn ($v) => (int) $v)->values();
        $svcNames = DB::table('platform_services')->pluck('name_ar', 'id');
        $allTypes = DB::table('platform_service_item_types_new')
            ->orderBy('platform_service_id')->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'platform_service_id'])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'name' => (string) ($t->name_ar ?: $t->name_en ?: "#{$t->id}"),
                'group' => [
                    'id' => (int) $t->platform_service_id,
                    'name' => (string) ($svcNames[$t->platform_service_id] ?? 'أخرى'),
                ],
            ])
            ->values();
        $other = $this->itemsPayload($list->items->where('source', '!=', LabListItem::SOURCE_ITEM_TYPE)->values());

        return view('admin-v2.taxonomy-lab.list-show', [
            'list' => $list,
            'breadcrumb' => $this->breadcrumb($list),
            'allTypes' => $allTypes,
            'selectedTypes' => $selected,
            'otherItems' => $other,
            'subLists' => $list->children->map(fn (LabList $c) => [
                'id' => $c->id,
                'name' => $c->displayName('ar'),
                'items' => $this->itemsPayload($c->items),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', Rule::exists('lab_lists', 'id')],
        ]);

        $list = LabList::create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => (int) LabList::where('parent_id', $data['parent_id'] ?? null)->max('sort_order') + 1,
        ]);

        return response()->json(['ok' => true, 'id' => $list->id, 'name' => $list->displayName('ar')]);
    }

    public function rename(Request $request, LabList $list): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $list->update(['name_ar' => $data['name_ar'], 'name_en' => $data['name_en'] ?? $list->name_en]);

        return response()->json(['ok' => true, 'name' => $list->displayName('ar')]);
    }

    public function destroy(LabList $list): JsonResponse
    {
        $list->delete(); // cascades to sub-lists + items

        return response()->json(['ok' => true]);
    }

    public function addItem(Request $request, LabList $list): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(LabListItem::SOURCES))],
            'source_id' => ['required', 'integer'],
        ]);

        $table = LabListItem::sourceTable($data['source']);
        if (! DB::table($table)->where('id', $data['source_id'])->exists()) {
            return response()->json(['ok' => false, 'error' => 'unknown_atom'], 422);
        }

        $item = LabListItem::firstOrCreate(
            ['list_id' => $list->id, 'source' => $data['source'], 'source_id' => $data['source_id']],
            ['sort_order' => (int) $list->items()->max('sort_order') + 1],
        );

        $names = LabListItem::resolveNames([$item]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'source' => $item->source,
                'source_id' => $item->source_id,
                'name' => $names["{$item->source}:{$item->source_id}"] ?? ('#' . $item->source_id),
                'source_label' => LabListItem::label($item->source),
            ],
        ]);
    }

    public function removeItem(LabList $list, LabListItem $item): JsonResponse
    {
        abort_unless($item->list_id === $list->id, 404);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Batch-save the two-column transfer: sync the list's items OF ONE SOURCE to
     * exactly the submitted set. Items of other sources are left untouched, so
     * syncing item types never disturbs a list's options/specialties.
     */
    public function syncItems(Request $request, LabList $list): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(LabListItem::SOURCES))],
            'ids' => ['present', 'array'],
            'ids.*' => ['integer'],
        ]);

        $source = $data['source'];
        $table = LabListItem::sourceTable($source);
        $wanted = collect($data['ids'])->map(fn ($v) => (int) $v)->unique();
        $valid = DB::table($table)->whereIn('id', $wanted)->pluck('id')->map(fn ($v) => (int) $v);

        DB::transaction(function () use ($list, $source, $valid) {
            $existing = $list->items()->where('source', $source)->pluck('source_id')->map(fn ($v) => (int) $v);
            $toRemove = $existing->diff($valid);
            $toAdd = $valid->diff($existing);

            if ($toRemove->isNotEmpty()) {
                $list->items()->where('source', $source)->whereIn('source_id', $toRemove->all())->delete();
            }
            $sort = (int) $list->items()->max('sort_order');
            foreach ($toAdd as $sid) {
                LabListItem::create(['list_id' => $list->id, 'source' => $source, 'source_id' => $sid, 'sort_order' => ++$sort]);
            }
        });

        return response()->json(['ok' => true, 'count' => $valid->count()]);
    }

    /**
     * Searchable pool of atoms not already in this list — both sources, so a list
     * can mix an option and an item type. Capped for a responsive picker.
     */
    public function pool(Request $request, LabList $list): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        $taken = $list->items()->get(['source', 'source_id'])
            ->groupBy('source')
            ->map(fn ($rows) => $rows->pluck('source_id')->all());

        $results = collect();
        foreach (LabListItem::SOURCES as $source => $table) {
            $rows = DB::table($table)
                ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                    ->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%")))
                ->whereNotIn('id', $taken->get($source, []))
                ->orderBy('name_ar')
                ->limit(15)
                ->get(['id', 'name_ar', 'name_en']);

            foreach ($rows as $r) {
                $results->push([
                    'source' => $source,
                    'source_id' => (int) $r->id,
                    'name' => (string) ($r->name_ar ?: $r->name_en ?: "#{$r->id}"),
                    'source_label' => LabListItem::label($source),
                ]);
            }
        }

        // Grouped by source (max 15 each = 45) so every source is represented,
        // even with an empty query — never truncate one source away.
        return response()->json(['ok' => true, 'results' => $results->values()]);
    }

    /** [ ['id','source','source_id','name'], ... ] with names resolved in bulk. */
    private function itemsPayload($items): array
    {
        $names = LabListItem::resolveNames($items);

        return $items->map(fn (LabListItem $it) => [
            'id' => $it->id,
            'source' => $it->source,
            'source_id' => $it->source_id,
            'name' => $names["{$it->source}:{$it->source_id}"] ?? ('#' . $it->source_id),
            'source_label' => LabListItem::label($it->source),
        ])->values()->all();
    }

    /** Ancestor chain (root → current) for the drill-down breadcrumb. */
    private function breadcrumb(LabList $list): array
    {
        $chain = [];
        for ($node = $list; $node; $node = $node->parent) {
            array_unshift($chain, ['id' => $node->id, 'name' => $node->displayName('ar')]);
        }

        return $chain;
    }
}
