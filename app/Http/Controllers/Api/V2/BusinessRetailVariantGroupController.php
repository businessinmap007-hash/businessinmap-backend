<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\RetailVariantGroup;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 business retail-variant-group management - "قميص كلاسيك: أزرق-M،
 * أزرق-L، أحمر-M": several of the business's own retail listings shown to
 * the customer as one product with a variant picker. Every row is scoped to
 * business_id = the authenticated user (or their delegate's employer, via
 * BusinessContext) - mirrors BusinessMenuBundleController's shape.
 */
final class BusinessRetailVariantGroupController extends Controller
{
    /** GET /api/v2/business/retail/variant-groups */
    public function index(Request $request)
    {
        $groups = RetailVariantGroup::query()
            ->where('business_id', $this->businessId($request))
            ->with('options.listing.product')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $groups->map(fn ($g) => $this->payload($g))->values()]);
    }

    /** GET /api/v2/business/retail/variant-groups/{group} */
    public function show(Request $request, int $group)
    {
        return response()->json(['success' => true, 'data' => $this->payload($this->ownGroup($request, $group))]);
    }

    /** POST /api/v2/business/retail/variant-groups */
    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validatedGroup($request, $businessId);

        $group = DB::transaction(function () use ($businessId, $data) {
            $group = RetailVariantGroup::create($data['group'] + ['business_id' => $businessId]);
            $group->options()->createMany($data['options']);

            return $group;
        });

        return response()->json(['success' => true, 'data' => $this->payload($group->fresh('options.listing.product'))], 201);
    }

    /** PUT/PATCH /api/v2/business/retail/variant-groups/{group} */
    public function update(Request $request, int $group)
    {
        $model = $this->ownGroup($request, $group);
        $data = $this->validatedGroup($request, $this->businessId($request));

        DB::transaction(function () use ($model, $data) {
            $model->update($data['group']);
            // The variant list is edited as a whole, same convention as
            // MenuBundle's fixed composition - no per-row history to
            // preserve here either.
            $model->options()->delete();
            $model->options()->createMany($data['options']);
        });

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh('options.listing.product'))]);
    }

    /** DELETE /api/v2/business/retail/variant-groups/{group} - the listings themselves are untouched. */
    public function destroy(Request $request, int $group)
    {
        $this->ownGroup($request, $group)->delete();

        return response()->json(['success' => true]);
    }

    // -- Helpers --

    private function businessId(Request $request): int
    {
        return BusinessContext::id($request);
    }

    private function ownGroup(Request $request, int $groupId): RetailVariantGroup
    {
        return RetailVariantGroup::query()
            ->where('business_id', $this->businessId($request))
            ->with('options.listing.product')
            ->findOrFail($groupId);
    }

    /** @return array{group: array<string,mixed>, options: array<int,array<string,mixed>>} */
    private function validatedGroup(Request $request, int $businessId): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            // A single variant under its own name is not a "family" - it is
            // just that listing on its own shelf.
            'options' => ['required', 'array', 'min:2', 'max:30'],
            // Must belong to THIS business - an id from someone else's shelf must not slip in.
            'options.*.listing_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('business_catalog_listings', 'id')->where('business_id', $businessId),
            ],
            'options.*.label_ar' => ['required', 'string', 'max:120'],
            'options.*.label_en' => ['nullable', 'string', 'max:120'],
        ], [], [
            'name_ar' => __('اسم المنتج'),
            'options' => __('التنويعات'),
        ]);

        $listingIds = collect($data['options'])->pluck('listing_id')->map(fn ($id) => (int) $id)->all();

        // A listing already promised to another group (or, on update, a
        // different group than this one) can't be borrowed silently - the
        // customer would see it offered twice under two different names.
        $groupId = $request->route('group');
        $taken = DB::table('retail_variant_options')
            ->whereIn('business_catalog_listing_id', $listingIds)
            ->when($groupId, fn ($q) => $q->where('retail_variant_group_id', '!=', (int) $groupId))
            ->pluck('business_catalog_listing_id');

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'options' => __('بعض هذه التنويعات مضافة بالفعل إلى منتج آخر: :ids', ['ids' => $taken->implode(', ')]),
            ]);
        }

        $options = collect($data['options'])->map(fn ($row, $i) => [
            'business_catalog_listing_id' => (int) $row['listing_id'],
            'label_ar' => trim((string) $row['label_ar']),
            'label_en' => trim((string) ($row['label_en'] ?? '')) ?: null,
            'sort_order' => $i,
        ])->values()->all();

        return [
            'group' => [
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            ],
            'options' => $options,
        ];
    }

    private function payload(RetailVariantGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name_ar' => (string) $group->name_ar,
            'name_en' => $group->name_en,
            'is_active' => (bool) $group->is_active,
            'sort_order' => (int) $group->sort_order,
            'options' => $group->options->map(function (\App\Models\RetailVariantOption $o) {
                /** @var BusinessCatalogListing|null $listing */
                $listing = $o->listing;

                return [
                    'id' => (int) $o->id,
                    'listing_id' => (int) $o->business_catalog_listing_id,
                    'label' => $o->display_label,
                    'label_ar' => $o->label_ar,
                    'label_en' => $o->label_en,
                    'price' => $listing ? (float) $listing->price : null,
                    'stock' => $listing?->stock !== null ? (int) $listing->stock : null,
                    'is_active' => $listing ? (bool) $listing->is_active : false,
                    'product_name' => $listing?->product?->displayName(),
                ];
            })->values(),
        ];
    }
}
