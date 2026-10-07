<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\FoodCategory;
use App\Models\FoodItem;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * The food catalogue a nutrition specialist picks from when adding a meal to a plan or a template («جدول التغذية»).
 *
 * The shared catalogue is curated by the platform and read-only here; what a specialist adds is THEIR own entry —
 * visible to them alone, theirs to correct and delete («مع امكانية اضافة من المتخصص لنوع غذاء»). Returned whole (a few
 * hundred short rows) so the app can filter and search it locally.
 */
final class FoodLibraryController extends Controller
{
    /** GET /api/v2/business/training/food-library — optional: category_id, q */
    public function index(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $me = BusinessContext::id($request);
        $q = trim((string) ($data['q'] ?? ''));

        $foods = FoodItem::query()->active()->visibleTo($me)
            ->whereHas('category', fn ($c) => $c->where('is_active', true))
            ->when(! empty($data['category_id']), fn ($w) => $w->where('food_category_id', $data['category_id']))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")))
            ->orderBy('food_category_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        $categories = FoodCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'categories' => $categories->map(fn (FoodCategory $c) => ['id' => $c->id, 'name' => $c->label()])->values(),
            'foods' => $foods->map(fn (FoodItem $f) => $this->serialize($f, $me))->values(),
        ]]);
    }

    /** POST /api/v2/business/training/food-library — the specialist's own food. */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $me = BusinessContext::id($request);

        $food = FoodItem::query()->create($data + [
            'owner_id' => $me,
            'sort_order' => 1 + (int) FoodItem::query()->where('owner_id', $me)->max('sort_order'),
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => ['food' => $this->serialize($food, $me)]], 201);
    }

    /** PUT /api/v2/business/training/food-library/{food} — only one's own. */
    public function update(Request $request, int $food)
    {
        $row = $this->ownedOrFail($request, $food);
        $row->update($this->validated($request));

        return response()->json(['success' => true, 'data' => ['food' => $this->serialize($row->fresh(), BusinessContext::id($request))]]);
    }

    /** DELETE /api/v2/business/training/food-library/{food} — only one's own; a plan keeps the meal it already has. */
    public function destroy(Request $request, int $food)
    {
        $this->ownedOrFail($request, $food)->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف الصنف.')]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'food_category_id' => ['required', 'integer', 'exists:food_categories,id'],
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:160'],
            'serving_label' => ['required', 'string', 'max:80'],
            'serving_grams' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'calories' => ['required', 'integer', 'min:0', 'max:5000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);
    }

    private function ownedOrFail(Request $request, int $id): FoodItem
    {
        return FoodItem::query()->where('id', $id)->where('owner_id', BusinessContext::id($request))->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function serialize(FoodItem $f, int $me): array
    {
        return [
            'id' => (int) $f->id,
            'category_id' => (int) $f->food_category_id,
            'name' => $f->label(),
            'serving' => (string) $f->serving_label,
            'serving_grams' => $f->serving_grams !== null ? (int) $f->serving_grams : null,
            'calories' => (int) $f->calories,
            'protein_g' => (float) $f->protein_g,
            'carbs_g' => (float) $f->carbs_g,
            'fat_g' => (float) $f->fat_g,
            // a specialist's own entry — the app lets them edit and delete it
            'mine' => $f->owner_id !== null && (int) $f->owner_id === $me,
        ];
    }
}
